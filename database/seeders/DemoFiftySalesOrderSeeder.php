<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoFiftySalesOrderSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,
            DemoCustomerSeeder::class,
            DemoProductSeeder::class,
        ]);

        $customers = Customer::query()
            ->where('is_active', true)
            ->where('customer_code', 'like', 'DEMO-CUST-%')
            ->orderBy('customer_code')
            ->get();

        $products = Product::query()
            ->where('is_active', true)
            ->where('is_sales_available', true)
            ->where('product_code', 'like', 'DEMO-PROD-%')
            ->orderBy('product_code')
            ->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            throw new RuntimeException('Demo customers or products were not created.');
        }

        foreach (range(1, 50) as $number) {
            $reference = sprintf('demo-50-sales-order-%02d', $number);

            if (SalesOrder::query()->where('source_reference', $reference)->exists()) {
                continue;
            }

            $customer = $customers[($number - 1) % $customers->count()];
            $firstProduct = $products[($number - 1) % $products->count()];
            $secondProduct = $products[($number + 6) % $products->count()];
            $thirdProduct = $products[($number + 13) % $products->count()];

            $baseDate = CarbonImmutable::create(2026, 6, 1);
            $orderDate = $baseDate->addDays(($number - 1) % 29)->toDateString();
            $shipmentDate = $baseDate->addDays(($number % 29) + 1)->toDateString();
            $deliveryDate = $baseDate->addDays(($number % 29) + 2)->toDateString();

            app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
                customerId: $customer->id,
                orderDate: $orderDate,
                requestedShipmentDate: $shipmentDate,
                requestedDeliveryDate: $deliveryDate,
                billingTargetDate: $orderDate,
                customerOrderNumber: sprintf('DEMO50-%04d', $number),
                sourceType: 'demo_50',
                sourceReference: $reference,
                note: '画面確認用の50件デモ受注データ',
                reason: 'demo fifty sales order seeder',
                applyPricing: true,
                awaitingShipmentInstruction: $number % 7 === 0,
                lines: [
                    new CreateSalesOrderLineData($firstProduct->id, $this->quantity($number, 6, 1), $this->salesUnitId($firstProduct), 'デモ明細1'),
                    new CreateSalesOrderLineData($secondProduct->id, $this->quantity($number, 4, 2), $this->salesUnitId($secondProduct), 'デモ明細2'),
                    new CreateSalesOrderLineData($thirdProduct->id, $this->quantity($number, 3, 3), $this->salesUnitId($thirdProduct), 'デモ明細3'),
                ],
            ));
        }
    }

    private function salesUnitId(Product $product): int
    {
        return Unit::query()->findOrFail($product->sales_unit_id)->id;
    }

    private function quantity(int $number, int $cycle, int $multiplier): string
    {
        return number_format((($number % $cycle) + 1) * $multiplier, 4, '.', '');
    }
}
