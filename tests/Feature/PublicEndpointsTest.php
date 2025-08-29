<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PublicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function scores_index_returns_200(): void
    {
        // /api/scores は公開API
        $response = $this->getJson('/api/scores');
        $response->assertStatus(200);
    }

    /** @test */
    public function shops_index_returns_200(): void
    {
        // /api/shops は公開API
        $response = $this->getJson('/api/shops');
        $response->assertStatus(200);
    }
}
