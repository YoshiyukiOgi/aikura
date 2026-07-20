<?php

namespace Database\Seeders;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\NumberSequence;
use App\Models\PriceRule;
use App\Models\ProductionLot;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\ShipmentHeader;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentPick;
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
use App\Services\Shipment\AllocateShipmentLineLotService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FlowValidationDemoSeeder extends Seeder
{
    private const SOURCE_TYPE = 'flow_validation_demo';

    /** @var array<int, string> */
    private const SOURCE_TYPES_TO_CLEAR = [
        self::SOURCE_TYPE,
        'billing_five_month_demo',
        'billing_three_month_demo',
        'shipment_flow_demo',
        'demo_screen',
        'arimitsu_transaction_demo',
    ];

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

        $customers = $this->prepareCustomers();
        $this->clearDemoTransactions($customers);
        $this->ensureDemoPricesAndStock();
        $this->syncCurrentMonthNumberSequences();

        $confirmed = collect();

        foreach ($this->confirmedShipmentScenarios($customers) as $scenario) {
            $confirmed->push($this->createFlow($scenario, 'confirmed'));
        }

        $this->createJuneMonthlyInvoices($customers);
        $this->createSpotInvoices($confirmed, $customers);

        foreach ($this->workInProgressScenarios($customers) as $scenario) {
            $this->createFlow($scenario, $scenario['stage']);
        }
    }

    /**
     * @return array<string, Customer>
     */
    private function prepareCustomers(): array
    {
        $spotCycle = BillingCycle::query()->updateOrCreate(
            ['code' => 'per_shipment_next_month_end'],
            [
                'name' => '都度請求 翌月末入金',
                'closing_day' => null,
                'payment_month_offset' => 1,
                'payment_day' => 31,
                'billing_method' => 'per_shipment',
                'description' => '出荷ごとに請求書を作る検証用請求サイクル',
                'is_active' => true,
            ],
        );

        $customers = [
            'monthly_a' => Customer::query()->where('customer_code', 'DEMO-CUST-010')->firstOrFail(),
            'monthly_b' => Customer::query()->where('customer_code', 'DEMO-CUST-013')->firstOrFail(),
            'monthly_c' => Customer::query()->where('customer_code', 'DEMO-CUST-017')->firstOrFail(),
            'spot_a' => Customer::query()->where('customer_code', 'DEMO-CUST-015')->firstOrFail(),
            'spot_b' => Customer::query()->where('customer_code', 'DEMO-CUST-023')->firstOrFail(),
        ];

        foreach (['spot_a', 'spot_b'] as $key) {
            $customers[$key]->forceFill(['billing_cycle_id' => $spotCycle->id])->save();
            $customers[$key]->refresh();
        }

        return $customers;
    }

    /**
     * @param array<string, Customer> $customers
     * @return array<int, array<string, mixed>>
     */
    private function confirmedShipmentScenarios(array $customers): array
    {
        return [
            $this->scenario($customers['monthly_a'], '2026-06-05', '2026-06-06', 'FLOW-202606-MA-01', [['DEMO-PROD-003', '4.0000'], ['DEMO-PROD-006', '2.0000']]),
            $this->scenario($customers['monthly_a'], '2026-06-19', '2026-06-20', 'FLOW-202606-MA-02', [['DEMO-PROD-001', '5.0000']]),
            $this->scenario($customers['monthly_b'], '2026-06-10', '2026-06-11', 'FLOW-202606-MB-01', [['DEMO-PROD-019', '8.0000'], ['DEMO-PROD-023', '4.0000']]),
            $this->scenario($customers['monthly_c'], '2026-06-15', '2026-06-16', 'FLOW-202606-MC-01', [['DEMO-PROD-020', '6.0000']]),
            $this->scenario($customers['monthly_c'], '2026-06-27', '2026-06-28', 'FLOW-202606-MC-02', [['DEMO-PROD-021', '5.0000']]),
            $this->scenario($customers['spot_a'], '2026-06-12', '2026-06-13', 'FLOW-202606-SA-01', [['DEMO-PROD-002', '2.0000']]),
            $this->scenario($customers['spot_a'], '2026-06-18', '2026-06-19', 'FLOW-202606-SA-02', [['DEMO-PROD-005', '3.0000']]),
            $this->scenario($customers['spot_b'], '2026-06-22', '2026-06-23', 'FLOW-202606-SB-01', [['DEMO-PROD-028', '4.0000']]),

            $this->scenario($customers['monthly_a'], '2026-07-03', '2026-07-04', 'FLOW-202607-MA-01', [['DEMO-PROD-004', '3.0000']]),
            $this->scenario($customers['monthly_b'], '2026-07-11', '2026-07-12', 'FLOW-202607-MB-01', [['DEMO-PROD-022', '7.0000'], ['DEMO-PROD-024', '3.0000']]),
            $this->scenario($customers['monthly_c'], '2026-07-24', '2026-07-25', 'FLOW-202607-MC-01', [['DEMO-PROD-020', '5.0000']]),
            $this->scenario($customers['monthly_a'], '2026-07-29', '2026-07-30', 'FLOW-202607-MA-02', [['DEMO-PROD-006', '4.0000']]),
            $this->scenario($customers['spot_a'], '2026-07-05', '2026-07-06', 'FLOW-202607-SA-01', [['DEMO-PROD-009', '4.0000']]),
            $this->scenario($customers['spot_a'], '2026-07-10', '2026-07-11', 'FLOW-202607-SA-02', [['DEMO-PROD-002', '3.0000']]),
            $this->scenario($customers['spot_b'], '2026-07-20', '2026-07-21', 'FLOW-202607-SB-01', [['DEMO-PROD-028', '2.0000']]),
            $this->scenario($customers['spot_b'], '2026-07-29', '2026-07-30', 'FLOW-202607-SB-02', [['DEMO-PROD-005', '2.0000']]),
        ];
    }

    /**
     * @param array<string, Customer> $customers
     * @return array<int, array<string, mixed>>
     */
    private function workInProgressScenarios(array $customers): array
    {
        return [
            $this->scenario($customers['monthly_a'], '2026-07-31', '2026-08-02', 'FLOW-202607-WIP-RECEIVED', [['DEMO-PROD-001', '3.0000']], 'received'),
            $this->scenario($customers['monthly_b'], '2026-07-31', '2026-08-01', 'FLOW-202607-WIP-INSTRUCTED', [['DEMO-PROD-019', '4.0000']], 'instructed'),
            $this->scenario($customers['spot_a'], '2026-07-31', '2026-08-01', 'FLOW-202607-WIP-READY', [['DEMO-PROD-002', '2.0000']], 'ready'),
            $this->scenario($customers['spot_b'], '2026-07-31', '2026-08-01', 'FLOW-202607-WIP-ISSUED', [['DEMO-PROD-028', '2.0000']], 'issued'),
        ];
    }

    /**
     * @param array<int, array{0: string, 1: string}> $lines
     * @return array<string, mixed>
     */
    private function scenario(Customer $customer, string $orderDate, string $shipmentDate, string $reference, array $lines, string $stage = 'confirmed'): array
    {
        return compact('customer', 'orderDate', 'shipmentDate', 'reference', 'lines', 'stage');
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function createFlow(array $scenario, string $stage): ?ShipmentHeader
    {
        $order = $this->createOrder($scenario);

        if ($stage === 'received') {
            return null;
        }

        $instruction = $this->createInstruction($order, $scenario);

        if ($stage === 'instructed') {
            return null;
        }

        $pick = $this->pickInstruction($instruction, $scenario);
        $shipment = $this->createDraftShipment($pick, $scenario);

        if (in_array($stage, ['ready', 'issued', 'confirmed'], true)) {
            $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment, 'flow validation demo pricing');
            $this->allocateLots($shipment);
        }

        if ($stage === 'issued') {
            $shipment->forceFill(['document_issued_at' => now()])->save();

            return $shipment->refresh();
        }

        if ($stage === 'confirmed') {
            return app(ConfirmShipmentService::class)->confirm($shipment, 'flow validation demo shipment confirm');
        }

        return $shipment;
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function createOrder(array $scenario): SalesOrder
    {
        $lines = collect($scenario['lines'])->map(function (array $line): CreateSalesOrderLineData {
            $product = Product::query()->where('product_code', $line[0])->firstOrFail();
            $unit = Unit::query()->findOrFail($product->sales_unit_id);

            return new CreateSalesOrderLineData($product->id, $line[1], $unit->id);
        })->all();

        return app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $scenario['customer']->id,
            orderDate: $scenario['orderDate'],
            requestedDeliveryDate: $scenario['shipmentDate'],
            billingTargetDate: $scenario['shipmentDate'],
            customerOrderNumber: $scenario['reference'],
            sourceType: self::SOURCE_TYPE,
            sourceReference: $scenario['reference'],
            note: 'flow validation demo order',
            reason: 'flow validation demo order',
            applyPricing: true,
            lines: $lines,
        ));
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function createInstruction(SalesOrder $order, array $scenario): ShipmentInstruction
    {
        return app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: $scenario['orderDate'],
            scheduledShipmentDate: $scenario['shipmentDate'],
            note: 'flow validation demo instruction',
            reason: 'flow validation demo instruction',
            lines: $order->lines->map(fn ($line): CreateShipmentInstructionLineData => new CreateShipmentInstructionLineData($line->id, $line->quantity))->all(),
        ));
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function pickInstruction(ShipmentInstruction $instruction, array $scenario): ShipmentPick
    {
        return app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: $scenario['shipmentDate'],
            note: 'flow validation demo pick',
            reason: 'flow validation demo pick',
            lines: $instruction->lines->map(fn ($line): PickShipmentInstructionLineData => new PickShipmentInstructionLineData($line->id, $line->quantity))->all(),
        ));
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function createDraftShipment(ShipmentPick $pick, array $scenario): ShipmentHeader
    {
        return app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(
            shipmentPickId: $pick->id,
            documentDate: $scenario['shipmentDate'],
            billingTargetDate: $scenario['shipmentDate'],
            note: 'flow validation demo shipment',
            reason: 'flow validation demo shipment',
        ));
    }

    /**
     * @param array<string, Customer> $customers
     */
    private function createJuneMonthlyInvoices(array $customers): void
    {
        $plans = [
            ['customer' => $customers['monthly_a'], 'payment' => 'full'],
            ['customer' => $customers['monthly_b'], 'payment' => 'none'],
            ['customer' => $customers['monthly_c'], 'payment' => 'partial'],
        ];

        foreach ($plans as $plan) {
            $invoice = app(CreateClosingInvoiceService::class)->create(
                customerId: $plan['customer']->id,
                closingDate: '2026-06-30',
                dueDate: '2026-07-31',
                note: 'flow validation demo June closing invoice',
                reason: 'flow validation demo June closing invoice',
            );
            $invoice = app(ConfirmInvoiceService::class)->confirm($invoice, 'flow validation demo June invoice confirm');
            $schedule = app(CreatePaymentScheduleService::class)->create($invoice, 'flow validation demo June schedule');

            if ($plan['payment'] === 'full') {
                app(RegisterPaymentService::class)->register($schedule, (string) $schedule->scheduled_amount, '2026-07-25', referenceNumber: 'FLOW-JUNE-FULL', reason: 'flow validation demo full payment');
            }

            if ($plan['payment'] === 'partial') {
                $amount = bcmul((string) $schedule->scheduled_amount, '0.55', 2);
                app(RegisterPaymentService::class)->register($schedule, $amount, '2026-07-26', referenceNumber: 'FLOW-JUNE-PARTIAL', reason: 'flow validation demo partial payment');
            }
        }
    }

    /**
     * @param Collection<int, ShipmentHeader|null> $shipments
     * @param array<string, Customer> $customers
     */
    private function createSpotInvoices(Collection $shipments, array $customers): void
    {
        $shipments = $shipments->filter();

        foreach ($shipments as $shipment) {
            if (! in_array($shipment->customer_id, [$customers['spot_a']->id, $customers['spot_b']->id], true)) {
                continue;
            }

            $date = $shipment->billing_target_date->toDateString();
            $reference = $shipment->sourceShipmentPick?->shipmentInstruction?->lines->first()?->salesOrder?->source_reference;

            if (in_array($reference, ['FLOW-202607-SB-01', 'FLOW-202607-SB-02'], true)) {
                continue;
            }

            $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
                customerId: $shipment->customer_id,
                invoiceDate: $date,
                billingPeriodStart: $date,
                billingPeriodEnd: $date,
                dueDate: $shipment->billing_target_date->copy()->addMonthNoOverflow()->endOfMonth()->toDateString(),
                note: 'flow validation demo spot invoice',
                reason: 'flow validation demo spot invoice',
                shipmentHeaderIds: [$shipment->id],
                includeCarriedForward: false,
            ));

            if ($reference === 'FLOW-202607-SA-01') {
                continue;
            }

            $invoice = app(ConfirmInvoiceService::class)->confirm($invoice, 'flow validation demo spot invoice confirm');

            if ($reference === 'FLOW-202607-SA-02') {
                continue;
            }

            $schedule = app(CreatePaymentScheduleService::class)->create($invoice, 'flow validation demo spot schedule');

            if ($reference === 'FLOW-202606-SA-01') {
                app(RegisterPaymentService::class)->register($schedule, (string) $schedule->scheduled_amount, '2026-07-20', referenceNumber: 'FLOW-SPOT-FULL', reason: 'flow validation demo spot full payment');
            } elseif ($reference === 'FLOW-202606-SA-02') {
                $amount = bcmul((string) $schedule->scheduled_amount, '0.50', 2);
                app(RegisterPaymentService::class)->register($schedule, $amount, '2026-07-22', referenceNumber: 'FLOW-SPOT-SHORT', reason: 'flow validation demo spot short payment');
            } elseif ($reference === 'FLOW-202606-SB-01') {
                $amount = bcadd((string) $schedule->scheduled_amount, '1000.00', 2);
                app(RegisterPaymentService::class)->register($schedule, $amount, '2026-07-24', referenceNumber: 'FLOW-SPOT-OVER', reason: 'flow validation demo spot over payment');
            }
        }
    }

    private function allocateLots(ShipmentHeader $shipment): void
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
                reason: 'flow validation demo lot allocation',
            );
        }
    }

    private function ensureDemoPricesAndStock(): void
    {
        PriceRule::query()
            ->whereHas('product', fn ($query) => $query->where('product_code', 'like', 'DEMO-PROD-%'))
            ->whereDate('effective_from', '>', '2026-06-01')
            ->update(['effective_from' => '2026-06-01']);

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
                        'source_type' => 'flow_validation_demo_opening_stock',
                        'source_document_number' => 'FLOW-DEMO-OPENING-202606',
                        'source_line_no' => $product->id,
                    ],
                    [
                        'status' => 'confirmed',
                        'movement_type' => 'opening_stock',
                        'movement_date' => '2026-06-01',
                        'product_id' => $product->id,
                        'stock_location_id' => $location->id,
                        'unit_id' => $product->inventory_unit_id,
                        'quantity' => '300.0000',
                        'source_shipment_header_id' => null,
                        'source_shipment_line_id' => null,
                        'related_stock_movement_id' => null,
                        'production_lot_id' => $lot?->id,
                        'lot_code' => $lot?->lot_code,
                        'confirmed_at' => now(),
                        'closed_at' => null,
                        'cancelled_at' => null,
                        'cancelled_reason' => null,
                        'reason' => 'flow validation demo opening stock',
                        'note' => 'opening stock for flow validation demo',
                    ],
                );
            });
    }

    /**
     * @param array<string, Customer> $customers
     */
    private function clearDemoTransactions(array $customers): void
    {
        $customerIds = collect($customers)->pluck('id')->all();
        $periodStart = '2026-06-01';
        $periodEnd = '2026-08-31';

        $orderIds = DB::table('sales_orders')
            ->whereIn('source_type', self::SOURCE_TYPES_TO_CLEAR)
            ->pluck('id');
        $orderIds = $orderIds
            ->merge(DB::table('sales_orders')
                ->whereBetween('order_date', [$periodStart, $periodEnd])
                ->pluck('id'))
            ->unique()
            ->values();

        $orderLineIds = DB::table('sales_order_lines')->whereIn('sales_order_id', $orderIds)->pluck('id');
        $instructionIds = DB::table('shipment_instruction_lines')->whereIn('sales_order_line_id', $orderLineIds)->pluck('shipment_instruction_id')->unique()->values();
        $pickIds = DB::table('shipment_picks')->whereIn('shipment_instruction_id', $instructionIds)->pluck('id');
        $shipmentIds = DB::table('shipment_headers')
            ->whereIn('source_shipment_pick_id', $pickIds)
            ->orWhereIn('source_shipment_instruction_id', $instructionIds)
            ->pluck('id');
        $shipmentIds = $shipmentIds
            ->merge(DB::table('shipment_headers')
                ->whereBetween('billing_target_date', [$periodStart, $periodEnd])
                ->pluck('id'))
            ->merge(DB::table('shipment_headers')
                ->whereBetween('document_date', [$periodStart, $periodEnd])
                ->pluck('id'))
            ->unique()
            ->values();
        $pickIds = $pickIds
            ->merge(DB::table('shipment_headers')->whereIn('id', $shipmentIds)->whereNotNull('source_shipment_pick_id')->pluck('source_shipment_pick_id'))
            ->unique()
            ->values();
        $instructionIds = $instructionIds
            ->merge(DB::table('shipment_picks')->whereIn('id', $pickIds)->pluck('shipment_instruction_id'))
            ->merge(DB::table('shipment_headers')->whereIn('id', $shipmentIds)->whereNotNull('source_shipment_instruction_id')->pluck('source_shipment_instruction_id'))
            ->unique()
            ->values();
        $orderLineIds = $orderLineIds
            ->merge(DB::table('shipment_instruction_lines')->whereIn('shipment_instruction_id', $instructionIds)->pluck('sales_order_line_id'))
            ->unique()
            ->values();
        $orderIds = $orderIds
            ->merge(DB::table('sales_order_lines')->whereIn('id', $orderLineIds)->pluck('sales_order_id'))
            ->unique()
            ->values();
        $shipmentLineIds = DB::table('shipment_lines')->whereIn('shipment_header_id', $shipmentIds)->pluck('id');
        $invoiceIds = DB::table('invoice_lines')->whereIn('shipment_header_id', $shipmentIds)->pluck('invoice_header_id')->unique()->values();
        $invoiceIds = $invoiceIds
            ->merge(DB::table('invoice_headers')
                ->where('document_type', 'invoice')
                ->whereBetween('invoice_date', [$periodStart, $periodEnd])
                ->pluck('id'))
            ->unique()
            ->values();
        $invoiceLineIds = DB::table('invoice_lines')->whereIn('invoice_header_id', $invoiceIds)->pluck('id');
        $paymentScheduleIds = DB::table('payment_schedules')->whereIn('invoice_header_id', $invoiceIds)->pluck('id');
        $paymentIds = DB::table('payment_allocations')->whereIn('payment_schedule_id', $paymentScheduleIds)->pluck('payment_id')->unique()->values();

        $returnIds = DB::table('sales_return_headers')
            ->whereIn('credit_invoice_header_id', $invoiceIds)
            ->pluck('id');
        $returnIds = $returnIds
            ->merge(DB::table('sales_return_headers')
                ->whereBetween('return_date', [$periodStart, $periodEnd])
                ->pluck('id'))
            ->unique()
            ->values();
        $returnLineIds = DB::table('sales_return_lines')
            ->whereIn('sales_return_header_id', $returnIds)
            ->orWhereIn('source_invoice_line_id', $invoiceLineIds)
            ->orWhereIn('credit_invoice_line_id', $invoiceLineIds)
            ->pluck('id');
        $invoiceIds = $invoiceIds
            ->merge(DB::table('invoice_headers')
                ->whereIn('source_sales_return_header_id', $returnIds)
                ->pluck('id'))
            ->merge(DB::table('invoice_lines')
                ->whereIn('source_sales_return_line_id', $returnLineIds)
                ->pluck('invoice_header_id'))
            ->unique()
            ->values();
        $invoiceLineIds = DB::table('invoice_lines')->whereIn('invoice_header_id', $invoiceIds)->pluck('id');
        $paymentScheduleIds = DB::table('payment_schedules')->whereIn('invoice_header_id', $invoiceIds)->pluck('id');
        $paymentIds = DB::table('payment_allocations')->whereIn('payment_schedule_id', $paymentScheduleIds)->pluck('payment_id')->unique()->values();
        $nonSalesOperationIds = DB::table('non_sales_stock_operation_headers')
            ->whereIn('source_sales_return_header_id', $returnIds)
            ->pluck('id');
        $nonSalesOperationIds = $nonSalesOperationIds
            ->merge(DB::table('non_sales_stock_operation_headers')
                ->whereBetween('operation_date', [$periodStart, $periodEnd])
                ->pluck('id'))
            ->unique()
            ->values();
        $nonSalesOperationLineIds = DB::table('non_sales_stock_operation_lines')
            ->whereIn('non_sales_stock_operation_header_id', $nonSalesOperationIds)
            ->orWhereIn('source_sales_return_line_id', $returnLineIds)
            ->pluck('id');
        $nonSalesStockMovementIds = DB::table('non_sales_stock_operation_lines')
            ->whereIn('id', $nonSalesOperationLineIds)
            ->whereNotNull('stock_movement_id')
            ->pluck('stock_movement_id');

        DB::table('non_sales_stock_operation_lines')->whereIn('id', $nonSalesOperationLineIds)->update(['stock_movement_id' => null]);
        DB::table('stock_movements')->whereIn('id', $nonSalesStockMovementIds)->delete();
        DB::table('non_sales_stock_operation_lines')->whereIn('id', $nonSalesOperationLineIds)->delete();
        DB::table('non_sales_stock_operation_headers')->whereIn('id', $nonSalesOperationIds)->delete();
        DB::table('invoice_headers')->whereIn('source_sales_return_header_id', $returnIds)->update(['source_sales_return_header_id' => null]);
        DB::table('sales_return_headers')->whereIn('id', $returnIds)->update(['credit_invoice_header_id' => null]);
        DB::table('invoice_lines')->whereIn('source_sales_return_line_id', $returnLineIds)->update(['source_sales_return_line_id' => null]);
        DB::table('sales_return_lines')->whereIn('id', $returnLineIds)->update(['credit_invoice_line_id' => null]);
        DB::table('sales_return_line_lots')->whereIn('sales_return_line_id', $returnLineIds)->delete();
        DB::table('sales_return_lines')->whereIn('id', $returnLineIds)->delete();
        DB::table('sales_return_headers')->whereIn('id', $returnIds)->delete();
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

        DB::table('receivable_monthly_balances')
            ->where('year', 2026)
            ->whereIn('month', [6, 7])
            ->delete();
    }

    private function syncCurrentMonthNumberSequences(): void
    {
        $yyyymm = '202607';

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
                    'last_reset_on' => '2026-07-31',
                ]);
        }
    }
}
