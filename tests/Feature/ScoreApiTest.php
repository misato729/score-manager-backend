<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Song;
use App\Models\Score;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScoreApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function score_endpoints_require_numeric_user_path(): void
    {
        foreach (['scores', 'song-scores'] as $resource) {
            $this->getJson("/api/users/{$resource}")->assertMethodNotAllowed();
            $this->getJson("/api/users/not-a-user/{$resource}")->assertNotFound();
        }
    }

    #[Test]
    public function index_returns_scores_for_specified_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $song = Song::factory()->create();
        $score = Score::factory()->create([
            'user_id' => $user->id,
            'song_id' => $song->id,
            'rank'    => 'AA',
            'fc'      => true,
        ]);
        Score::factory()->create([
            'user_id' => $otherUser->id,
            'song_id' => $song->id,
        ]);

        $this->getJson("/api/users/{$user->id}/scores?user={$otherUser->id}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $score->id)
            ->assertJsonPath('0.rank', 'AA')
            ->assertJsonPath('0.fc', true)
            ->assertJsonPath('0.song.id', $song->id);
    }

    #[Test]
    public function user_scores_returns_all_songs_with_only_path_users_scores(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $playedSong = Song::factory()->create();
        $otherUsersSong = Song::factory()->create();
        $unplayedSong = Song::factory()->create();
        $score = Score::factory()->create([
            'user_id' => $user->id,
            'song_id' => $playedSong->id,
            'rank' => 'AA',
            'fc' => true,
            'memo' => '練習中',
        ]);
        Score::factory()->create([
            'user_id' => $otherUser->id,
            'song_id' => $otherUsersSong->id,
            'rank' => 'AAA',
        ]);

        $response = $this->getJson("/api/users/{$user->id}/song-scores?user={$otherUser->id}")
            ->assertOk()
            ->assertJsonCount(3);

        $songs = collect($response->json())->keyBy('song_id');
        $this->assertSame($score->id, $songs[$playedSong->id]['score_id']);
        $this->assertSame('AA', $songs[$playedSong->id]['rank']);
        $this->assertEquals(1, $songs[$playedSong->id]['fc']);
        $this->assertSame('練習中', $songs[$playedSong->id]['memo']);
        foreach ([$otherUsersSong, $unplayedSong] as $song) {
            $this->assertNull($songs[$song->id]['score_id']);
            $this->assertNull($songs[$song->id]['rank']);
        }
        foreach ($songs as $song) {
            $this->assertSame($user->name, $song['user_name']);
        }
    }

    #[Test]
    public function user_scores_returns_not_found_for_unknown_user(): void
    {
        $this->getJson('/api/users/999999/song-scores')
            ->assertNotFound()
            ->assertJson(['error' => 'User not found']);
    }

    #[Test]
    public function store_requires_auth(): void
    {
        // 未認証で叩く → 401
        $user = User::factory()->create();
        $song = Song::factory()->create();

        $payload = [
            'user_id' => $user->id,
            'song_id' => $song->id,
            'rank'    => 'A',
            'fc'      => false,
        ];

        $this->postJson('/api/scores', $payload)
            ->assertStatus(401);
    }

    #[Test]
    public function store_creates_score_with_valid_payload(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $song = Song::factory()->create();

        // 認証して作成 → 201
        Sanctum::actingAs($user);

        $payload = [
            'user_id' => $user->id,
            'song_id' => $song->id,
            'rank'    => 'AAA',
            'fc'      => true,
        ];

        $this->postJson('/api/scores', $payload)
            ->assertCreated()
            ->assertJsonFragment([
                'user_id' => $user->id,
                'song_id' => $song->id,
                'rank'    => 'AAA',
                'fc'      => true,
            ]);

        $this->assertDatabaseHas('scores', [
            'user_id' => $user->id,
            'song_id' => $song->id,
            'rank'    => 'AAA',
            'fc'      => 1,
        ]);
    }

    #[Test]
    public function update_modifies_existing_score(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $song = Song::factory()->create();
        $score = Score::factory()->create([
            'user_id' => $user->id,
            'song_id' => $song->id,
            'rank'    => 'A',
            'fc'      => false,
        ]);

        Sanctum::actingAs($user);

        $payload = ['rank' => 'AA', 'fc' => true];

        $this->putJson("/api/scores/{$score->id}", $payload)
            ->assertOk()
            ->assertJson(['message' => 'Score updated']);

        $this->assertDatabaseHas('scores', [
            'id'   => $score->id,
            'rank' => 'AA',
            'fc'   => 1,
        ]);
    }
}
