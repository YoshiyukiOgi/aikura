<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use Illuminate\Database\Seeder;

class DemoEditableSalesOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (SalesOrder::query()->where('source_reference', 'screen-demo-editable')->exists()) {
            return;
        }

        $customer = Customer::query()->where('customer_code', 'DEMO-CUST-002')->firstOrFail();
        $product = Product::query()->where('product_code', 'DEMO-PROD-002')->firstOrFail();
        $unit = Unit::query()->findOrFail($product->sales_unit_id);

        app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-22',
            requestedDeliveryDate: '2026-06-27',
            customerOrderNumber: 'DEMO-EDIT-001',
            sourceType: 'demo_screen',
            sourceReference: 'screen-demo-editable',
            note: '画面で商品・数量を編集するための検証受注',
            reason: 'screen demo data',
            applyPricing: true,
            lines: [new CreateSalesOrderLineData($product->id, '3.0000', $unit->id)],
        ));
    }
}
