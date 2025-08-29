<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Shop;
use App\Models\UserVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisitApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_requires_auth(): void
    {
        $shop = Shop::factory()->create();

        $this->postJson('/api/visit', ['shop_id' => $shop->id])
             ->assertStatus(401);
    }

    #[Test]
    public function store_creates_visit_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/visit', ['shop_id' => $shop->id])
             ->assertCreated()
             ->assertJson(['message' => 'チェックインが完了しました']);

        $this->assertDatabaseHas('user_visits', [
            'user_id' => $user->id,
            'shop_id' => $shop->id,
        ]);
    }

    #[Test]
    public function store_returns_conflict_if_already_checked_in(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        UserVisit::factory()->create(['user_id' => $user->id, 'shop_id' => $shop->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/visit', ['shop_id' => $shop->id])
             ->assertStatus(409)
             ->assertJson(['message' => 'すでにチェックイン済みです']);
    }

    #[Test]
    public function index_returns_authenticated_users_visits(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        UserVisit::factory()->create(['user_id' => $user->id, 'shop_id' => $shop->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/visited')
             ->assertOk()
             ->assertJsonIsArray()
             ->assertJsonFragment(['name' => $shop->name]);
    }

    #[Test]
    public function public_index_requires_user_query(): void
    {
        $this->getJson('/api/visited-shops')
             ->assertStatus(400)
             ->assertJson(['message' => 'userパラメータが必要です']);
    }

    #[Test]
    public function public_index_returns_visits_for_specified_user(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        UserVisit::factory()->create(['user_id' => $user->id, 'shop_id' => $shop->id]);

        $this->getJson("/api/visited-shops?user={$user->id}")
             ->assertOk()
             ->assertJsonIsArray()
             ->assertJsonFragment([
                 'id'   => $shop->id,
                 'name' => $shop->name,
                 'address' => $shop->address,
             ]);
    }

    // 任意：バリデーション422を抑えておくとより実務的
    #[Test]
    public function store_validates_shop_id_exists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/visit', ['shop_id' => 999999])
             ->assertStatus(422); // exists:shops,id によるバリデーション
    }
}
