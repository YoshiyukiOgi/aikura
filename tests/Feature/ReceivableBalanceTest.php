<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PaymentSchedule;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Billing\CancelPaymentService;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Billing\CreatePaymentScheduleService;
use App\Services\Billing\ReceivableBalanceService;
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
use Tests\TestCase;

class ReceivableBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_customer_receivable_balance(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('BALANCE-CUST-001');
        $openSchedule = $this->createPaymentSchedule($customer, $product, $unit, '2.0000', '2026-05-31');
        $partialSchedule = $this->createPaymentSchedule($customer, $product, $unit, '3.0000', '2026-06-01');
        $closedSchedule = $this->createPaymentSchedule($customer, $product, $unit, '1.0000', '2026-06-02');
        $this->resetSchedule($openSchedule, '3300.00');
        $this->resetSchedule($partialSchedule, '4950.00');
        $this->resetSchedule($closedSchedule, '1650.00');

        app(RegisterPaymentService::class)->register($partialSchedule, '1000.00', '2026-06-20');
        app(RegisterPaymentService::class)->register($closedSchedule, '1650.00', '2026-06-30');

        $balance = app(ReceivableBalanceService::class)->forCustomer($customer);

        $this->assertSame($customer->id, $balance->customerId);
        $this->assertSame('9900.00', $balance->scheduledAmount);
        $this->assertSame('2650.00', $balance->receivedAmount);
        $this->assertSame('7250.00', $balance->outstandingAmount);
        $this->assertSame(1, $balance->openScheduleCount);
        $this->assertSame(1, $balance->partialScheduleCount);
        $this->assertSame(1, $balance->closedScheduleCount);
        $this->assertSame('3300.00', $openSchedule->refresh()->outstanding_amount);
    }

    public function test_cancelled_payment_is_reflected_by_restored_schedule_balance(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('BALANCE-CANCEL-CUST-001');
        $schedule = $this->createPaymentSchedule($customer, $product, $unit, '2.0000', '2026-05-31');
        $payment = app(RegisterPaymentService::class)->register($schedule, '3300.00', '2026-06-30');

        app(CancelPaymentService::class)->cancel($payment, 'balance restoration');

        $balance = app(ReceivableBalanceService::class)->forCustomer($customer);

        $this->assertSame('3300.00', $balance->scheduledAmount);
        $this->assertSame('0.00', $balance->receivedAmount);
        $this->assertSame('3300.00', $balance->outstandingAmount);
        $this->assertSame(1, $balance->openScheduleCount);
        $this->assertSame(0, $balance->closedScheduleCount);
    }

    public function test_it_lists_balances_for_customers_with_payment_schedules(): void
    {
        [$firstCustomer, $firstProduct, $firstUnit] = $this->prepareBaseData('BALANCE-LIST-CUST-001');
        [$secondCustomer, $secondProduct, $secondUnit] = $this->prepareBaseData('BALANCE-LIST-CUST-002');

        $this->createPaymentSchedule($firstCustomer, $firstProduct, $firstUnit, '1.0000', '2026-05-31');
        $this->createPaymentSchedule($secondCustomer, $secondProduct, $secondUnit, '2.0000', '2026-05-31');

        $balances = app(ReceivableBalanceService::class)->allCustomers();

        $this->assertCount(2, $balances);
        $this->assertSame('BALANCE-LIST-CUST-001', $balances[0]->customerCode);
        $this->assertSame('1650.00', $balances[0]->outstandingAmount);
        $this->assertSame('BALANCE-LIST-CUST-002', $balances[1]->customerCode);
        $this->assertSame('3300.00', $balances[1]->outstandingAmount);
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
            dueDate: '2026-06-30',
            shipmentHeaderIds: [$shipment->id],
        ));
        $invoice = app(ConfirmInvoiceService::class)->confirm($invoice);

        $scheduledAmount = bcmul($quantity, '1650.00', 2);

        $schedule = PaymentSchedule::query()->where('invoice_header_id', $invoice->id)->firstOrFail();
        $schedule->refresh()->forceFill([
            'status' => 'open',
            'expected_payment_date' => '2026-06-30',
            'scheduled_amount' => $scheduledAmount,
            'received_amount' => '0.00',
            'outstanding_amount' => $scheduledAmount,
        ])->save();

        return $schedule->refresh();
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
        \App\Models\AppSetting::setValue('operational_start_date', '2026-01-01');

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
            'is_inventory_managed' => false,
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

    private function resetSchedule(PaymentSchedule $schedule, string $amount): void
    {
        $schedule->refresh()->forceFill([
            'status' => 'open',
            'received_amount' => '0.00',
            'outstanding_amount' => $amount,
            'scheduled_amount' => $amount,
            'closed_at' => null,
        ])->save();
    }
}
