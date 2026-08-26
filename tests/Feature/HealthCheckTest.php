<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_home_returns_ok(): void
    {
        $response = $this->getJson('/api/v1');

        $response->assertOk()
            ->assertJsonPath('data.name', 'aikura')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.version', 'v1');
    }
}
