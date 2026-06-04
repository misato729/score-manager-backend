<?php

namespace Tests\Feature;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_returns_200_and_array(): void
    {
        // 0件でも200＆配列で返ること
        $this->getJson('/api/shops')
        ->assertOk()
        ->assertJsonIsArray();
    }

    #[Test]
    public function index_returns_shops_when_existing(): void
    {
        Shop::factory()->create(['name' => 'Test Arcade']);

        $this->getJson('/api/shops')
             ->assertOk()
             ->assertJsonFragment(['name' => 'Test Arcade']);
    }

    #[Test]
    public function index_orders_shops_by_prefecture_code_then_id(): void
    {
        $okinawa = Shop::factory()->create([
            'name' => 'モーリーファンタジー那覇',
            'address' => '沖縄県那覇市金城5-10-2',
            'prefecture_code' => 47,
        ]);
        $saitama = Shop::factory()->create([
            'name' => 'タイトーステーション大宮店',
            'address' => '埼玉県さいたま市大宮区',
            'prefecture_code' => 11,
        ]);

        $this->getJson('/api/shops')
             ->assertOk()
             ->assertJsonPath('0.id', $saitama->id)
             ->assertJsonPath('1.id', $okinawa->id);
    }
}
