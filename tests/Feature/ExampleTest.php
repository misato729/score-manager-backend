<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_guests_to_login(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_home_forbids_authenticated_users_without_permission(): void
    {
        $this->actingAs(User::factory()->create(['id' => (int) env('ALLOWED_USER_ID', 10) + 1]))
            ->get('/')
            ->assertForbidden()
            ->assertDontSee('Homeへ戻る')
            ->assertDontSee('>Home<', false);
    }

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this
            ->actingAs(User::factory()->create(['id' => (int) env('ALLOWED_USER_ID', 10)]))
            ->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('リフプラ難易度表 管理システム')
            ->assertSee('店舗一覧')
            ->assertSee('楽曲一覧')
            ->assertSee('ログアウト')
            ->assertDontSee('ようこそ')
            ->assertDontSee('クイックアクション');
    }
}
