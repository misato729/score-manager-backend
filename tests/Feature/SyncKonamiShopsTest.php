<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use App\Models\UserVisit;
use App\Services\KonamiFacilityScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SyncKonamiShopsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_missing_public_shops_are_hidden_and_visits_are_preserved(): void
    {
        $remaining = $this->shop('残っている店舗');
        $missing = $this->shop('撤去済み店舗');
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
        $this->assertDatabaseCount('shops', 4);
        $this->assertDatabaseHas('user_visits', ['id' => $visit->id, 'shop_id' => $missing->id]);
        $this->getJson('/api/shops')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $remaining->id);
        $this->getJson("/api/users/{$user->id}/visited-shops")
            ->assertOk()->assertJsonPath('visited_shops.0.id', $missing->id);
    }

    #[Test]
    public function an_empty_prefecture_hides_its_public_shops(): void
    {
        $shop = $this->shop('最後の設置店舗');
        $this->fakeSnapshot([]);

        $this->artisan('shops:sync-konami')->expectsOutput('1件を非公開にしました。')->assertSuccessful();

        $this->assertTrue((bool) $shop->fresh()->is_deleted);
    }

    #[Test]
    public function same_name_in_another_prefecture_does_not_keep_a_missing_shop_public(): void
    {
        $tokyo = $this->shop('同名店舗');
        $osaka = $this->shop('同名店舗', ['prefecture_code' => 27, 'address' => '大阪府大阪市']);
        $this->fakeSnapshot([27 => ['同名店舗']]);

        $this->artisan('shops:sync-konami')->assertSuccessful();

        $this->assertTrue((bool) $tokyo->fresh()->is_deleted);
        $this->assertFalse((bool) $osaka->fresh()->is_deleted);
    }

    #[Test]
    public function dry_run_lists_candidates_without_changing_any_shop(): void
    {
        $shop = $this->shop('確認用店舗');
        $before = $shop->fresh()->getAttributes();
        $this->fakeSnapshot([]);

        $this->artisan('shops:sync-konami --dry-run')
            ->expectsTable(['ID', '都道府県', '非公開化対象の店舗名'], [[$shop->id, '東京都', $shop->name]])
            ->expectsOutput('確認のみ: 非公開化候補 1件。店舗情報は変更していません。')
            ->assertSuccessful();

        $this->assertSame($before, $shop->fresh()->getAttributes());
    }

    #[Test]
    public function source_failure_returns_a_failure_without_modifying_shops(): void
    {
        $shop = $this->shop('公開維持店舗');
        $this->mock(KonamiFacilityScraper::class)->shouldReceive('fetch')->once()
            ->andThrow(new RuntimeException('検索結果の件数が一致しません。'));

        $this->artisan('shops:sync-konami')
            ->expectsOutput('公式検索の取得に失敗しました。店舗情報は変更していません。')
            ->assertFailed();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
    }

    #[Test]
    public function missing_prefecture_codes_fall_back_to_address_and_unknown_shops_are_skipped(): void
    {
        $known = $this->shop('住所で判定する店舗', ['prefecture_code' => null]);
        $unknown = $this->shop('判定できない店舗', ['prefecture_code' => null, 'address' => '住所調査中']);
        $this->fakeSnapshot([]);

        $this->artisan('shops:sync-konami')
            ->expectsOutput('都道府県または店舗名を判定できないため、1件をスキップしました。')
            ->assertSuccessful();

        $this->assertTrue((bool) $known->fresh()->is_deleted);
        $this->assertFalse((bool) $unknown->fresh()->is_deleted);
    }

    #[Test]
    public function shop_matching_normalizes_full_width_ascii_and_whitespace(): void
    {
        $shop = $this->shop('ＧＡＭＥ　センター 池袋');
        $this->fakeSnapshot([13 => ["GAME センター\n池袋"]]);

        $this->artisan('shops:sync-konami')->expectsOutput('0件を非公開にしました。')->assertSuccessful();

        $this->assertFalse((bool) $shop->fresh()->is_deleted);
    }

    #[Test]
    public function rerunning_sync_does_not_modify_hidden_shops_again(): void
    {
        $shop = $this->shop('撤去済み店舗');
        $this->mock(KonamiFacilityScraper::class)->shouldReceive('fetch')->twice()
            ->andReturn(array_fill_keys(range(1, 47), []));

        $this->artisan('shops:sync-konami')->expectsOutput('1件を非公開にしました。')->assertSuccessful();
        $before = $shop->fresh()->getAttributes();
        $this->travel(1)->hour();
        $this->artisan('shops:sync-konami')->expectsOutput('0件を非公開にしました。')->assertSuccessful();

        $this->assertSame($before, $shop->fresh()->getAttributes());
    }

    private function shop(string $name, array $attributes = []): Shop
    {
        return Shop::factory()->create(array_merge([
            'name' => $name, 'prefecture_code' => 13, 'address' => '東京都豊島区',
        ], $attributes));
    }

    private function fakeSnapshot(array $prefectures): void
    {
        $this->mock(KonamiFacilityScraper::class)->shouldReceive('fetch')->once()
            ->andReturn(array_replace(array_fill_keys(range(1, 47), []), $prefectures));
    }
}
