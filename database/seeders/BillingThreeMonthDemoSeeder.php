<?php

namespace Database\Seeders;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\NumberSequence;
use App\Models\PriceRule;
use App\Models\ProductionLot;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateClosingInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Billing\CreatePaymentScheduleService;
use App\Services\Billing\RegisterPaymentService;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\AllocateShipmentLineLotService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentFromPickData;
use App\Services\Shipment\CreateDraftShipmentFromPickService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BillingThreeMonthDemoSeeder extends Seeder
{
    private const SOURCE_TYPE = 'billing_five_month_demo';

    public function run(): void
    {
        $this->call([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            TaxMasterSeeder::class,
            LiquorTaxMasterSeeder::class,
            StockLocationSeeder::class,
            ShipmentMasterSeeder::class,
            DemoCustomerSeeder::class,
            DemoProductSeeder::class,
        ]);
        $this->ensureMayDemoPricesAndStock();
        $this->deleteOwnScenarioData();
        $this->syncCurrentMonthNumberSequences();

        if (SalesOrder::query()->where('source_type', self::SOURCE_TYPE)->exists()) {
            return;
        }

        $spotCycle = BillingCycle::query()->updateOrCreate(
            ['code' => 'per_shipment_next_month_end'],
            [
                'name' => '都度請求 翌月末入金',
                'closing_day' => null,
                'payment_month_offset' => 1,
                'payment_day' => 31,
                'billing_method' => 'per_shipment',
                'description' => '出荷1件ごとに請求書を作成するデモ用請求サイクル',
                'is_active' => true,
            ],
        );

        $monthlyRetail = Customer::query()->where('customer_code', 'DEMO-CUST-010')->firstOrFail();
        $monthlyFood = Customer::query()->where('customer_code', 'DEMO-CUST-013')->firstOrFail();
        $spotRetail = Customer::query()->where('customer_code', 'DEMO-CUST-015')->firstOrFail();
        $spotRetail->forceFill(['billing_cycle_id' => $spotCycle->id])->save();

        $shipments = [];
        foreach ($this->scenarios($monthlyRetail, $monthlyFood, $spotRetail) as $scenario) {
            $shipments[] = $this->createShipmentFlow($scenario);
        }

        $this->createMonthlyInvoices($monthlyRetail, [
            ['closing_date' => '2026-02-28', 'due_date' => '2026-03-31', 'payment_date' => '2026-03-29', 'payment_ratio' => '1.00'],
            ['closing_date' => '2026-03-31', 'due_date' => '2026-04-30', 'payment_date' => '2026-04-28', 'payment_ratio' => '0.60'],
            ['closing_date' => '2026-04-30', 'due_date' => '2026-05-31', 'payment_date' => '2026-06-03', 'payment_ratio' => '1.00'],
            ['closing_date' => '2026-05-31', 'due_date' => '2026-06-30', 'payment_date' => null, 'payment_ratio' => '0.00'],
            ['closing_date' => '2026-06-30', 'due_date' => '2026-07-31', 'payment_date' => '2026-07-02', 'payment_ratio' => '0.30'],
        ]);

        $this->createMonthlyInvoices($monthlyFood, [
            ['closing_date' => '2026-02-28', 'due_date' => '2026-03-31', 'payment_date' => '2026-03-31', 'payment_ratio' => '1.00'],
            ['closing_date' => '2026-03-31', 'due_date' => '2026-04-30', 'payment_date' => null, 'payment_ratio' => '0.00'],
            ['closing_date' => '2026-04-30', 'due_date' => '2026-05-31', 'payment_date' => '2026-05-30', 'payment_ratio' => '0.50'],
            ['closing_date' => '2026-05-31', 'due_date' => '2026-06-30', 'payment_date' => '2026-06-30', 'payment_ratio' => '1.00'],
            ['closing_date' => '2026-06-30', 'due_date' => '2026-07-31', 'payment_date' => null, 'payment_ratio' => '0.00'],
        ]);

        foreach ($shipments as $shipment) {
            if ($shipment->customer_id !== $spotRetail->id) {
                continue;
            }

            $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
                customerId: $spotRetail->id,
                invoiceDate: $shipment->billing_target_date->toDateString(),
                billingPeriodStart: $shipment->billing_target_date->toDateString(),
                billingPeriodEnd: $shipment->billing_target_date->toDateString(),
                dueDate: $shipment->billing_target_date->copy()->addMonthNoOverflow()->endOfMonth()->toDateString(),
                note: '都度請求デモ。前回未入金は表示用に残し、今回請求額には含めない。',
                reason: 'billing three month demo spot invoice',
                shipmentHeaderIds: [$shipment->id],
                includeCarriedForward: false,
            ));
            $invoice = app(ConfirmInvoiceService::class)->confirm($invoice, 'billing three month demo spot invoice confirm');
            $schedule = app(CreatePaymentScheduleService::class)->create($invoice, 'billing three month demo spot payment schedule');

            if ($shipment->billing_target_date->format('Y-m') === '2026-03') {
                $overPayment = bcadd((string) $schedule->scheduled_amount, '1000.00', 2);
                app(RegisterPaymentService::class)->register($schedule, $overPayment, '2026-07-01', referenceNumber: 'DEMO-SPOT-OVERPAY', reason: 'billing three month demo overpayment');
            } elseif ($shipment->billing_target_date->format('Y-m') === '2026-02') {
                app(RegisterPaymentService::class)->register($schedule, (string) $schedule->scheduled_amount, '2026-03-30', referenceNumber: 'DEMO-SPOT-PAID', reason: 'billing five month demo spot paid');
            } elseif ($shipment->billing_target_date->format('Y-m') === '2026-04') {
                $partial = bcmul((string) $schedule->scheduled_amount, '0.50', 2);
                app(RegisterPaymentService::class)->register($schedule, $partial, '2026-05-31', referenceNumber: 'DEMO-SPOT-PARTIAL', reason: 'billing five month demo spot partial');
            } elseif ($shipment->billing_target_date->format('Y-m') === '2026-05') {
                app(RegisterPaymentService::class)->register($schedule, (string) $schedule->scheduled_amount, '2026-07-01', referenceNumber: 'DEMO-SPOT-LATE', reason: 'billing five month demo spot late paid');
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scenarios(Customer $monthlyRetail, Customer $monthlyFood, Customer $spotRetail): array
    {
        return [
            $this->scenario($monthlyRetail, '2026-02-06', '2026-02-08', 'DEMO-BILL-5M-001', [['DEMO-PROD-003', '4.0000'], ['DEMO-PROD-006', '3.0000']]),
            $this->scenario($monthlyRetail, '2026-02-20', '2026-02-22', 'DEMO-BILL-5M-002', [['DEMO-PROD-001', '6.0000']]),
            $this->scenario($monthlyRetail, '2026-03-07', '2026-03-09', 'DEMO-BILL-5M-003', [['DEMO-PROD-004', '2.0000'], ['DEMO-PROD-009', '8.0000']]),
            $this->scenario($monthlyRetail, '2026-03-22', '2026-03-24', 'DEMO-BILL-5M-004', [['DEMO-PROD-018', '3.0000']]),
            $this->scenario($monthlyRetail, '2026-04-09', '2026-04-11', 'DEMO-BILL-5M-005', [['DEMO-PROD-002', '5.0000']]),
            $this->scenario($monthlyRetail, '2026-05-08', '2026-05-10', 'DEMO-BILL-5M-006', [['DEMO-PROD-005', '4.0000'], ['DEMO-PROD-006', '2.0000']]),
            $this->scenario($monthlyRetail, '2026-06-10', '2026-06-12', 'DEMO-BILL-5M-007', [['DEMO-PROD-001', '8.0000']]),
            $this->scenario($monthlyFood, '2026-02-12', '2026-02-14', 'DEMO-BILL-5M-008', [['DEMO-PROD-019', '12.0000'], ['DEMO-PROD-023', '10.0000']]),
            $this->scenario($monthlyFood, '2026-03-16', '2026-03-18', 'DEMO-BILL-5M-009', [['DEMO-PROD-020', '8.0000'], ['DEMO-PROD-025', '5.0000']]),
            $this->scenario($monthlyFood, '2026-04-15', '2026-04-17', 'DEMO-BILL-5M-010', [['DEMO-PROD-021', '9.0000']]),
            $this->scenario($monthlyFood, '2026-05-18', '2026-05-20', 'DEMO-BILL-5M-011', [['DEMO-PROD-022', '7.0000'], ['DEMO-PROD-024', '6.0000']]),
            $this->scenario($monthlyFood, '2026-06-18', '2026-06-20', 'DEMO-BILL-5M-012', [['DEMO-PROD-020', '10.0000']]),
            $this->scenario($spotRetail, '2026-02-16', '2026-02-17', 'DEMO-BILL-5M-013', [['DEMO-PROD-002', '2.0000']]),
            $this->scenario($spotRetail, '2026-03-13', '2026-03-14', 'DEMO-BILL-5M-014', [['DEMO-PROD-005', '3.0000']]),
            $this->scenario($spotRetail, '2026-04-18', '2026-04-19', 'DEMO-BILL-5M-015', [['DEMO-PROD-028', '4.0000']]),
            $this->scenario($spotRetail, '2026-05-21', '2026-05-22', 'DEMO-BILL-5M-016', [['DEMO-PROD-004', '2.0000']]),
            $this->scenario($spotRetail, '2026-06-24', '2026-06-25', 'DEMO-BILL-5M-017', [['DEMO-PROD-009', '6.0000']]),
        ];
    }

    /**
     * @param array<int, array{0: string, 1: string}> $lines
     * @return array<string, mixed>
     */
    private function scenario(Customer $customer, string $orderDate, string $shipmentDate, string $orderNumber, array $lines): array
    {
        return compact('customer', 'orderDate', 'shipmentDate', 'orderNumber', 'lines');
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function createShipmentFlow(array $scenario): \App\Models\ShipmentHeader
    {
        $orderLines = collect($scenario['lines'])->map(function (array $line): CreateSalesOrderLineData {
            $product = Product::query()->where('product_code', $line[0])->firstOrFail();
            $unit = Unit::query()->findOrFail($product->sales_unit_id);

            return new CreateSalesOrderLineData($product->id, $line[1], $unit->id);
        })->all();

        $order = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $scenario['customer']->id,
            orderDate: $scenario['orderDate'],
            requestedDeliveryDate: $scenario['shipmentDate'],
            customerOrderNumber: $scenario['orderNumber'],
            sourceType: self::SOURCE_TYPE,
            sourceReference: $scenario['orderNumber'],
            note: '請求・入金の3か月デモ用受注',
            reason: 'billing three month demo sales order',
            applyPricing: true,
            lines: $orderLines,
        ));

        $instruction = app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: $scenario['orderDate'],
            scheduledShipmentDate: $scenario['shipmentDate'],
            note: '請求・入金の3か月デモ用出荷指示',
            reason: 'billing three month demo shipment instruction',
            lines: $order->lines->map(fn ($line): CreateShipmentInstructionLineData => new CreateShipmentInstructionLineData($line->id, $line->quantity))->all(),
        ));

        $pick = app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: $scenario['shipmentDate'],
            note: '請求・入金の3か月デモ用ピッキング',
            reason: 'billing three month demo pick',
            lines: $instruction->lines->map(fn ($line): PickShipmentInstructionLineData => new PickShipmentInstructionLineData($line->id, $line->quantity))->all(),
        ));

        $shipment = app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(
            shipmentPickId: $pick->id,
            documentDate: $scenario['shipmentDate'],
            billingTargetDate: $scenario['shipmentDate'],
            note: '請求・入金の3か月デモ用出荷',
            reason: 'billing three month demo shipment',
        ));

        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment, 'billing three month demo pricing');
        $this->allocateLots($shipment);

        return app(ConfirmShipmentService::class)->confirm($shipment, 'billing three month demo shipment confirm');
    }

    private function allocateLots(\App\Models\ShipmentHeader $shipment): void
    {
        $location = StockLocation::query()
            ->where('is_default_shipping_location', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->firstOrFail();

        $shipment->load('lines.product');

        foreach ($shipment->lines as $line) {
            if (! $line->product->is_inventory_managed) {
                continue;
            }

            $lot = ProductionLot::query()
                ->where('product_id', $line->product_id)
                ->where('status', 'active')
                ->where('is_active', true)
                ->orderBy('id')
                ->firstOrFail();

            app(AllocateShipmentLineLotService::class)->allocate(
                shipmentLine: $line,
                productionLot: $lot,
                stockLocation: $location,
                quantity: (string) $line->quantity,
                reason: 'billing three month demo lot allocation',
            );
        }
    }

    /**
     * @param array<int, array{closing_date: string, due_date: string, payment_date: string|null, payment_ratio: string}> $periods
     */
    private function createMonthlyInvoices(Customer $customer, array $periods): void
    {
        foreach ($periods as $period) {
            $invoice = app(CreateClosingInvoiceService::class)->create(
                customerId: $customer->id,
                closingDate: $period['closing_date'],
                dueDate: $period['due_date'],
                note: '月次締め請求デモ。前回繰越・期間内入金・今回売上を確認できます。',
                reason: 'billing three month demo closing invoice',
            );
            $invoice = app(ConfirmInvoiceService::class)->confirm($invoice, 'billing three month demo closing invoice confirm');
            $schedule = app(CreatePaymentScheduleService::class)->create($invoice, 'billing three month demo payment schedule');

            if ($period['payment_date'] === null || bccomp($period['payment_ratio'], '0.00', 2) <= 0) {
                continue;
            }

            $amount = bcmul((string) $schedule->scheduled_amount, $period['payment_ratio'], 2);
            app(RegisterPaymentService::class)->register($schedule, $amount, $period['payment_date'], referenceNumber: 'DEMO-MONTHLY-PAY', reason: 'billing three month demo payment');
        }
    }

    private function ensureMayDemoPricesAndStock(): void
    {
        PriceRule::query()
            ->whereHas('product', fn ($query) => $query->where('product_code', 'like', 'DEMO-PROD-%'))
            ->whereDate('effective_from', '>', '2026-02-01')
            ->update(['effective_from' => '2026-02-01']);

        $location = StockLocation::query()->where('code', 'main_brewery')->firstOrFail();

        Product::query()
            ->where('product_code', 'like', 'DEMO-PROD-%')
            ->orderBy('product_code')
            ->get()
            ->each(function (Product $product) use ($location): void {
                $lot = ProductionLot::query()
                    ->where('product_id', $product->id)
                    ->orderBy('id')
                    ->first();

                StockMovement::query()->updateOrCreate(
                    [
                        'source_type' => 'billing_five_month_demo_opening_stock',
                        'source_document_number' => 'DEMO-BILLING-5M-OPENING-202602',
                        'source_line_no' => $product->id,
                    ],
                    [
                        'status' => 'confirmed',
                        'movement_type' => 'opening_stock',
                        'movement_date' => '2026-02-01',
                        'product_id' => $product->id,
                        'stock_location_id' => $location->id,
                        'unit_id' => $product->inventory_unit_id,
                        'quantity' => '200.0000',
                        'source_shipment_header_id' => null,
                        'source_shipment_line_id' => null,
                        'related_stock_movement_id' => null,
                        'production_lot_id' => $lot?->id,
                        'lot_code' => $lot?->lot_code,
                        'confirmed_at' => now(),
                        'closed_at' => null,
                        'cancelled_at' => null,
                        'cancelled_reason' => null,
                        'reason' => '請求・入金3か月デモ用の5月初期在庫',
                        'note' => '既存データを消さずに追加するデモ在庫。',
                    ],
                );
            });
    }

    private function syncCurrentMonthNumberSequences(): void
    {
        $yyyymm = now()->format('Ym');

        $targets = [
            'sales_order' => ['sales_orders', 'order_number', "O-{$yyyymm}-"],
            'shipment_instruction' => ['shipment_instructions', 'instruction_number', "SI-{$yyyymm}-"],
            'shipment_pick' => ['shipment_picks', 'pick_number', "P-{$yyyymm}-"],
            'shipment_document' => ['shipment_headers', 'document_number', "S-{$yyyymm}-"],
            'invoice_document' => ['invoice_headers', 'invoice_number', "I-{$yyyymm}-"],
        ];

        foreach ($targets as $code => [$table, $column, $prefix]) {
            $max = DB::table($table)
                ->where($column, 'like', $prefix.'%')
                ->get([$column])
                ->map(fn ($row): int => (int) substr((string) $row->{$column}, strlen($prefix)))
                ->max() ?? 0;

            NumberSequence::query()
                ->where('code', $code)
                ->update([
                    'current_number' => $max,
                    'last_reset_on' => now()->toDateString(),
                ]);
        }
    }

    private function deleteOwnScenarioData(): void
    {
        $orderIds = DB::table('sales_orders')
            ->where('source_type', self::SOURCE_TYPE)
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return;
        }

        $orderLineIds = DB::table('sales_order_lines')->whereIn('sales_order_id', $orderIds)->pluck('id');
        $instructionIds = DB::table('shipment_instruction_lines')->whereIn('sales_order_line_id', $orderLineIds)->pluck('shipment_instruction_id')->unique()->values();
        $pickIds = DB::table('shipment_picks')->whereIn('shipment_instruction_id', $instructionIds)->pluck('id');
        $shipmentIds = DB::table('shipment_headers')->whereIn('source_shipment_pick_id', $pickIds)->pluck('id');
        $shipmentLineIds = DB::table('shipment_lines')->whereIn('shipment_header_id', $shipmentIds)->pluck('id');
        $invoiceIds = DB::table('invoice_lines')->whereIn('shipment_header_id', $shipmentIds)->pluck('invoice_header_id')->unique()->values();
        $paymentScheduleIds = DB::table('payment_schedules')->whereIn('invoice_header_id', $invoiceIds)->pluck('id');
        $paymentIds = DB::table('payment_allocations')->whereIn('payment_schedule_id', $paymentScheduleIds)->pluck('payment_id')->unique()->values();

        DB::table('payment_allocations')->whereIn('payment_id', $paymentIds)->delete();
        DB::table('payments')->whereIn('id', $paymentIds)->delete();
        DB::table('payment_schedules')->whereIn('id', $paymentScheduleIds)->delete();
        DB::table('invoice_lines')->whereIn('invoice_header_id', $invoiceIds)->delete();
        DB::table('invoice_headers')->whereIn('id', $invoiceIds)->delete();
        DB::table('stock_movements')->whereIn('source_shipment_line_id', $shipmentLineIds)->delete();
        DB::table('shipment_lot_allocations')->whereIn('shipment_line_id', $shipmentLineIds)->delete();
        DB::table('shipment_stock_reservations')->whereIn('shipment_line_id', $shipmentLineIds)->delete();
        DB::table('shipment_lines')->whereIn('id', $shipmentLineIds)->delete();
        DB::table('shipment_headers')->whereIn('id', $shipmentIds)->delete();
        DB::table('shipment_pick_lines')->whereIn('shipment_pick_id', $pickIds)->delete();
        DB::table('shipment_picks')->whereIn('id', $pickIds)->delete();
        DB::table('shipment_instruction_lines')->whereIn('shipment_instruction_id', $instructionIds)->delete();
        DB::table('shipment_instructions')->whereIn('id', $instructionIds)->delete();
        DB::table('sales_order_lines')->whereIn('id', $orderLineIds)->delete();
        DB::table('sales_orders')->whereIn('id', $orderIds)->delete();
    }
}
