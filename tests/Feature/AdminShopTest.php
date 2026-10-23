<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminShopTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_page_shows_common_header_and_sidebar(): void
    {
        $admin = $this->allowedAdmin();

        $this->actingAs($admin)
            ->get('/admin/shops')
            ->assertOk()
            ->assertSee('リフプラ難易度表 管理システム')
            ->assertSee('ログアウト')
            ->assertSee('店舗一覧')
            ->assertSee('楽曲一覧')
            ->assertSee('設置店舗一覧')
            ->assertDontSee('緯度')
            ->assertDontSee('経度');
    }

    #[Test]
    public function create_page_is_visible_to_allowed_admin(): void
    {
        $admin = $this->allowedAdmin();

        $this->actingAs($admin)
            ->get('/admin/shops/create')
            ->assertOk()
            ->assertSee('設置店舗新規登録');
    }

    #[Test]
    public function edit_page_shows_common_header_and_sidebar(): void
    {
        $admin = $this->allowedAdmin();
        $shop = Shop::factory()->create([
            'name' => 'タイトーステーション大宮店',
            'address' => '埼玉県さいたま市大宮区大門町1-1',
        ]);

        $response = $this->actingAs($admin)
            ->get("/admin/shops/{$shop->id}/edit")
            ->assertOk()
            ->assertSee('リフプラ難易度表 管理システム')
            ->assertSee('店舗一覧')
            ->assertSee('楽曲一覧')
            ->assertSee('設置店舗編集')
            ->assertSee('タイトーステーション大宮店');

        $this->assertSame(1, substr_count($response->getContent(), 'ログアウト'));
    }

    #[Test]
    public function admin_can_store_new_shop(): void
    {
        $admin = $this->allowedAdmin();

        $this->actingAs($admin)
            ->post('/admin/shops', [
                'name' => 'タイトーステーション大宮店',
                'address' => '埼玉県さいたま市大宮区大門町1-1',
                'lat' => '35.905813',
                'lng' => '139.625705',
                'price' => '120',
                'number_of_machine' => '1',
                'description' => '大宮駅東口徒歩1分',
            ])
            ->assertRedirect(route('shops.index'));

        $this->assertDatabaseHas('shops', [
            'name' => 'タイトーステーション大宮店',
            'address' => '埼玉県さいたま市大宮区大門町1-1',
            'prefecture_code' => 11,
            'price' => 120,
            'number_of_machine' => 1,
            'description' => '大宮駅東口徒歩1分',
            'is_deleted' => false,
        ]);
    }

    #[Test]
    public function changing_price_of_a_published_shop_preserves_its_missing_days(): void
    {
        $shop = $this->shopWithSyncState();

        $this->actingAs($this->allowedAdmin())
            ->put(route('shops.update', $shop), $this->shopInput($shop, ['price' => 150]))
            ->assertRedirect(route('shops.index'));

        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'price' => 150, 'is_deleted' => false]);
        $this->assertDatabaseHas('shop_sync_states', [
            'shop_id' => $shop->id,
            'consecutive_missing_days' => 3,
            'hidden_at' => null,
        ]);
        $this->assertSame('2026-10-22', $shop->syncState()->firstOrFail()->last_checked_on->toDateString());
    }

    #[Test]
    #[DataProvider('identityChanges')]
    public function changing_shop_identity_resets_its_sync_state(array $changes): void
    {
        $shop = $this->shopWithSyncState();

        $this->actingAs($this->allowedAdmin())
            ->put(route('shops.update', $shop), $this->shopInput($shop, $changes))
            ->assertRedirect(route('shops.index'));

        $this->assertDatabaseMissing('shop_sync_states', ['shop_id' => $shop->id]);
        $this->assertDatabaseHas('shops', ['id' => $shop->id, ...$changes]);
    }

    public static function identityChanges(): array
    {
        return [
            'name' => [['name' => '変更後の店舗名']],
            'prefecture' => [['prefecture_code' => 13]],
            'address' => [['address' => '埼玉県さいたま市大宮区大門町2-2']],
        ];
    }

    #[Test]
    public function manually_hiding_a_published_shop_clears_its_sync_state(): void
    {
        $shop = $this->shopWithSyncState();

        $this->actingAs($this->allowedAdmin())
            ->put(route('shops.update', $shop), $this->shopInput($shop, ['is_deleted' => '1']))
            ->assertRedirect(route('shops.index'));

        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'is_deleted' => true]);
        $this->assertDatabaseMissing('shop_sync_states', ['shop_id' => $shop->id]);
    }

    #[Test]
    public function saving_an_automatically_hidden_shop_as_hidden_takes_manual_ownership(): void
    {
        $shop = $this->shopWithSyncState(true);

        $this->actingAs($this->allowedAdmin())
            ->put(route('shops.update', $shop), $this->shopInput($shop, ['is_deleted' => '1', 'price' => 150]))
            ->assertRedirect(route('shops.index'));

        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'is_deleted' => true, 'price' => 150]);
        $this->assertDatabaseMissing('shop_sync_states', ['shop_id' => $shop->id]);
    }

    #[Test]
    public function manually_republishing_a_shop_clears_its_automatic_hidden_state(): void
    {
        $shop = $this->shopWithSyncState(true);

        $this->actingAs($this->allowedAdmin())
            ->put(route('shops.update', $shop), $this->shopInput($shop))
            ->assertRedirect(route('shops.index'));

        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'is_deleted' => false]);
        $this->assertDatabaseMissing('shop_sync_states', ['shop_id' => $shop->id]);
    }

    private function shopWithSyncState(bool $hidden = false): Shop
    {
        $shop = Shop::factory()->create([
            'name' => 'タイトーステーション大宮店',
            'address' => '埼玉県さいたま市大宮区大門町1-1',
            'prefecture_code' => 11,
            'is_deleted' => $hidden,
        ]);
        $shop->syncState()->create([
            'last_checked_on' => '2026-10-22',
            'consecutive_missing_days' => $hidden ? 5 : 3,
            'hidden_at' => $hidden ? '2026-10-22 22:30:00' : null,
        ]);

        return $shop;
    }

    private function shopInput(Shop $shop, array $changes = []): array
    {
        return array_replace($shop->only([
            'name',
            'address',
            'prefecture_code',
            'lat',
            'lng',
            'price',
            'number_of_machine',
            'description',
        ]), $changes);
    }

    private function allowedAdmin(): User
    {
        return User::factory()->create(['id' => (int) env('ALLOWED_USER_ID', 10)]);
    }
}
