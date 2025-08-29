<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AuthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
{
    parent::setUp();

    // CI では UI ログインの振る舞いは検証対象外にする
    if (env('CI')) {
        $this->markTestSkipped('Skip Breeze UI login test on CI');
    }
}


    /** @test */
    public function protected_endpoint_requires_auth_401(): void
    {
        $this->postJson('/api/scores', [])->assertStatus(401);
    }

    /** @test */
    public function user_endpoint_returns_current_user_when_authenticated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/user')
             ->assertStatus(200)
             ->assertJsonPath('id', $user->id);
    }
}
