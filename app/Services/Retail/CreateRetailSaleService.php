<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailInventoryMovement;
use App\Models\Retail\RetailInventoryStock;
use App\Models\Retail\RetailProduct;
use App\Models\Retail\RetailSale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRetailSaleService
{
    public function __construct(private readonly RetailBrewerySaleSyncService $brewerySaleSyncService) {}

    /**
     * @param  array<int, array{retail_product_id:int, quantity:numeric-string|int|float}>  $items
     */
    public function create(array $data, array $items): RetailSale
    {
        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($data, $items): RetailSale {
            $products = RetailProduct::query()
                ->whereIn('id', collect($items)->pluck('retail_product_id')->all())
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            if ($products->count() !== count(array_unique(collect($items)->pluck('retail_product_id')->all()))) {
                throw ValidationException::withMessages([
                    'items' => '販売対象外の商品が含まれています。',
                ]);
            }

            $subtotal = 0.0;
            $tax = 0.0;
            $preparedItems = [];

            foreach ($items as $index => $item) {
                $product = $products->get((int) $item['retail_product_id']);
                $quantity = (float) $item['quantity'];
                if (abs($quantity) < 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => '数量は1以上で入力してください。',
                    ]);
                }

                $unitPrice = (float) $product->selling_price;
                $lineSubtotal = round($unitPrice * $quantity, 2);
                $lineTax = round($lineSubtotal * (float) $product->tax_rate, 2);

                $subtotal += $lineSubtotal;
                $tax += $lineTax;
                $preparedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => (float) $product->tax_rate,
                    'tax_amount' => $lineTax,
                    'line_amount' => $lineSubtotal,
                ];
            }

            $sale = RetailSale::query()->create([
                'retail_company_id' => $data['retail_company_id'] ?? null,
                'sale_no' => $this->nextSaleNo(),
                'retail_customer_id' => $data['retail_customer_id'] ?? null,
                'sale_date' => $data['sale_date'],
                'sale_type' => $data['sale_type'],
                'status' => 'posted',
                'payment_status' => $data['sale_type'] === 'credit' ? 'unpaid' : 'paid',
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $subtotal + $tax,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($preparedItems as $prepared) {
                /** @var RetailProduct $product */
                $product = $prepared['product'];
                $saleItem = $sale->items()->create([
                    'retail_product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => $prepared['quantity'],
                    'unit_price' => $prepared['unit_price'],
                    'tax_rate' => $prepared['tax_rate'],
                    'tax_amount' => $prepared['tax_amount'],
                    'line_amount' => $prepared['line_amount'],
                ]);

                $this->moveStock($product, -1 * (float) $prepared['quantity'], $saleItem->id, $sale->sale_no, (bool) ($data['allow_negative_stock'] ?? false));
            }

            $sale = $sale->load(['customer', 'items.product']);

            return $this->brewerySaleSyncService->syncConfirmedSale($sale)->load(['customer', 'items.product']);
        });
    }

    private function nextSaleNo(): string
    {
        $date = Carbon::today()->format('Ymd');
        $prefix = "RS-{$date}-";
        $count = RetailSale::query()->where('sale_no', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function moveStock(RetailProduct $product, float $movementQuantity, int $saleItemId, string $saleNo, bool $allowNegativeStock): void
    {
        $stock = RetailInventoryStock::query()
            ->where('retail_product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $stock = RetailInventoryStock::query()->create([
                'retail_product_id' => $product->id,
                'quantity' => 0,
            ]);
            $stock->refresh();
        }

        $currentQuantity = (float) $stock->quantity;
        $sourceUnavailable = $product->procurement_source === 'brewery'
            && in_array($product->brewery_source_status, ['inactive', 'deleted'], true);
        if ((! $allowNegativeStock || $sourceUnavailable) && $movementQuantity < 0 && $currentQuantity < abs($movementQuantity)) {
            $reason = $sourceUnavailable ? '蔵側販売停止・削除済み商品のため、' : '';
            throw ValidationException::withMessages([
                'items' => "{$product->name} は{$reason}店舗在庫を超えて販売できません。在庫: {$currentQuantity} {$product->stock_unit}",
            ]);
        }

        $stockAfter = $currentQuantity + $movementQuantity;
        $stock->update(['quantity' => $stockAfter]);

        RetailInventoryMovement::query()->create([
            'retail_product_id' => $product->id,
            'movement_type' => 'sale',
            'quantity' => $movementQuantity,
            'stock_after' => $stockAfter,
            'source_type' => 'retail_sale_item',
            'source_id' => $saleItemId,
            'occurred_at' => now(),
            'note' => "販売 {$saleNo}",
        ]);
    }
}
