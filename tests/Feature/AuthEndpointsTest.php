<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AuthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function post_scores_requires_auth_401(): void
    {
        // 認証なしで保護APIにPOST → 401
        $response = $this->postJson('/api/scores', [
            // ペイロードは空でもOK（authミドルウェアが先に弾く）
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function get_user_returns_current_user_when_authenticated(): void
    {
        // ユーザーを作成してSanctumでログインした体にする
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
                 ->assertJsonPath('id', $user->id);
    }
}
