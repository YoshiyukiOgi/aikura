<?php

namespace Tests\Feature;

use App\Services\RemoveDemoData;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCustomerSeeder;
use Database\Seeders\DemoProductSeeder;
use Database\Seeders\OperationTestDemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemoveDemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_explicit_demo_masters_and_inventory_without_touching_standard_masters(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoCustomerSeeder::class);
        $this->seed(DemoProductSeeder::class);
        $this->seed(OperationTestDemoUserSeeder::class);

        $service = app(RemoveDemoData::class);
        $before = $service->summary();
        $removed = $service->remove();
        $after = $service->summary();

        $this->assertSame(29, $before['customers']);
        $this->assertSame(29, $before['products']);
        $this->assertSame(29, $before['production_lots']);
        $this->assertSame(2, $before['users']);
        $this->assertSame($before, $removed);
        $this->assertSame(0, array_sum($after));
        $this->assertSame(0, DB::table('customers')->where('customer_code', 'like', 'DEMO-%')->count());
        $this->assertSame(0, DB::table('products')->where('product_code', 'like', 'DEMO-%')->count());
        $this->assertGreaterThan(0, DB::table('transaction_categories')->count());
        $this->assertGreaterThan(0, DB::table('units')->count());
    }
}
