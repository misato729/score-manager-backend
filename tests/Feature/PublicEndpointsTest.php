<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PublicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function health_returns_200(): void
    {
        $this->getJson('/api/health')->assertStatus(200);
    }
}
