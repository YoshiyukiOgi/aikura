<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Billing\CreatePaymentScheduleService;
use App\Services\Billing\RegisterPaymentService;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
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

class DemoTransactionSeeder extends Seeder
{
    public function run(): void
    {
        if (SalesOrder::query()->where('source_type', 'demo_screen')->exists()) {
            return;
        }

        $customer = Customer::query()->where('customer_code', 'DEMO-CUST-001')->firstOrFail();
        $product = Product::query()->where('product_code', 'DEMO-PROD-001')->firstOrFail();
        $unit = Unit::query()->findOrFail($product->sales_unit_id);
        $date = '2026-06-20';

        $order = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: $date,
            requestedDeliveryDate: '2026-06-25',
            customerOrderNumber: 'DEMO-ORDER-001',
            sourceType: 'demo_screen',
            sourceReference: 'screen-demo-flow',
            note: '画面確認用の検証受注',
            reason: 'screen demo data',
            applyPricing: true,
            lines: [new CreateSalesOrderLineData($product->id, '6.0000', $unit->id)],
        ));

        $instruction = app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: $date,
            scheduledShipmentDate: '2026-06-22',
            note: '画面確認用の出荷指示',
            reason: 'screen demo data',
            lines: [new CreateShipmentInstructionLineData($order->lines->first()->id, '6.0000')],
        ));

        $pick = app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-21',
            note: '画面確認用のピッキング',
            reason: 'screen demo data',
            lines: [new PickShipmentInstructionLineData($instruction->lines->first()->id, '6.0000')],
        ));

        $shipment = app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(
            shipmentPickId: $pick->id,
            documentDate: '2026-06-21',
            billingTargetDate: '2026-06-21',
            note: '画面確認用の出荷',
            reason: 'screen demo data',
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment, 'screen demo data');
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment, 'screen demo data');

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-06-21',
            dueDate: '2026-07-31',
            note: '画面確認用の請求',
            reason: 'screen demo data',
            shipmentHeaderIds: [$shipment->id],
        ));
        $invoice = app(ConfirmInvoiceService::class)->confirm($invoice, 'screen demo data');
        $schedule = app(CreatePaymentScheduleService::class)->create($invoice, 'screen demo data');
        app(RegisterPaymentService::class)->register($schedule, '1000.00', '2026-06-22', reason: 'screen demo data');
    }
}
