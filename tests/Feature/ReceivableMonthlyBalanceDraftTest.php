<?php

namespace Tests\Feature;

use App\Exceptions\Billing\ReceivableMonthlyBalanceDraftException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PaymentSchedule;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ReceivableMonthlyBalance;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Billing\CancelPaymentService;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Billing\CreatePaymentScheduleService;
use App\Services\Billing\CreateReceivableMonthlyBalanceDraftService;
use App\Services\Billing\RegisterPaymentService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReceivableMonthlyBalanceDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_receivable_monthly_balances_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('receivable_monthly_balances'));

        foreach ([
            'status',
            'year',
            'month',
            'period_start',
            'period_end',
            'customer_id',
            'customer_code',
            'customer_name',
            'scheduled_amount',
            'received_amount',
            'outstanding_amount',
            'open_schedule_count',
            'partial_schedule_count',
            'closed_schedule_count',
            'calculated_at',
            'confirmed_at',
            'closed_at',
            'reason',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('receivable_monthly_balances', $column),
                "Column [receivable_monthly_balances.{$column}] does not exist.",
            );
        }
    }

    public function test_it_creates_monthly_balance_draft_as_of_period_end(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('AR-DRAFT-CUST-001');

        $partial = $this->createPaymentSchedule($customer, $product, $unit, '2.0000', '2026-06-01');
        $open = $this->createPaymentSchedule($customer, $product, $unit, '1.0000', '2026-06-15');
        $future = $this->createPaymentSchedule($customer, $product, $unit, '5.0000', '2026-07-01');

        app(RegisterPaymentService::class)->register($partial, '1000.00', '2026-06-20');
        app(RegisterPaymentService::class)->register($partial->refresh(), '2300.00', '2026-07-05');
        app(RegisterPaymentService::class)->register($future, '8250.00', '2026-07-31');

        $balances = app(CreateReceivableMonthlyBalanceDraftService::class)
            ->create(2026, 6, 'monthly receivable draft');

        $this->assertCount(1, $balances);
        $balance = $balances->first();

        $this->assertSame('draft', $balance->status);
        $this->assertSame(2026, $balance->year);
        $this->assertSame(6, $balance->month);
        $this->assertSame('2026-06-01', $balance->period_start->toDateString());
        $this->assertSame('2026-06-30', $balance->period_end->toDateString());
        $this->assertSame($customer->id, $balance->customer_id);
        $this->assertSame('AR-DRAFT-CUST-001', $balance->customer_code);
        $this->assertSame('4950.00', $balance->scheduled_amount);
        $this->assertSame('1000.00', $balance->received_amount);
        $this->assertSame('3950.00', $balance->outstanding_amount);
        $this->assertSame(1, $balance->open_schedule_count);
        $this->assertSame(1, $balance->partial_schedule_count);
        $this->assertSame(0, $balance->closed_schedule_count);
        $this->assertSame('monthly receivable draft', $balance->reason);
        $this->assertNotNull($balance->calculated_at);
    }

    public function test_it_excludes_cancelled_payments_from_period_received_amount(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('AR-CANCEL-CUST-001');

        $schedule = $this->createPaymentSchedule($customer, $product, $unit, '1.0000', '2026-06-01');
        $payment = app(RegisterPaymentService::class)->register($schedule, '1650.00', '2026-06-20');
        app(CancelPaymentService::class)->cancel($payment, 'exclude cancelled payment');

        $balance = app(CreateReceivableMonthlyBalanceDraftService::class)
            ->create(2026, 6)
            ->first();

        $this->assertSame('1650.00', $balance->scheduled_amount);
        $this->assertSame('0.00', $balance->received_amount);
        $this->assertSame('1650.00', $balance->outstanding_amount);
        $this->assertSame(1, $balance->open_schedule_count);
        $this->assertSame(0, $balance->closed_schedule_count);
    }

    public function test_it_updates_existing_draft_for_same_month_customer(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('AR-UPDATE-CUST-001');

        $this->createPaymentSchedule($customer, $product, $unit, '1.0000', '2026-06-01');
        $first = app(CreateReceivableMonthlyBalanceDraftService::class)->create(2026, 6)->first();

        $this->createPaymentSchedule($customer, $product, $unit, '2.0000', '2026-06-02');
        $second = app(CreateReceivableMonthlyBalanceDraftService::class)->create(2026, 6)->first();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('4950.00', $second->scheduled_amount);
        $this->assertSame('4950.00', $second->outstanding_amount);
        $this->assertSame(2, $second->open_schedule_count);
    }

    public function test_it_rejects_recreating_non_draft_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('AR-CONFIRMED-CUST-001');

        $this->createPaymentSchedule($customer, $product, $unit, '1.0000', '2026-06-01');
        $balance = app(CreateReceivableMonthlyBalanceDraftService::class)->create(2026, 6)->first();
        $balance->update(['status' => 'confirmed']);

        $this->expectException(ReceivableMonthlyBalanceDraftException::class);

        app(CreateReceivableMonthlyBalanceDraftService::class)->create(2026, 6);
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit}
     */
    private function prepareBaseData(string $customerCode): array
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            ShipmentMasterSeeder::class,
        ]);

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => $customerCode,
            'name' => $customerCode.' Name',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => $customerCode.'-SAKE',
            'product_type' => 'sake',
            'name' => $customerCode.' Sake',
            'display_name' => $customerCode.' Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        return [$customer, $product, $unit];
    }

    private function createPaymentSchedule(
        Customer $customer,
        Product $product,
        Unit $unit,
        string $quantity,
        string $invoiceDate,
    ): PaymentSchedule {
        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: $invoiceDate,
            billingTargetDate: $invoiceDate,
            lines: [
                new CreateDraftShipmentLineData($product->id, $quantity, $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: $invoiceDate,
            dueDate: '2026-07-31',
            shipmentHeaderIds: [$shipment->id],
        ));
        $invoice = app(ConfirmInvoiceService::class)->confirm($invoice);

        return app(CreatePaymentScheduleService::class)->create($invoice);
    }
}
