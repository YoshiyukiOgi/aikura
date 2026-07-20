<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_v1_root_returns_standard_json_response(): void
    {
        $this->getJson('/api/v1')
            ->assertOk()
            ->assertJsonPath('data.name', 'aikura')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.version', 'v1');
    }

    public function test_api_route_catalog_is_served_by_controller_service_boundary(): void
    {
        $this->getJson('/api/v1/routes')
            ->assertOk()
            ->assertJsonPath('data.routes.0.module', 'sales_orders')
            ->assertJsonPath('data.routes.0.stage', '8-3')
            ->assertJsonPath('data.routes.3.module', 'shipments')
            ->assertJsonPath('data.routes.6.module', 'monthly');
    }

    public function test_named_api_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('api.v1.index'));
        $this->assertTrue(Route::has('api.v1.routes'));
    }
}
