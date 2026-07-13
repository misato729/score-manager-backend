<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('設置店舗一覧');
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

    private function allowedAdmin(): User
    {
        return User::factory()->create(['id' => (int) env('ALLOWED_USER_ID', 10)]);
    }
}
