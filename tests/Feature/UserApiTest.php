<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_endpoint_requires_auth(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    #[Test]
    public function user_endpoint_returns_current_user_when_authenticated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    #[Test]
    public function update_target_requires_auth_and_updates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = ['target' => 'AAA']; // 仕様に合わせて変更

        $this->putJson('/api/users/target', $payload)
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'target' => 'AAA',
        ]);
    }

    #[Test]
    public function protected_test_user_cannot_delete_their_account(): void
    {
        $user = User::factory()->create(['id' => 8]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/users/8')
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account cannot be deleted',
            ]);

        $this->assertDatabaseHas('users', ['id' => 8]);
    }

    #[Test]
    public function regular_user_can_delete_their_account(): void
    {
        $user = User::factory()->create(['id' => 9]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/users/9')
            ->assertOk()
            ->assertJson([
                'message' => 'Account deleted',
            ]);

        $this->assertDatabaseMissing('users', ['id' => 9]);
    }
}
