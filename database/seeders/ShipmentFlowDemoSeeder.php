<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\ProductionLot;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\ShipmentHeader;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentPick;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\Shipment\AllocateShipmentLineLotService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\CancelShippingFlowService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentFromInstructionService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShipmentFlowDemoSeeder extends Seeder
{
    private const SOURCE_TYPE = 'shipment_flow_demo';
    private const REASON = '出荷フローデモデータ作成';

    public function run(): void
    {
        $this->clearTransactionData();

        $customers = Customer::query()->where('is_active', true)->orderBy('id')->limit(8)->get();
        $products = Product::query()
            ->where('is_active', true)
            ->where('is_sales_available', true)
            ->orderBy('id')
            ->limit(12)
            ->get();
        $location = StockLocation::query()
            ->where('is_default_shipping_location', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($customers->count() < 4 || $products->count() < 6 || ! $location) {
            throw new RuntimeException('出荷フローデモには、有効な取引先4件以上・商品6件以上・既定出荷拠点が必要です。');
        }

        foreach ($products as $index => $product) {
            $this->ensureLotStock($product, $location, $index + 1);
        }

        $orderOnly = $this->createOrder(1, $customers[0], $products->slice(0, 3)->values()->all(), '2026-06-21');
        $this->createOrder(2, $customers[1], $products->slice(1, 3)->values()->all(), '2026-06-22');

        $returned = $this->createOrder(3, $customers[2], $products->slice(2, 3)->values()->all(), '2026-06-23');
        $returnedInstruction = $this->createInstruction($returned, '2026-06-23', $location);
        $this->createDraftShipment($returnedInstruction);
        app(CancelShippingFlowService::class)->cancel($returnedInstruction, 'デモ：出荷完了前の出荷取消');

        $instructed = $this->createOrder(4, $customers[3], $products->slice(3, 3)->values()->all(), '2026-06-24');
        $this->createInstruction($instructed, '2026-06-24', $location);

        $issued = $this->createOrder(5, $customers[4 % $customers->count()], $products->slice(4, 3)->values()->all(), '2026-06-25');
        $issuedInstruction = $this->createInstruction($issued, '2026-06-25', $location);
        $this->createDraftShipment($issuedInstruction);

        $pickedIssued = $this->createOrder(6, $customers[5 % $customers->count()], $products->slice(5, 3)->values()->all(), '2026-06-26');
        $pickedInstruction = $this->createInstruction($pickedIssued, '2026-06-26', $location);
        $this->createPick($pickedInstruction, '2026-06-26', $location);
        $pickedShipment = $this->createDraftShipment($pickedInstruction);
        $this->allocateLots($pickedShipment, $location);
        app(ApplyDraftShipmentPricingService::class)->apply($pickedShipment, self::REASON);

        $completed = $this->createOrder(7, $customers[6 % $customers->count()], $products->slice(6, 3)->values()->all(), '2026-06-27');
        $completedInstruction = $this->createInstruction($completed, '2026-06-27', $location);
        $this->createPick($completedInstruction, '2026-06-27', $location);
        $completedShipment = $this->createDraftShipment($completedInstruction);
        $this->allocateLots($completedShipment, $location);
        $completedShipment = app(ApplyDraftShipmentPricingService::class)->apply($completedShipment, self::REASON);
        app(ConfirmShipmentService::class)->confirm($completedShipment, 'デモ：出荷完了');

        $this->command?->info('Shipment flow demo data has been recreated.');
    }

    private function clearTransactionData(): void
    {
        DB::statement(<<<'SQL'
TRUNCATE TABLE
    payment_allocations,
    payments,
    payment_schedules,
    invoice_lines,
    invoice_headers,
    receivable_monthly_balances,
    shipment_stock_reservations,
    shipment_lot_allocations,
    stock_movements,
    shipment_lines,
    shipment_headers,
    shipment_pick_lines,
    shipment_picks,
    shipment_instruction_lines,
    shipment_instructions,
    sales_order_lines,
    sales_orders,
    price_review_tasks,
    audit_logs
RESTART IDENTITY CASCADE
SQL);
    }

    /**
     * @param array<int, Product> $products
     */
    private function createOrder(int $sequence, Customer $customer, array $products, string $date): SalesOrder
    {
        return app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: $date,
            requestedShipmentDate: $date,
            requestedDeliveryDate: $date,
            customerOrderNumber: sprintf('FLOW-DEMO-%03d', $sequence),
            sourceType: self::SOURCE_TYPE,
            sourceReference: sprintf('shipment-flow-demo-%03d', $sequence),
            note: '出荷フローデモ受注',
            reason: self::REASON,
            applyPricing: true,
            awaitingShipmentInstruction: false,
            lines: array_map(
                fn (Product $product, int $index): CreateSalesOrderLineData => new CreateSalesOrderLineData(
                    productId: $product->id,
                    quantity: (string) (3 + $index),
                    unitId: $product->sales_unit_id,
                    note: '出荷フローデモ明細',
                ),
                $products,
                array_keys($products),
            ),
        ));
    }

    private function createInstruction(SalesOrder $order, string $date, StockLocation $location): ShipmentInstruction
    {
        $order->load('lines');

        return app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: $date,
            scheduledShipmentDate: $date,
            stockLocationId: $location->id,
            note: '出荷フローデモ出荷指示',
            reason: self::REASON,
            lines: $order->lines
                ->sortBy('line_no')
                ->map(fn (SalesOrderLine $line): CreateShipmentInstructionLineData => new CreateShipmentInstructionLineData(
                    salesOrderLineId: $line->id,
                    quantity: (string) $line->remaining_quantity,
                    note: '出荷フローデモ出荷指示明細',
                ))
                ->values()
                ->all(),
        ));
    }

    private function createPick(ShipmentInstruction $instruction, string $date, StockLocation $location): ShipmentPick
    {
        $instruction->load('lines');

        return app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: $date,
            stockLocationId: $location->id,
            note: '出荷フローデモピッキング',
            reason: self::REASON,
            lines: $instruction->lines
                ->sortBy('line_no')
                ->map(fn ($line): PickShipmentInstructionLineData => new PickShipmentInstructionLineData(
                    shipmentInstructionLineId: $line->id,
                    quantity: (string) $line->quantity,
                    note: '出荷フローデモピック明細',
                ))
                ->values()
                ->all(),
        ));
    }

    private function createDraftShipment(ShipmentInstruction $instruction): ShipmentHeader
    {
        return app(CreateDraftShipmentFromInstructionService::class)->create($instruction);
    }

    private function allocateLots(ShipmentHeader $shipment, StockLocation $location): void
    {
        $shipment->load('lines.product.productionLots');

        foreach ($shipment->lines as $line) {
            $lot = $line->product->productionLots
                ->where('is_active', true)
                ->where('status', 'active')
                ->sortBy('id')
                ->first();

            if (! $lot instanceof ProductionLot) {
                throw new RuntimeException("商品ID {$line->product_id} の有効ロットがありません。");
            }

            app(AllocateShipmentLineLotService::class)->allocate(
                shipmentLine: $line,
                productionLot: $lot,
                stockLocation: $location,
                quantity: (string) $line->quantity,
                reason: self::REASON,
            );
        }
    }

    private function ensureLotStock(Product $product, StockLocation $location, int $sequence): void
    {
        $lot = ProductionLot::query()
            ->where('unit_id', $product->sales_unit_id)
            ->where('capacity_value', $product->capacity_value)
            ->where('capacity_unit_id', $product->capacity_unit_id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (! $lot) {
            $lot = ProductionLot::create([
                'lot_code' => sprintf('FLOW-LOT-%03d', $sequence),
                'display_name' => sprintf('2025BY デモロット %03d', $sequence),
                'status' => 'active',
                'stock_location_id' => $location->id,
                'unit_id' => $product->sales_unit_id,
                'capacity_value' => $product->capacity_value,
                'capacity_unit_id' => $product->capacity_unit_id,
                'alcohol_percentage' => $product->alcohol_percentage,
                'analysis_status' => $product->is_alcohol ? 'confirmed' : null,
                'production_date' => '2026-04-01',
                'bottling_date' => '2026-04-15',
                'rice_variety' => '山田錦',
                'rice_polishing_ratio' => '50.00',
                'production_method' => 'デモ',
                'search_key' => sprintf('flow lot %03d', $sequence),
                'is_active' => true,
            ]);
        }

        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-06-20',
            'stock_location_id' => $location->id,
            'unit_id' => $product->sales_unit_id,
            'quantity' => '200.0000',
            'source_type' => 'shipment_flow_demo',
            'source_document_number' => 'FLOW-STOCK',
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'confirmed_at' => now(),
            'reason' => self::REASON,
            'note' => '出荷フローデモ在庫',
        ]);
    }
}
