<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentDraftException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateDraftShipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_draft_tables_exist(): void
    {
        foreach (['shipment_headers', 'shipment_lines'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] does not exist.");
        }
    }

    public function test_it_creates_draft_shipment_with_lines_and_audit_log(): void
    {
        [$customer, $product, $unit] = $this->prepareDraftData();

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            orderDate: '2026-05-22',
            scheduledShipmentDate: '2026-05-25',
            reason: '出荷予定入力',
            lines: [
                new CreateDraftShipmentLineData(
                    productId: $product->id,
                    quantity: '12.0000',
                    unitId: $unit->id,
                    note: '1ケース相当',
                ),
            ],
        ));

        $this->assertSame('draft', $shipment->status);
        $this->assertStringStartsWith('S-', $shipment->document_number);
        $this->assertSame($customer->id, $shipment->customer_id);
        $this->assertSame($customer->transaction_category_id, $shipment->transaction_category_id);
        $this->assertSame($customer->settlement_receivable_category_id, $shipment->settlement_receivable_category_id);
        $this->assertSame($customer->billing_cycle_id, $shipment->billing_cycle_id);
        $this->assertSame('2026-05-23', $shipment->billing_target_date->toDateString());
        $this->assertCount(1, $shipment->lines);
        $this->assertSame(1, $shipment->lines->first()->line_no);
        $this->assertSame('12.0000', $shipment->lines->first()->quantity);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment.draft_created',
            'target_table' => 'shipment_headers',
            'target_id' => (string) $shipment->id,
            'reason' => '出荷予定入力',
        ]);
    }

    public function test_draft_creation_does_not_require_price_or_create_confirmed_snapshot(): void
    {
        [$customer, $product, $unit] = $this->prepareDraftData();

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '1.0000', $unit->id),
            ],
        ));

        $this->assertSame('draft', $shipment->status);
        $this->assertFalse(Schema::hasColumn('shipment_lines', 'unit_price'));
        $this->assertFalse(Schema::hasColumn('shipment_lines', 'tax_amount'));
        $this->assertFalse(Schema::hasColumn('shipment_lines', 'liquor_tax_amount'));
    }

    public function test_it_rejects_empty_lines(): void
    {
        [$customer] = $this->prepareDraftData();

        $this->expectException(ShipmentDraftException::class);

        app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            lines: [],
        ));
    }

    public function test_it_rejects_inactive_customer(): void
    {
        [$customer, $product, $unit] = $this->prepareDraftData();
        $customer->update(['is_active' => false, 'disabled_at' => now()]);

        $this->expectException(ShipmentDraftException::class);

        app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '1.0000', $unit->id),
            ],
        ));
    }

    public function test_it_rejects_inactive_product(): void
    {
        [$customer, $product, $unit] = $this->prepareDraftData();
        $product->update(['is_sales_available' => false]);

        $this->expectException(ShipmentDraftException::class);

        app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '1.0000', $unit->id),
            ],
        ));
    }

    public function test_it_rejects_non_positive_quantity(): void
    {
        [$customer, $product, $unit] = $this->prepareDraftData();

        $this->expectException(ShipmentDraftException::class);

        app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '0.0000', $unit->id),
            ],
        ));
    }

    public function test_shipment_number_sequence_increments_for_multiple_drafts(): void
    {
        [$customer, $product, $unit] = $this->prepareDraftData();
        $service = app(CreateDraftShipmentService::class);

        $first = $service->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            lines: [new CreateDraftShipmentLineData($product->id, '1.0000', $unit->id)],
        ));

        $second = $service->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            lines: [new CreateDraftShipmentLineData($product->id, '2.0000', $unit->id)],
        ));

        $this->assertNotSame($first->document_number, $second->document_number);
        $this->assertSame(2, ShipmentHeader::count());
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit}
     */
    private function prepareDraftData(): array
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            ShipmentMasterSeeder::class,
        ]);

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'SHIP-CUST-001',
            'name' => '出荷確認酒店',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'SHIP-SAKE-001',
            'product_type' => 'sake',
            'name' => '出荷確認酒',
            'display_name' => '出荷確認酒',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        return [$customer, $product, $unit];
    }
}

