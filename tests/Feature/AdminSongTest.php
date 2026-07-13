<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSongTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_page_is_visible_to_allowed_admin(): void
    {
        $admin = $this->allowedAdmin();
        Song::factory()->create([
            'title' => 'FLOWER',
            'jiriki_rank' => 'S+',
        ]);

        $this->actingAs($admin)
            ->get('/admin/songs')
            ->assertOk()
            ->assertSee('リフプラ難易度表 管理システム')
            ->assertSee('ログアウト')
            ->assertSee('店舗一覧')
            ->assertSee('楽曲一覧')
            ->assertSee('FLOWER')
            ->assertSee('S+');
    }

    #[Test]
    public function edit_page_is_visible_to_allowed_admin(): void
    {
        $admin = $this->allowedAdmin();
        $song = Song::factory()->create([
            'title' => '灼熱Beach Side Bunny',
            'jiriki_rank' => 'A',
        ]);

        $this->actingAs($admin)
            ->get("/admin/songs/{$song->id}/edit")
            ->assertOk()
            ->assertSee('楽曲編集')
            ->assertSee('灼熱Beach Side Bunny')
            ->assertSee('<select id="jiriki_rank" name="jiriki_rank" required>', false)
            ->assertSee('value="S+"', false)
            ->assertSee('value="A" selected', false)
            ->assertSee('value="F"', false);
    }

    #[Test]
    public function admin_can_update_song(): void
    {
        $admin = $this->allowedAdmin();
        $song = Song::factory()->create([
            'title' => '旧曲名',
            'jiriki_rank' => 'B',
        ]);

        $this->actingAs($admin)
            ->put("/admin/songs/{$song->id}", [
                'title' => '新曲名',
                'jiriki_rank' => 'A+',
            ])
            ->assertRedirect(route('songs.index'));

        $this->assertDatabaseHas('songs', [
            'id' => $song->id,
            'title' => '新曲名',
            'jiriki_rank' => 'A+',
        ]);
    }

    #[Test]
    public function admin_cannot_update_song_with_invalid_jiriki_rank(): void
    {
        $admin = $this->allowedAdmin();
        $song = Song::factory()->create([
            'title' => '旧曲名',
            'jiriki_rank' => 'B',
        ]);

        $this->actingAs($admin)
            ->from("/admin/songs/{$song->id}/edit")
            ->put("/admin/songs/{$song->id}", [
                'title' => '新曲名',
                'jiriki_rank' => 'AA',
            ])
            ->assertRedirect("/admin/songs/{$song->id}/edit")
            ->assertSessionHasErrors('jiriki_rank');

        $this->assertDatabaseHas('songs', [
            'id' => $song->id,
            'title' => '旧曲名',
            'jiriki_rank' => 'B',
        ]);
    }

    #[Test]
    public function non_allowed_user_cannot_access_song_admin(): void
    {
        $user = User::factory()->create(['id' => (int) env('ALLOWED_USER_ID', 10) + 1]);

        $this->actingAs($user)
            ->get('/admin/songs')
            ->assertForbidden();
    }

    private function allowedAdmin(): User
    {
        return User::factory()->create(['id' => (int) env('ALLOWED_USER_ID', 10)]);
    }
}
