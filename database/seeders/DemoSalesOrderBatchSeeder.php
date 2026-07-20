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

class DemoSalesOrderBatchSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(20)
            ->get();
        $products = Product::query()
            ->where('is_active', true)
            ->where('is_sales_available', true)
            ->orderBy('id')
            ->limit(12)
            ->get();

        foreach (range(1, 20) as $number) {
            $reference = sprintf('demo-batch-sales-order-%02d', $number);
            if (SalesOrder::query()->where('source_reference', $reference)->exists()) {
                continue;
            }

            $customer = $customers[($number - 1) % $customers->count()];
            $firstProduct = $products[($number - 1) % $products->count()];
            $secondProduct = $products[$number % $products->count()];
            $firstUnit = Unit::query()->findOrFail($firstProduct->sales_unit_id);
            $secondUnit = Unit::query()->findOrFail($secondProduct->sales_unit_id);

            app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
                customerId: $customer->id,
                orderDate: now()->subDays($number % 7)->toDateString(),
                requestedShipmentDate: now()->addDays(($number % 5) + 1)->toDateString(),
                requestedDeliveryDate: now()->addDays(($number % 5) + 2)->toDateString(),
                customerOrderNumber: sprintf('DEMO-BATCH-%04d', $number),
                sourceType: 'demo_batch',
                sourceReference: $reference,
                note: '画面確認用の受注データ',
                reason: 'demo batch seeder',
                applyPricing: true,
                lines: [
                    new CreateSalesOrderLineData($firstProduct->id, (string) (($number % 6 + 1) * 6), $firstUnit->id),
                    new CreateSalesOrderLineData($secondProduct->id, (string) (($number % 4 + 1) * 3), $secondUnit->id),
                ],
            ));
        }
    }
}
