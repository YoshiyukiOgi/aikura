<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\SalesOrder;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\Shipment\AllocateShipmentLineLotService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentFromInstructionService;
use App\Services\Shipment\CreateDraftShipmentFromPickData;
use App\Services\Shipment\CreateDraftShipmentFromPickService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use Illuminate\Database\Seeder;

class DemoEditableBillingSeeder extends Seeder
{
    public function run(): void
    {
        if (SalesOrder::query()->where('source_reference', 'screen-demo-billing-editable')->exists()) {
            return;
        }

        $customer = Customer::query()->where('customer_code', 'DEMO-CUST-003')->firstOrFail();
        $product = Product::query()->where('product_code', 'DEMO-PROD-003')->firstOrFail();
        $unit = Unit::query()->findOrFail($product->sales_unit_id);
        $location = StockLocation::query()->where('code', 'main_brewery')->firstOrFail();
        $lot = ProductionLot::query()->where('lot_code', 'DEMO-LOT-DEMO-PROD-003-202606')->firstOrFail();

        $order = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-23',
            requestedDeliveryDate: '2026-06-26',
            customerOrderNumber: 'DEMO-BILL-001',
            sourceType: 'demo_screen',
            sourceReference: 'screen-demo-billing-editable',
            note: '請求書作成・確定・入金登録を画面で確認するためのデモ出荷',
            reason: 'operation test demo data',
            applyPricing: true,
            lines: [new CreateSalesOrderLineData($product->id, '4.0000', $unit->id)],
        ));

        $instruction = app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-23',
            scheduledShipmentDate: '2026-06-24',
            stockLocationId: $location->id,
            note: '請求画面確認用の出荷指示',
            reason: 'operation test demo data',
            lines: [new CreateShipmentInstructionLineData($order->lines->first()->id, '4.0000')],
        ));

        $pickingShipment = app(CreateDraftShipmentFromInstructionService::class)->create($instruction);
        app(AllocateShipmentLineLotService::class)->allocate(
            shipmentLine: $pickingShipment->lines->firstOrFail(),
            productionLot: $lot,
            stockLocation: $location,
            quantity: '4.0000',
            reason: 'operation test demo data',
        );

        $pick = app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-24',
            stockLocationId: $location->id,
            note: '請求画面確認用の品出',
            reason: 'operation test demo data',
            lines: [new PickShipmentInstructionLineData($instruction->lines->first()->id, '4.0000')],
        ));

        $shipment = app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(
            shipmentPickId: $pick->id,
            documentDate: '2026-06-24',
            billingTargetDate: '2026-06-24',
            note: '請求書作成前のデモ出荷',
            reason: 'operation test demo data',
        ));

        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment, 'operation test demo data');
        app(ConfirmShipmentService::class)->confirm($shipment, 'operation test demo data');
    }
}
