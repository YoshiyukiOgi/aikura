<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiStateControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_business_error_when_confirming_already_confirmed_shipment(): void
    {
        [$user, $shipment] = $this->prepareConfirmedShipment();

        $this->actingAs($user)
            ->postJson("/api/v1/shipments/{$shipment->id}/confirm", [
                'reason' => 'duplicate shipment confirmation',
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'business_rule_violation')
            ->assertJsonPath('error.message', "出荷伝票 [{$shipment->id}] は下書き状態でないと確定できません。現在の状態: confirmed");
    }

    public function test_api_returns_business_error_when_confirming_already_confirmed_invoice(): void
    {
        [$user, $shipment] = $this->prepareConfirmedShipment();

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $shipment->customer_id,
            invoiceDate: '2026-07-31',
            shipmentHeaderIds: [$shipment->id],
        ));
        $invoice = app(ConfirmInvoiceService::class)->confirm($invoice);

        $this->actingAs($user)
            ->postJson("/api/v1/billing/invoices/{$invoice->id}/confirm", [
                'reason' => 'duplicate invoice confirmation',
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'business_rule_violation')
            ->assertJsonPath('error.message', "請求書 [{$invoice->id}] は下書き状態でないと確定できません。現在の状態: confirmed");
    }

    /**
     * @return array{0: User, 1: \App\Models\ShipmentHeader}
     */
    private function prepareConfirmedShipment(): array
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->createUser('api-state-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'API-STATE-CUST-001',
            'name' => 'API State Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'API-STATE-SAKE-001',
            'product_type' => 'sake',
            'name' => 'API State Sake',
            'display_name' => 'API State Sake 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
            'is_inventory_managed' => false,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $bottle->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-07-20',
            billingTargetDate: '2026-07-20',
            lines: [
                new CreateDraftShipmentLineData($product->id, '2.0000', $bottle->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        return [$user, $shipment];
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'ASTAPI'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'API State Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'API State User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
