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
use App\Services\Billing\ConfirmInvoiceService;
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
use RuntimeException;

class ArimitsuTransactionDemoSeeder extends Seeder
{
    private const SOURCE_TYPE = 'arimitsu_transaction_demo';
    private const REASON = '有光酒造場 取引デモデータ作成';

    public function run(): void
    {
        if (SalesOrder::query()->where('source_type', self::SOURCE_TYPE)->exists()) {
            $this->command?->info('Arimitsu transaction demo data already exists. Skipped.');

            return;
        }

        $customers = $this->customers();
        $products = $this->products();
        $stockLocation = $this->defaultShippingLocation();

        $scenarios = [
            ['stage' => 'order', 'count' => 3],
            ['stage' => 'instruction', 'count' => 3],
            ['stage' => 'picking', 'count' => 3],
            ['stage' => 'draft_shipment', 'count' => 3],
            ['stage' => 'confirmed_shipment', 'count' => 3],
            ['stage' => 'invoice', 'count' => 2],
            ['stage' => 'payment', 'count' => 2],
        ];

        $created = [
            'orders' => 0,
            'instructions' => 0,
            'picks' => 0,
            'draft_shipments' => 0,
            'confirmed_shipments' => 0,
            'invoices' => 0,
            'payments' => 0,
        ];

        $sequence = 1;

        foreach ($scenarios as $scenario) {
            for ($i = 0; $i < $scenario['count']; $i++, $sequence++) {
                $customer = $customers[($sequence - 1) % $customers->count()];
                $lineProducts = $this->lineProducts($products, $sequence);
                $orderDate = sprintf('2026-06-%02d', min(27, 2 + $sequence));

                $order = $this->createOrder($sequence, $customer, $lineProducts, $orderDate);
                $created['orders']++;

                if ($scenario['stage'] === 'order') {
                    continue;
                }

                $instruction = $this->createInstruction($order, $orderDate, $stockLocation);
                $created['instructions']++;

                if ($scenario['stage'] === 'instruction') {
                    continue;
                }

                $pick = $this->createPick($instruction, $orderDate, $stockLocation);
                $created['picks']++;

                if ($scenario['stage'] === 'picking') {
                    continue;
                }

                $shipment = $this->createDraftShipment($pick, $orderDate);
                $this->allocateShipmentLots($shipment, $stockLocation);
                $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment, self::REASON);
                $created['draft_shipments']++;

                if ($scenario['stage'] === 'draft_shipment') {
                    continue;
                }

                $shipment = app(ConfirmShipmentService::class)->confirm($shipment, self::REASON);
                $created['confirmed_shipments']++;

                if ($scenario['stage'] === 'confirmed_shipment') {
                    continue;
                }

                $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
                    customerId: $customer->id,
                    invoiceDate: $orderDate,
                    dueDate: '2026-07-31',
                    note: '有光酒造場 取引デモ請求',
                    reason: self::REASON,
                    shipmentHeaderIds: [$shipment->id],
                ));
                $invoice = app(ConfirmInvoiceService::class)->confirm($invoice, self::REASON);
                $schedule = app(CreatePaymentScheduleService::class)->create($invoice, self::REASON);
                $created['invoices']++;

                if ($scenario['stage'] === 'invoice') {
                    continue;
                }

                $paymentAmount = $i === 0
                    ? (string) $schedule->scheduled_amount
                    : bcadd((string) $schedule->scheduled_amount, '500.00', 2);

                app(RegisterPaymentService::class)->register(
                    schedule: $schedule,
                    amount: $paymentAmount,
                    paymentDate: '2026-06-28',
                    paymentMethod: 'bank_transfer',
                    referenceNumber: sprintf('ARIMITSU-PAY-%03d', $sequence),
                    note: $i === 0 ? '有光酒造場 取引デモ入金' : '有光酒造場 取引デモ超過入金',
                    reason: self::REASON,
                );
                $created['payments']++;
            }
        }

        $this->command?->info(sprintf(
            'Created Arimitsu transaction demo data: orders=%d, instructions=%d, picks=%d, draft_shipments=%d, confirmed_shipments=%d, invoices=%d, payments=%d',
            $created['orders'],
            $created['instructions'],
            $created['picks'],
            $created['draft_shipments'],
            $created['confirmed_shipments'],
            $created['invoices'],
            $created['payments'],
        ));
    }

    /**
     * @return Collection<int, Customer>
     */
    private function customers(): Collection
    {
        $customers = Customer::query()
            ->where('is_active', true)
            ->where('customer_code', 'like', 'CLIENT-%')
            ->orderBy('id')
            ->limit(10)
            ->get();

        if ($customers->count() < 4) {
            $customers = Customer::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->limit(10)
                ->get();
        }

        if ($customers->count() < 4) {
            throw new RuntimeException('取引デモデータ作成には有効な取引先が4件以上必要です。');
        }

        return $customers->values();
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        $products = Product::query()
            ->with('productionLots')
            ->where('is_active', true)
            ->where('is_sales_available', true)
            ->where('product_code', 'like', 'AKITORA-%')
            ->whereHas('productionLots', fn ($query) => $query->where('is_active', true)->where('status', 'active'))
            ->orderBy('id')
            ->limit(24)
            ->get();

        if ($products->count() < 8) {
            throw new RuntimeException('取引デモデータ作成には安芸虎の商品・製造ロットが8件以上必要です。');
        }

        return $products->values();
    }

    private function defaultShippingLocation(): StockLocation
    {
        $location = StockLocation::query()
            ->where('is_default_shipping_location', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $location) {
            throw new RuntimeException('既定の出荷拠点が見つかりません。');
        }

        return $location;
    }

    /**
     * @param Collection<int, Product> $products
     * @return array<int, Product>
     */
    private function lineProducts(Collection $products, int $sequence): array
    {
        $start = ($sequence - 1) % ($products->count() - 2);

        return [
            $products[$start],
            $products[$start + 1],
            $products[$start + 2],
        ];
    }

    /**
     * @param array<int, Product> $products
     */
    private function createOrder(int $sequence, Customer $customer, array $products, string $orderDate): SalesOrder
    {
        $quantities = ['3.0000', '4.0000', '2.0000'];
        $lines = [];

        foreach ($products as $index => $product) {
            $lines[] = new CreateSalesOrderLineData(
                productId: $product->id,
                quantity: $quantities[$index],
                unitId: $product->sales_unit_id,
                note: '有光酒造場デモ商品',
            );
        }

        return app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: $orderDate,
            requestedShipmentDate: $orderDate,
            requestedDeliveryDate: $orderDate,
            customerOrderNumber: sprintf('ARI-DEMO-%03d', $sequence),
            sourceType: self::SOURCE_TYPE,
            sourceReference: sprintf('arimitsu-transaction-demo-%03d', $sequence),
            note: '有光酒造場 取引デモ受注',
            reason: self::REASON,
            applyPricing: true,
            awaitingShipmentInstruction: true,
            lines: $lines,
        ));
    }

    private function createInstruction(SalesOrder $order, string $date, StockLocation $stockLocation): ShipmentInstruction
    {
        $order->load('lines');

        return app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: $date,
            scheduledShipmentDate: $date,
            stockLocationId: $stockLocation->id,
            note: '有光酒造場 取引デモ出荷指示',
            reason: self::REASON,
            lines: $order->lines
                ->sortBy('line_no')
                ->map(fn (SalesOrderLine $line) => new CreateShipmentInstructionLineData(
                    salesOrderLineId: $line->id,
                    quantity: (string) $line->remaining_quantity,
                    note: '有光酒造場デモ指示',
                ))
                ->values()
                ->all(),
        ));
    }

    private function createPick(ShipmentInstruction $instruction, string $date, StockLocation $stockLocation): ShipmentPick
    {
        $instruction->load('lines');

        return app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: $date,
            stockLocationId: $stockLocation->id,
            note: '有光酒造場 取引デモピッキング',
            reason: self::REASON,
            lines: $instruction->lines
                ->sortBy('line_no')
                ->map(fn ($line) => new PickShipmentInstructionLineData(
                    shipmentInstructionLineId: $line->id,
                    quantity: (string) $line->quantity,
                    note: '有光酒造場デモピック',
                ))
                ->values()
                ->all(),
        ));
    }

    private function createDraftShipment(ShipmentPick $pick, string $date): ShipmentHeader
    {
        return app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(
            shipmentPickId: $pick->id,
            documentDate: $date,
            billingTargetDate: $date,
            liquorTaxTransferDate: $date,
            note: '有光酒造場 取引デモ出荷伝票',
            reason: self::REASON,
        ));
    }

    private function allocateShipmentLots(ShipmentHeader $shipment, StockLocation $stockLocation): void
    {
        $shipment->load('lines.product.productionLots');

        foreach ($shipment->lines as $line) {
            $lot = $line->product->productionLots
                ->where('is_active', true)
                ->where('status', 'active')
                ->sortBy('id')
                ->first();

            if (! $lot instanceof ProductionLot) {
                throw new RuntimeException(sprintf('商品ID %d の有効な製造ロットが見つかりません。', $line->product_id));
            }

            app(AllocateShipmentLineLotService::class)->allocate(
                shipmentLine: $line,
                productionLot: $lot,
                stockLocation: $stockLocation,
                quantity: (string) $line->quantity,
                reason: self::REASON,
            );
        }
    }
}
