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
}
