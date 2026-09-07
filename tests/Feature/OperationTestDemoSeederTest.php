<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\ShipmentHeader;
use App\Models\User;
use Database\Seeders\OperationTestDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationTestDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_test_demo_seeder_creates_loginable_users_and_editable_demo_data(): void
    {
        $this->seed(OperationTestDemoSeeder::class);

        $adminRole = Role::query()->where('code', 'admin')->firstOrFail();
        $operator = User::query()->where('email', 'demo.operator@example.test')->firstOrFail();
        $approver = User::query()->where('email', 'demo.approver@example.test')->firstOrFail();

        $this->assertTrue($operator->is_active);
        $this->assertTrue($approver->is_active);
        $this->assertTrue($operator->roles->contains($adminRole));
        $this->assertTrue($approver->roles->contains($adminRole));

        $this->withSession(['_token' => 'test-token'])->post('/login', [
            '_token' => 'test-token',
            'email' => $operator->email,
            'password' => 'password',
        ])->assertRedirect('/sales-orders');

        $this->assertDatabaseHas('sales_orders', [
            'customer_order_number' => 'DEMO-EDIT-001',
            'status' => 'received',
        ]);
        $this->assertDatabaseHas('sales_orders', [
            'customer_order_number' => 'DEMO-BILL-001',
            'status' => 'instructed',
        ]);
        $this->assertTrue(ShipmentHeader::query()->where('status', 'confirmed')->whereNotNull('source_shipment_pick_id')->exists());
        $this->assertTrue(SalesOrder::query()->where('customer_order_number', 'DEMO-ORDER-001')->exists());
    }
}
