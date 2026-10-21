<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopSyncNotification;
use App\Services\KonamiFacilityScraper;
use App\Services\ShopSyncSentryReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ShopSyncNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'shop-sync.sentry_dsn' => 'https://public@example.sentry.io/1',
            'shop-sync.admin_url' => 'https://admin.example.test/',
        ]);
    }

    #[Test]
    public function newly_hidden_shops_are_saved_and_reported_as_one_batch_after_the_update(): void
    {
        $first = $this->shop('店名変更の可能性がある店舗');
        $second = $this->shop('もう一つの店舗');
        $this->shop('既に非公開の店舗', ['is_deleted' => true]);
        $this->fakeSnapshot();
        $this->mock(ShopSyncSentryReporter::class)->shouldReceive('send')->once()
            ->withArgs(function (ShopSyncNotification $notification) use ($first, $second) {
                $this->assertTrue((bool) $first->fresh()->is_deleted);
                $this->assertTrue((bool) $second->fresh()->is_deleted);
                $this->assertSame([$first->id, $second->id], array_column($notification->shops, 'id'));
                $this->assertSame('東京都', $notification->shops[0]['prefecture']);
                $this->assertSame('東京都豊島区', $notification->shops[0]['address']);
                $this->assertSame("https://admin.example.test/admin/shops/{$first->id}/edit", $notification->shops[0]['edit_url']);

                return true;
            });

        $this->artisan('shops:sync-konami')
            ->expectsOutput('2件を非公開にしました。')
            ->expectsOutput('1件の通知をSentryへ送信しました。')
            ->assertSuccessful();

        $this->assertDatabaseCount('shop_sync_notifications', 1);
        $this->assertNotNull(ShopSyncNotification::first()->sent_at);
    }

    #[Test]
    public function no_newly_hidden_shops_means_no_new_notification(): void
    {
        $shop = $this->shop('設置中店舗');
        $this->fakeSnapshot([13 => [$shop->name]]);
        $this->mock(ShopSyncSentryReporter::class)->shouldNotReceive('send');

        $this->artisan('shops:sync-konami')->assertSuccessful();

        $this->assertDatabaseCount('shop_sync_notifications', 0);
    }

    #[Test]
    public function dry_run_does_not_create_notifications_or_send_existing_pending_notifications(): void
    {
        $shop = $this->shop('確認対象店舗');
        $pending = $this->pendingNotification();
        $this->fakeSnapshot();
        $this->mock(ShopSyncSentryReporter::class)->shouldNotReceive('send');

        $this->artisan('shops:sync-konami --dry-run')
            ->expectsOutput('通知テーブル: 準備済み。')
            ->assertSuccessful();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertDatabaseCount('shop_sync_notifications', 1);
        $this->assertNull($pending->fresh()->sent_at);
    }

    #[Test]
    public function missing_notification_table_stops_sync_before_scraping_or_changing_shops(): void
    {
        $shop = $this->shop('公開維持店舗');
        Schema::drop('shop_sync_notifications');
        $this->mock(KonamiFacilityScraper::class)->shouldNotReceive('fetch');
        $this->mock(ShopSyncSentryReporter::class)->shouldNotReceive('send');

        $this->artisan('shops:sync-konami')
            ->expectsOutput('通知テーブルが未作成です。マイグレーションを実行してください。店舗情報は変更していません。')
            ->assertFailed();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertFalse(Schema::hasTable('shop_sync_notifications'));
    }

    #[Test]
    public function dry_run_with_missing_notification_table_warns_and_still_previews_candidates(): void
    {
        $shop = $this->shop('確認対象店舗');
        Schema::drop('shop_sync_notifications');
        $this->fakeSnapshot();
        $this->mock(ShopSyncSentryReporter::class)->shouldNotReceive('send');

        $this->artisan('shops:sync-konami --dry-run')
            ->expectsOutput('通知テーブルが未作成です。通常の同期前にマイグレーションを実行してください。今回は変更候補のみ確認します。')
            ->expectsTable(['ID', '都道府県', '非公開化対象の店舗名'], [[$shop->id, '東京都', $shop->name]])
            ->expectsOutput('確認のみ: 非公開化候補 1件。店舗情報は変更していません。')
            ->assertSuccessful();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertFalse(Schema::hasTable('shop_sync_notifications'));
    }

    #[Test]
    public function failed_delivery_is_retried_with_the_same_notification_even_without_new_hides(): void
    {
        $shop = $this->shop('確認が必要な店舗');
        $this->fakeSnapshot([], 2);
        $firstId = null;
        $reporter = $this->mock(ShopSyncSentryReporter::class);
        $reporter->shouldReceive('send')->once()->ordered()->andReturnUsing(function ($notification) use (&$firstId) {
            $firstId = $notification->id;
            throw new RuntimeException('Unavailable');
        });
        $reporter->shouldReceive('send')->once()->ordered()->withArgs(function ($notification) use (&$firstId) {
            return $notification->id === $firstId;
        });

        $this->artisan('shops:sync-konami')
            ->expectsOutput('非公開化の通知をSentryへ送信できませんでした。通知は保存されているため、次回再送します。')
            ->assertFailed();

        $this->assertTrue((bool) $shop->fresh()->is_deleted);
        $this->assertNull(ShopSyncNotification::first()->sent_at);

        $this->artisan('shops:sync-konami')->expectsOutput('0件を非公開にしました。')->assertSuccessful();

        $this->assertDatabaseCount('shop_sync_notifications', 1);
        $this->assertNotNull(ShopSyncNotification::first()->sent_at);
    }

    #[Test]
    public function source_failure_still_retries_pending_notifications_but_never_hides_shops(): void
    {
        $shop = $this->shop('公開維持店舗');
        $pending = $this->pendingNotification();
        $this->mock(KonamiFacilityScraper::class)->shouldReceive('fetch')->once()
            ->andThrow(new RuntimeException('Source unavailable'));
        $this->mock(ShopSyncSentryReporter::class)->shouldReceive('send')->once()
            ->withArgs(fn ($notification) => $notification->id === $pending->id);

        $this->artisan('shops:sync-konami')->assertFailed();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertNotNull($pending->fresh()->sent_at);
        $this->assertDatabaseCount('shop_sync_notifications', 1);
    }

    #[Test]
    public function missing_dsn_keeps_notifications_pending_until_configuration_is_added(): void
    {
        config(['shop-sync.sentry_dsn' => null]);
        $this->shop('通知待ち店舗');
        $this->fakeSnapshot([], 2);
        $this->mock(ShopSyncSentryReporter::class)->shouldReceive('send')->once();

        $this->artisan('shops:sync-konami')
            ->expectsOutput('SentryのDSNが未設定のため、通知を保存しました。設定後の実行時に送信します。')
            ->assertSuccessful();
        $this->assertNull(ShopSyncNotification::first()->sent_at);

        config(['shop-sync.sentry_dsn' => 'https://public@example.sentry.io/1']);
        $this->artisan('shops:sync-konami')->assertSuccessful();

        $this->assertNotNull(ShopSyncNotification::first()->sent_at);
    }

    #[Test]
    public function sent_notifications_are_not_sent_again(): void
    {
        $this->pendingNotification()->update(['sent_at' => now()]);
        $this->fakeSnapshot();
        $this->mock(ShopSyncSentryReporter::class)->shouldNotReceive('send');

        $this->artisan('shops:sync-konami')->assertSuccessful();
    }

    #[Test]
    public function failing_to_save_notification_rolls_back_the_publication_change(): void
    {
        $shop = $this->shop('公開維持店舗');
        $this->fakeSnapshot();
        $this->mock(\App\Services\ShopSyncNotifier::class)->shouldReceive('record')->once()
            ->andThrow(new RuntimeException('Cannot save notification'));

        try {
            $this->artisan('shops:sync-konami')->run();
            $this->fail('Expected notification save failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cannot save notification', $exception->getMessage());
        }

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertDatabaseCount('shop_sync_notifications', 0);
    }

    #[Test]
    public function test_notification_does_not_change_shops_or_create_an_outbox_record(): void
    {
        $shop = $this->shop('公開維持店舗');
        $this->mock(ShopSyncSentryReporter::class)->shouldReceive('sendTest')->once();

        $this->artisan('shops:test-sync-notification')->assertSuccessful();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertDatabaseCount('shop_sync_notifications', 0);
    }

    #[Test]
    public function test_notification_requires_a_dsn(): void
    {
        config(['shop-sync.sentry_dsn' => null]);
        $this->mock(ShopSyncSentryReporter::class)->shouldNotReceive('sendTest');

        $this->artisan('shops:test-sync-notification')
            ->expectsOutput('SHOP_SYNC_SENTRY_DSNを設定してください。')->assertFailed();
    }

    private function shop(string $name, array $attributes = []): Shop
    {
        return Shop::factory()->create(array_merge([
            'name' => $name, 'prefecture_code' => 13, 'address' => '東京都豊島区',
        ], $attributes));
    }

    private function pendingNotification(): ShopSyncNotification
    {
        return ShopSyncNotification::create(['shops' => [[
            'id' => 999, 'name' => '過去に非公開化した店舗', 'prefecture' => '東京都',
            'address' => '東京都豊島区', 'edit_url' => 'https://admin.example.test/admin/shops/999/edit',
        ]]]);
    }

    private function fakeSnapshot(array $prefectures = [], int $times = 1): void
    {
        $this->mock(KonamiFacilityScraper::class)->shouldReceive('fetch')->times($times)
            ->andReturn(array_replace(array_fill_keys(range(1, 47), []), $prefectures));
    }
}
