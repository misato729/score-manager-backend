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
    public function index_requires_user_query(): void
    {
        // /api/scores は user クエリが必須 → 400
        $this->getJson('/api/scores')
            ->assertStatus(400)
            ->assertJson(['error' => 'User ID is required']);
    }

    #[Test]
    public function index_returns_scores_for_specified_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $song = Song::factory()->create();
        $score = Score::factory()->create([
            'user_id' => $user->id,
            'song_id' => $song->id,
            'rank'    => 'AA',
            'fc'      => true,
        ]);

        $this->getJson("/api/scores?user={$user->id}")
            ->assertOk()
            ->assertJsonCount(1) // 1件だけ作っていれば
            ->assertJsonPath('0.id', $score->id)
            ->assertJsonPath('0.rank', 'AA')
            ->assertJsonPath('0.fc', true)
            ->assertJsonPath('0.song.id', $song->id);
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
