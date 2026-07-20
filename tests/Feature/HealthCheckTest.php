<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_home_returns_ok(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertJson([
                'name' => 'AI蔵',
                'status' => 'ok',
            ]);
    }
}

