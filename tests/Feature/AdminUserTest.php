<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function allowed_admin_can_view_user_details_without_credentials(): void
    {
        $admin = $this->allowedAdmin();
        $user = User::factory()->create([
            'id' => $admin->id + 1,
            'name' => '一覧表示ユーザー',
            'email' => 'listed-user@example.test',
            'password' => 'private-password-value',
            'remember_token' => 'private-remember-token',
            'created_at' => '2026-01-01 15:30:00',
        ]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('リフプラ難易度表 管理システム')
            ->assertSee('店舗一覧')
            ->assertSee('楽曲一覧')
            ->assertSee('ユーザー一覧')
            ->assertSee('一覧表示ユーザー')
            ->assertSee('listed-user@example.test')
            ->assertSee('2026-01-02 00:30')
            ->assertDontSee($user->getRawOriginal('password'), false)
            ->assertDontSee('private-remember-token')
            ->assertViewHas('users', function (LengthAwarePaginator $users) use ($user): bool {
                $listed = $users->getCollection()->firstWhere('id', $user->id);
                $this->assertNotNull($listed);
                $this->assertArrayNotHasKey('password', $listed->getAttributes());
                $this->assertArrayNotHasKey('remember_token', $listed->getAttributes());

                return true;
            });
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get(route('users.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function ordinary_user_cannot_view_the_user_list(): void
    {
        $user = User::factory()->create(['id' => (int) env('ALLOWED_USER_ID', 10) + 1]);

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    #[Test]
    #[DataProvider('searchKeywords')]
    public function admin_can_search_names_and_email_addresses_without_case_sensitivity(string $keyword): void
    {
        $admin = $this->allowedAdmin();
        $matched = User::factory()->create([
            'id' => $admin->id + 1,
            'name' => 'MixedCase Player',
            'email' => 'search-user@Example.test',
        ]);
        User::factory()->create([
            'id' => $admin->id + 2,
            'name' => 'Unrelated Person',
            'email' => 'unrelated@elsewhere.test',
        ]);

        $this->actingAs($admin)
            ->get(route('users.index', ['keyword' => $keyword]))
            ->assertOk()
            ->assertSee('MixedCase Player')
            ->assertDontSee('Unrelated Person')
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 1 && $users->getCollection()->modelKeys() === [$matched->id]
            );
    }

    public static function searchKeywords(): array
    {
        return [
            'name' => ['MixedCase'],
            'name with different case' => ['mixedcase'],
            'email' => ['search-user@Example.test'],
            'email with different case' => ['SEARCH-USER@EXAMPLE.TEST'],
        ];
    }

    #[Test]
    public function zero_is_a_search_keyword(): void
    {
        $admin = $this->allowedAdmin();
        $matched = User::factory()->create(['id' => $admin->id + 1, 'name' => 'Player 0', 'email' => 'zero@example.test']);
        User::factory()->create(['id' => $admin->id + 2, 'name' => 'Other Player', 'email' => 'other@example.test']);

        $this->actingAs($admin)
            ->get(route('users.index', ['keyword' => '0']))
            ->assertOk()
            ->assertSee('Player 0')
            ->assertDontSee('Other Player')
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 1 && $users->getCollection()->modelKeys() === [$matched->id]
            );
    }

    #[Test]
    public function results_are_paginated_in_id_order_and_preserve_the_keyword(): void
    {
        $admin = $this->allowedAdmin();
        $firstId = $admin->id + 100;
        foreach (range($firstId + 50, $firstId) as $id) {
            User::factory()->create([
                'id' => $id,
                'name' => "Pageable Player {$id}",
                'email' => "pageable-{$id}@example.test",
            ]);
        }

        $this->actingAs($admin)
            ->get(route('users.index', ['keyword' => 'Pageable', 'unused' => 'discard-me']))
            ->assertOk()
            ->assertSee('keyword=Pageable', false)
            ->assertDontSee('discard-me')
            ->assertViewHas('users', function (LengthAwarePaginator $users) use ($firstId): bool {
                $this->assertSame(51, $users->total());
                $this->assertSame(50, $users->perPage());
                $this->assertSame(range($firstId, $firstId + 49), $users->getCollection()->modelKeys());
                $this->assertStringContainsString('keyword=Pageable', $users->nextPageUrl());
                $this->assertStringNotContainsString('unused', $users->nextPageUrl());

                return true;
            });

        $this->get(route('users.index', ['keyword' => 'Pageable', 'page' => 2]))
            ->assertOk()
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->currentPage() === 2 && $users->getCollection()->modelKeys() === [$firstId + 50]
            );
    }

    #[Test]
    public function search_with_no_matching_users_shows_an_empty_state(): void
    {
        $this->actingAs($this->allowedAdmin())
            ->get(route('users.index', ['keyword' => 'no-matching-users']))
            ->assertOk()
            ->assertSee('条件に一致するユーザーがいません')
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 0);
    }

    #[Test]
    public function user_names_and_email_addresses_are_escaped(): void
    {
        $admin = $this->allowedAdmin();
        $name = '<script>alert("name")</script>';
        $email = '<script>alert("email")</script>@example.test';
        User::factory()->create(['id' => $admin->id + 1, 'name' => $name, 'email' => $email]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee($name)
            ->assertSee($email)
            ->assertDontSee($name, false)
            ->assertDontSee($email, false);
    }

    #[Test]
    #[DataProvider('invalidKeywords')]
    public function invalid_search_keywords_are_rejected(mixed $keyword): void
    {
        $this->actingAs($this->allowedAdmin())
            ->getJson(route('users.index', ['keyword' => $keyword]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('keyword');
    }

    public static function invalidKeywords(): array
    {
        return [
            'array' => [['unexpected']],
            'too long' => [str_repeat('a', 256)],
        ];
    }

    private function allowedAdmin(): User
    {
        return User::factory()->create([
            'id' => (int) env('ALLOWED_USER_ID', 10),
            'name' => 'Administrator',
            'email' => 'admin@example.test',
        ]);
    }
}
