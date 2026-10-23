<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopSyncState;
use App\Models\User;
use App\Models\UserVisit;
use App\Services\KonamiFacilityScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SyncKonamiShopsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-23 07:30:00', 'Asia/Tokyo'));
        config(['shop-sync.sentry_dsn' => null]);
    }

    #[Test]
    public function only_missing_public_shops_are_hidden_after_five_days_and_visits_are_preserved(): void
    {
        $remaining = $this->shop('残っている店舗');
        $missing = $this->shop('撤去済み店舗');
        $this->missingForFourDays($missing);
        $hidden = $this->shop('手動で非公開の店舗', ['is_deleted' => true]);
        $deleted = $this->shop('削除済み店舗');
        $deleted->delete();
        $user = User::factory()->create();
        $visit = UserVisit::factory()->create(['user_id' => $user->id, 'shop_id' => $missing->id]);
        $this->fakeSnapshot([13 => [$remaining->name, $hidden->name, '新規の公式店舗']]);

        $this->artisan('shops:sync-konami')->expectsOutput('1件を非公開にしました。')->assertSuccessful();

        $this->assertFalse((bool) $remaining->fresh()->is_deleted);
        $this->assertTrue((bool) $missing->fresh()->is_deleted);
        $this->assertTrue((bool) $hidden->fresh()->is_deleted);
        $this->assertFalse((bool) Shop::withTrashed()->find($deleted->id)->is_deleted);
        $this->assertNull($missing->fresh()->deleted_at);
        $this->assertNotNull($missing->syncState->hidden_at);
        $this->assertDatabaseCount('shops', 4);
        $this->assertDatabaseHas('user_visits', ['id' => $visit->id, 'shop_id' => $missing->id]);
        $this->getJson('/api/shops')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $remaining->id);
        $this->getJson("/api/users/{$user->id}/visited-shops")
            ->assertOk()->assertJsonPath('visited_shops.0.id', $missing->id);
    }

    #[Test]
    public function five_consecutive_daily_absences_are_required_even_in_an_empty_prefecture(): void
    {
        $shop = $this->shop('最後の設置店舗');
        $this->fakeSnapshot([], 5);

        for ($day = 1; $day <= 5; $day++) {
            $this->artisan('shops:sync-konami')
                ->expectsOutput(($day === 5 ? '1' : '0').'件を非公開にしました。')
                ->assertSuccessful();

            $this->assertSame($day, $shop->syncState()->first()->consecutive_missing_days);
            $this->assertSame($day === 5, (bool) $shop->fresh()->is_deleted);
            $this->assertDatabaseCount('shop_sync_notifications', $day === 5 ? 1 : 0);
            $this->travel(1)->day();
        }
    }

    #[Test]
    public function same_day_retries_do_not_advance_the_absence_count(): void
    {
        $shop = $this->shop('確認対象店舗');
        $this->fakeSnapshot([], 3);

        $this->artisan('shops:sync-konami')->assertSuccessful();
        $this->travel(2)->hours();
        $this->artisan('shops:sync-konami')->assertSuccessful();
        $this->artisan('shops:sync-konami')->assertSuccessful();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertSame(1, $shop->syncState()->first()->consecutive_missing_days);
        $this->assertSame('2026-10-23', $shop->syncState()->first()->last_checked_on->toDateString());
        $this->assertDatabaseCount('shop_sync_notifications', 0);
    }

    #[Test]
    public function the_absence_day_rolls_over_at_japanese_midnight(): void
    {
        $shop = $this->shop('日付境界の店舗');
        $this->fakeSnapshot([], 2);
        $this->travelTo(Carbon::parse('2026-10-23 23:59:00', 'Asia/Tokyo'));

        $this->artisan('shops:sync-konami')->assertSuccessful();
        $this->travelTo(Carbon::parse('2026-10-24 00:01:00', 'Asia/Tokyo'));
        $this->artisan('shops:sync-konami')->assertSuccessful();

        $this->assertSame(2, $shop->syncState()->first()->consecutive_missing_days);
        $this->assertSame('2026-10-24', $shop->syncState()->first()->last_checked_on->toDateString());
        $this->assertFalse((bool) $shop->fresh()->is_deleted);
    }

    #[Test]
    public function a_gap_without_an_observation_restarts_the_consecutive_count(): void
    {
        $shop = $this->shop('一日確認できなかった店舗');
        $this->missingForFourDays($shop);
        $this->travel(1)->day();
        $this->fakeSnapshot();

        $this->artisan('shops:sync-konami')->expectsOutput('0件を非公開にしました。')->assertSuccessful();

        $this->assertSame(1, $shop->syncState()->first()->consecutive_missing_days);
        $this->assertSame('2026-10-24', $shop->syncState()->first()->last_checked_on->toDateString());
        $this->assertFalse((bool) $shop->fresh()->is_deleted);
    }

    #[Test]
    public function an_official_reappearance_resets_the_count_before_the_fifth_day(): void
    {
        $shop = $this->shop('稼働中の店舗');
        $this->missingForFourDays($shop);
        $this->fakeSnapshot([13 => [$shop->name]]);

        $this->artisan('shops:sync-konami')->assertSuccessful();

        $this->assertSame(0, $shop->syncState()->first()->consecutive_missing_days);
        $this->assertNull($shop->syncState()->first()->hidden_at);
        $this->assertFalse((bool) $shop->fresh()->is_deleted);

        $this->travel(1)->day();
        $this->fakeSnapshot();
        $this->artisan('shops:sync-konami')->assertSuccessful();

        $this->assertSame(1, $shop->syncState()->first()->consecutive_missing_days);
        $this->assertDatabaseCount('shop_sync_notifications', 0);
    }

    #[Test]
    public function same_name_in_another_prefecture_does_not_keep_a_missing_shop_public(): void
    {
        $tokyo = $this->shop('同名店舗');
        $this->missingForFourDays($tokyo);
        $osaka = $this->shop('同名店舗', ['prefecture_code' => 27, 'address' => '大阪府大阪市']);
        $this->fakeSnapshot([27 => ['同名店舗']]);

        $this->artisan('shops:sync-konami')->assertSuccessful();

        $this->assertTrue((bool) $tokyo->fresh()->is_deleted);
        $this->assertFalse((bool) $osaka->fresh()->is_deleted);
    }

    #[Test]
    public function fifth_day_dry_run_lists_candidates_without_changing_shops_or_history(): void
    {
        $shop = $this->shop('確認用店舗');
        $this->missingForFourDays($shop);
        $shopBefore = $shop->fresh()->getAttributes();
        $stateBefore = $shop->syncState()->first()->getAttributes();
        $this->fakeSnapshot();

        $this->artisan('shops:sync-konami --dry-run')
            ->expectsTable(['ID', '都道府県', '非公開化対象の店舗名'], [[$shop->id, '東京都', $shop->name]])
            ->expectsOutput('確認のみ: 非公開化候補 1件。店舗情報は変更していません。')
            ->assertSuccessful();

        $this->assertSame($shopBefore, $shop->fresh()->getAttributes());
        $this->assertSame($stateBefore, $shop->syncState()->first()->getAttributes());
        $this->assertDatabaseCount('shop_sync_notifications', 0);
    }

    #[Test]
    public function dry_run_before_five_days_does_not_create_history_or_hide_candidates(): void
    {
        $shop = $this->shop('初回確認の店舗');
        $this->fakeSnapshot();

        $this->artisan('shops:sync-konami --dry-run')
            ->expectsOutput('確認のみ: 非公開化候補 0件。店舗情報は変更していません。')
            ->assertSuccessful();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertDatabaseCount('shop_sync_states', 0);
        $this->assertDatabaseCount('shop_sync_notifications', 0);
    }

    #[Test]
    public function source_failure_does_not_modify_shops_or_absence_history(): void
    {
        $shop = $this->shop('公開維持店舗');
        $this->missingForFourDays($shop);
        $before = $shop->syncState()->first()->getAttributes();
        $this->mock(KonamiFacilityScraper::class)->shouldReceive('fetch')->once()
            ->andThrow(new RuntimeException('検索結果の件数が一致しません。'));

        $this->artisan('shops:sync-konami')
            ->expectsOutput('公式検索の取得に失敗しました。店舗情報は変更していません。')
            ->assertFailed();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertSame($before, $shop->syncState()->first()->getAttributes());
        $this->assertDatabaseCount('shop_sync_notifications', 0);

        $this->travel(1)->day();
        $this->fakeSnapshot();
        $this->artisan('shops:sync-konami')->assertSuccessful();

        $this->assertSame(1, $shop->syncState()->first()->consecutive_missing_days);
        $this->assertFalse((bool) $shop->fresh()->is_deleted);
    }

    #[Test]
    public function missing_prefecture_codes_fall_back_to_address_and_unknown_shops_are_skipped(): void
    {
        $known = $this->shop('住所で判定する店舗', ['prefecture_code' => null]);
        $this->missingForFourDays($known);
        $unknown = $this->shop('判定できない店舗', ['prefecture_code' => null, 'address' => '住所調査中']);
        $this->fakeSnapshot();

        $this->artisan('shops:sync-konami')
            ->expectsOutput('都道府県または店舗名を判定できないため、1件をスキップしました。')
            ->assertSuccessful();

        $this->assertTrue((bool) $known->fresh()->is_deleted);
        $this->assertFalse((bool) $unknown->fresh()->is_deleted);
        $this->assertNull($unknown->syncState()->first());
    }

    #[Test]
    public function shop_matching_normalizes_full_width_ascii_and_whitespace(): void
    {
        $shop = $this->shop('ＧＡＭＥ　センター 池袋');
        $this->missingForFourDays($shop);
        $this->fakeSnapshot([13 => ["GAME センター\n池袋"]]);

        $this->artisan('shops:sync-konami')->expectsOutput('0件を非公開にしました。')->assertSuccessful();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertSame(0, $shop->syncState()->first()->consecutive_missing_days);
    }

    #[Test]
    public function rerunning_sync_does_not_modify_hidden_shops_or_create_duplicate_notifications(): void
    {
        $shop = $this->shop('撤去済み店舗');
        $this->missingForFourDays($shop);
        $this->fakeSnapshot([], 2);

        $this->artisan('shops:sync-konami')->expectsOutput('1件を非公開にしました。')->assertSuccessful();
        $before = $shop->fresh()->getAttributes();
        $this->travel(1)->hour();
        $this->artisan('shops:sync-konami')->expectsOutput('0件を非公開にしました。')->assertSuccessful();

        $this->assertSame($before, $shop->fresh()->getAttributes());
        $this->assertDatabaseCount('shop_sync_notifications', 1);
    }

    #[Test]
    public function automatically_hidden_existing_shops_are_republished_when_they_reappear(): void
    {
        $shop = $this->shop('再登場した店舗', ['is_deleted' => true]);
        $this->autoHiddenState($shop);
        $visit = UserVisit::factory()->create(['shop_id' => $shop->id]);
        $this->fakeSnapshot([13 => [$shop->name, '未登録の新店舗']]);

        $this->artisan('shops:sync-konami')
            ->expectsTable(['ID', '都道府県', '再公開対象の店舗名'], [[$shop->id, '東京都', $shop->name]])
            ->expectsOutput('1件を再公開しました。')->assertSuccessful();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertNull($shop->syncState()->first()->hidden_at);
        $this->assertSame(0, $shop->syncState()->first()->consecutive_missing_days);
        $this->assertDatabaseHas('user_visits', ['id' => $visit->id, 'shop_id' => $shop->id]);
        $this->assertDatabaseCount('shops', 1);
        $this->assertDatabaseCount('shop_sync_notifications', 0);
        $this->getJson('/api/shops')->assertOk()->assertJsonPath('0.id', $shop->id);
    }

    #[Test]
    public function manually_hidden_and_soft_deleted_shops_are_never_republished(): void
    {
        $manual = $this->shop('手動非公開の店舗', ['is_deleted' => true]);
        $this->missingForFourDays($manual);
        $manualBefore = $manual->syncState()->first()->getAttributes();
        $deleted = $this->shop('削除済みの店舗', ['is_deleted' => true]);
        $this->autoHiddenState($deleted);
        $deleted->delete();
        $before = $deleted->syncState()->first()->getAttributes();
        $this->fakeSnapshot([13 => [$manual->name, $deleted->name]]);

        $this->artisan('shops:sync-konami')->expectsOutput('0件を再公開しました。')->assertSuccessful();

        $this->assertTrue((bool) $manual->fresh()->is_deleted);
        $this->assertSame($manualBefore, $manual->syncState()->first()->getAttributes());
        $this->assertTrue((bool) Shop::withTrashed()->find($deleted->id)->is_deleted);
        $this->assertNotNull(Shop::withTrashed()->find($deleted->id)->deleted_at);
        $this->assertSame($before, $deleted->syncState()->first()->getAttributes());
    }

    #[Test]
    public function a_dry_run_previews_republication_without_changing_the_hidden_shop_or_history(): void
    {
        $shop = $this->shop('復活確認の店舗', ['is_deleted' => true]);
        $this->autoHiddenState($shop);
        $shopBefore = $shop->fresh()->getAttributes();
        $stateBefore = $shop->syncState()->first()->getAttributes();
        $this->fakeSnapshot([13 => [$shop->name]]);

        $this->artisan('shops:sync-konami --dry-run')
            ->expectsTable(['ID', '都道府県', '再公開対象の店舗名'], [[$shop->id, '東京都', $shop->name]])
            ->expectsOutput('確認のみ: 再公開候補 1件。店舗情報は変更していません。')
            ->assertSuccessful();

        $this->assertSame($shopBefore, $shop->fresh()->getAttributes());
        $this->assertSame($stateBefore, $shop->syncState()->first()->getAttributes());
        $this->assertDatabaseCount('shop_sync_notifications', 0);
    }

    #[Test]
    public function a_republished_shop_starts_a_new_absence_streak_and_requires_another_five_days(): void
    {
        $shop = $this->shop('消えたり復活したりする店舗', ['is_deleted' => true]);
        $this->autoHiddenState($shop);
        $this->fakeSnapshot([13 => [$shop->name]]);
        $this->artisan('shops:sync-konami')->assertSuccessful();
        $this->fakeSnapshot([], 5);

        for ($day = 1; $day <= 5; $day++) {
            $this->travel(1)->day();
            $this->artisan('shops:sync-konami')->assertSuccessful();
            $this->assertSame($day, $shop->syncState()->first()->consecutive_missing_days);
            $this->assertSame($day === 5, (bool) $shop->fresh()->is_deleted);
        }

        $this->assertNotNull($shop->syncState()->first()->hidden_at);
        $this->assertDatabaseCount('shop_sync_notifications', 1);
    }

    #[Test]
    public function missing_history_table_stops_normal_sync_before_fetching(): void
    {
        $shop = $this->shop('公開維持店舗');
        Schema::drop('shop_sync_states');
        $this->mock(KonamiFacilityScraper::class)->shouldNotReceive('fetch');

        $this->artisan('shops:sync-konami')
            ->expectsOutput('同期履歴テーブルが未作成です。マイグレーションを実行してください。店舗情報は変更していません。')
            ->assertFailed();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertFalse(Schema::hasTable('shop_sync_states'));
        $this->assertDatabaseCount('shop_sync_notifications', 0);
    }

    #[Test]
    public function missing_history_table_allows_a_dry_run_but_never_assumes_five_missing_days(): void
    {
        $shop = $this->shop('初回確認の店舗');
        Schema::drop('shop_sync_states');
        $this->fakeSnapshot();

        $this->artisan('shops:sync-konami --dry-run')
            ->expectsOutput('確認のみ: 非公開化候補 0件。店舗情報は変更していません。')
            ->assertSuccessful();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
        $this->assertFalse(Schema::hasTable('shop_sync_states'));
        $this->assertDatabaseCount('shop_sync_notifications', 0);
    }

    private function shop(string $name, array $attributes = []): Shop
    {
        return Shop::factory()->create(array_merge([
            'name' => $name, 'prefecture_code' => 13, 'address' => '東京都豊島区',
        ], $attributes));
    }

    private function missingForFourDays(Shop $shop): void
    {
        ShopSyncState::create([
            'shop_id' => $shop->id,
            'last_checked_on' => '2026-10-22',
            'consecutive_missing_days' => 4,
        ]);
    }

    private function autoHiddenState(Shop $shop): void
    {
        ShopSyncState::create([
            'shop_id' => $shop->id,
            'last_checked_on' => '2026-10-22',
            'consecutive_missing_days' => 5,
            'hidden_at' => now()->subDay(),
        ]);
    }

    private function fakeSnapshot(array $prefectures = [], int $times = 1): void
    {
        $this->mock(KonamiFacilityScraper::class)->shouldReceive('fetch')->times($times)
            ->andReturn(array_replace(array_fill_keys(range(1, 47), []), $prefectures));
    }
}
