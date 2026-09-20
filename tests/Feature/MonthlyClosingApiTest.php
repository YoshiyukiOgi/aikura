<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLine;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Billing\CreatePaymentScheduleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyClosingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_create_confirm_and_close_monthly_balances(): void
    {
        [$user] = $this->prepareData();

        $stockDraft = $this->actingAs($user)
            ->postJson('/api/v1/monthly-closing/stock-balances', [
                'year' => 2026,
                'month' => 6,
                'reason' => 'api stock monthly draft',
            ])
            ->assertCreated()
            ->assertJsonPath('data.stock_lot_monthly_balances.0.status', 'draft')
            ->assertJsonPath('data.stock_lot_monthly_balances.0.closing_quantity', '5.0000');

        $stockBalanceId = $stockDraft->json('data.stock_lot_monthly_balances.0.id');

        $this->actingAs($user)
            ->postJson('/api/v1/monthly-closing/stock-balances/2026/6/confirm', [
                'reason' => 'api stock monthly confirm',
            ])
            ->assertOk()
            ->assertJsonPath('data.stock_lot_monthly_balances.0.status', 'confirmed');

        $this->actingAs($user)
            ->getJson('/api/v1/monthly-closing/stock-balances?year=2026&month=6')
            ->assertOk()
            ->assertJsonPath('data.stock_lot_monthly_balances.0.id', $stockBalanceId);

        $receivableDraft = $this->actingAs($user)
            ->postJson('/api/v1/monthly-closing/receivable-balances', [
                'year' => 2026,
                'month' => 6,
                'reason' => 'api receivable monthly draft',
            ])
            ->assertCreated()
            ->assertJsonPath('data.receivable_monthly_balances.0.status', 'draft')
            ->assertJsonPath('data.receivable_monthly_balances.0.scheduled_amount', '3300.00')
            ->assertJsonPath('data.receivable_monthly_balances.0.outstanding_amount', '3300.00');

        $receivableBalanceId = $receivableDraft->json('data.receivable_monthly_balances.0.id');

        $this->actingAs($user)
            ->postJson('/api/v1/monthly-closing/receivable-balances/2026/6/confirm', [
                'reason' => 'api receivable monthly confirm',
            ])
            ->assertOk()
            ->assertJsonPath('data.receivable_monthly_balances.0.status', 'confirmed');

        $this->actingAs($user)
            ->postJson('/api/v1/monthly-closing/receivable-balances/2026/6/close', [
                'reason' => 'api receivable monthly close',
            ])
            ->assertOk()
            ->assertJsonPath('data.receivable_monthly_balances.0.status', 'closed');

        $this->actingAs($user)
            ->getJson('/api/v1/monthly-closing/receivable-balances?year=2026&month=6')
            ->assertOk()
            ->assertJsonPath('data.receivable_monthly_balances.0.id', $receivableBalanceId)
            ->assertJsonPath('data.receivable_monthly_balances.0.status', 'closed');
    }

    public function test_user_without_monthly_closing_execute_permission_cannot_create_stock_balances(): void
    {
        $this->prepareData();
        $user = $this->createUser('limited-monthly-closing@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/monthly-closing/stock-balances', [
                'year' => 2026,
                'month' => 6,
                'reason' => 'api stock monthly draft',
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', 'monthly_closing.execute');
    }

    public function test_monthly_closing_api_validates_required_year(): void
    {
        [$user] = $this->prepareData();

        $this->actingAs($user)
            ->postJson('/api/v1/monthly-closing/receivable-balances', [
                'month' => 6,
                'reason' => 'api receivable monthly draft',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    /**
     * @return array{0: User}
     */
    private function prepareData(): array
    {
        $this->seed(DatabaseSeeder::class);
        AppSetting::setValue('operational_start_date', '2026-06-01');

        $user = $this->createUser('monthly-closing-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $stockLot = ProductionLot::create([
            'lot_code' => 'API-MONTHLY-LOT-001',
            'display_name' => 'API Monthly Stock Lot',
            'status' => 'active',
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'is_active' => true,
        ]);

        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'production_receipt',
            'movement_date' => '2026-06-10',
            'production_lot_id' => $stockLot->id,
            'lot_code' => $stockLot->lot_code,
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'quantity' => '5.0000',
            'confirmed_at' => now(),
        ]);

        $this->createReceivableSource($bottle);

        return [$user];
    }

    private function createReceivableSource(Unit $unit): void
    {
        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'API-MONTHLY-AR-001',
            'name' => 'API Monthly Receivable Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'API-MONTHLY-AR-SAKE-001',
            'product_type' => 'sake',
            'name' => 'API Monthly Receivable Sake',
            'display_name' => 'API Monthly Receivable Sake',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        $shipment = ShipmentHeader::create([
            'document_number' => 'API-MONTHLY-AR-SHIP-001', 'status' => 'confirmed', 'customer_id' => $customer->id,
            'transaction_category_id' => $customer->transaction_category_id, 'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'billing_cycle_id' => $customer->billing_cycle_id, 'document_date' => '2026-06-15', 'billing_target_date' => '2026-06-15',
        ]);
        ShipmentLine::create([
            'shipment_header_id' => $shipment->id, 'line_no' => 1, 'product_id' => $product->id, 'quantity' => '2.0000', 'unit_id' => $unit->id,
            'confirmed_product_code' => $product->product_code, 'confirmed_product_name' => $product->name, 'confirmed_display_name' => $product->display_name,
            'confirmed_product_type' => $product->product_type, 'confirmed_unit_code' => $unit->code, 'confirmed_unit_name' => $unit->name,
            'confirmed_quantity' => '2.0000', 'confirmed_unit_price' => '1500.0000', 'confirmed_consumption_tax_rate' => '0.1000', 'confirmed_at' => now(),
        ]);

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-06-30',
            dueDate: '2026-07-31',
            shipmentHeaderIds: [$shipment->id],
        ));
        $invoice = app(ConfirmInvoiceService::class)->confirm($invoice);

        app(CreatePaymentScheduleService::class)->create($invoice);
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'MCLAPI'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Monthly Closing API Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Monthly Closing API User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
