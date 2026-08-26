<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailAdjustmentEvent;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCompanySetting;
use App\Models\Retail\RetailInventoryMovement;
use App\Models\Retail\RetailInventoryStock;
use App\Models\Retail\RetailProduct;
use App\Models\Retail\RetailSale;
use App\Models\Retail\RetailSaleItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustRetailSaleService
{
    public function __construct(private readonly RetailBrewerySaleSyncService $brewerySaleSyncService) {}

    /**
     * @param array<int, array{retail_product_id:int, quantity:numeric-string|int|float}> $items
     */
    public function revise(RetailSale $sale, array $data, array $items): RetailSale
    {
        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($sale, $data, $items): RetailSale {
            $sale = $this->lockSale($sale);
            $this->assertEditableBeforeClose($sale);

            $oldQuantity = $this->itemQuantity($sale->items);
            $oldTotal = (float) $sale->total_amount;
            $oldItems = $sale->items;

            foreach ($oldItems as $item) {
                if ($item->retail_product_id) {
                    $this->moveStock((int) $item->retail_product_id, (float) $item->quantity, 'sale_revise_reversal', 'retail_sale_item', $item->id, "販売変更戻し {$sale->sale_no}");
                }
            }
            $sale->items()->delete();

            $prepared = $this->prepareItems($items);
            $allowNegativeStock = (bool) ($data['allow_negative_stock'] ?? false)
                || $this->allowsNegativeStockForSale($sale);
            foreach ($prepared['items'] as $preparedItem) {
                $item = $sale->items()->create($preparedItem['attributes']);
                $this->moveStock((int) $preparedItem['product']->id, -1 * (float) $preparedItem['attributes']['quantity'], 'sale_revise', 'retail_sale_item', $item->id, "販売変更 {$sale->sale_no}", $allowNegativeStock);
            }

            $sale->forceFill([
                'sale_date' => $data['sale_date'],
                'sale_type' => $data['sale_type'],
                'retail_customer_id' => $data['retail_customer_id'] ?? null,
                'payment_status' => $data['sale_type'] === 'credit' ? 'unpaid' : 'paid',
                'subtotal_amount' => $prepared['subtotal'],
                'tax_amount' => $prepared['tax'],
                'total_amount' => $prepared['subtotal'] + $prepared['tax'],
                'note' => $data['note'] ?? $sale->note,
                'correction_reason' => $data['reason'],
                'status' => 'revised',
                'revised_at' => now(),
            ])->save();

            $newItems = $sale->items()->with('product')->get();
            $this->event($sale, null, 'sale_revised', $this->itemQuantity($newItems) - $oldQuantity, (float) $sale->total_amount - $oldTotal, $data['reason']);

            if ($sale->brewery_sales_order_id) {
                $this->brewerySaleSyncService->syncRevision($sale, $oldItems, $newItems);
            }

            return $sale->refresh()->load(['customer', 'items.product']);
        });
    }

    public function cancel(RetailSale $sale, string $reason): RetailSale
    {
        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($sale, $reason): RetailSale {
            $sale = $this->lockSale($sale);
            $this->assertEditableBeforeClose($sale);
            $items = $sale->items;

            foreach ($items as $item) {
                if ($item->retail_product_id) {
                    $this->moveStock((int) $item->retail_product_id, (float) $item->quantity, 'sale_cancel', 'retail_sale_item', $item->id, "販売取消 {$sale->sale_no}");
                }
            }

            $sale->forceFill([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'correction_reason' => $reason,
                'cancelled_at' => now(),
            ])->save();

            $this->event($sale, null, 'sale_cancelled', -1 * $this->itemQuantity($items), -1 * (float) $sale->total_amount, $reason);

            if ($sale->brewery_sales_order_id) {
                $this->brewerySaleSyncService->syncCancellation($sale, $items);
            }

            return $sale->refresh()->load(['customer', 'items.product']);
        });
    }

    public function createCreditNote(RetailSale $sale, string $reason): RetailSale
    {
        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($sale, $reason): RetailSale {
            $sale = $this->lockSale($sale);
            $sale->loadMissing(['items.product', 'invoiceLines']);

            if ($sale->status === 'cancelled') {
                throw ValidationException::withMessages(['sale' => '取消済みの販売から赤伝は作成できません。']);
            }

            if ($sale->invoiceLines->isEmpty()) {
                throw ValidationException::withMessages(['sale' => '締め後の販売だけ赤伝を作成できます。']);
            }

            if ($sale->correctionSales()->where('correction_type', 'credit_note')->exists()) {
                throw ValidationException::withMessages(['sale' => 'この販売の赤伝は既に作成済みです。']);
            }

            $credit = RetailSale::query()->create([
                'retail_company_id' => $sale->retail_company_id,
                'sale_no' => $this->nextSaleNo('RCN'),
                'retail_customer_id' => $sale->retail_customer_id,
                'sale_date' => now()->toDateString(),
                'sale_type' => $sale->sale_type,
                'status' => 'posted',
                'original_retail_sale_id' => $sale->id,
                'correction_type' => 'credit_note',
                'payment_status' => $sale->sale_type === 'credit' ? 'unpaid' : 'paid',
                'subtotal_amount' => -1 * (float) $sale->subtotal_amount,
                'tax_amount' => -1 * (float) $sale->tax_amount,
                'total_amount' => -1 * (float) $sale->total_amount,
                'note' => "赤伝: {$sale->sale_no}",
                'correction_reason' => $reason,
            ]);

            foreach ($sale->items as $item) {
                $creditItem = $credit->items()->create([
                    'retail_product_id' => $item->retail_product_id,
                    'description' => '赤伝 '.$item->description,
                    'quantity' => -1 * (float) $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => -1 * (float) $item->tax_amount,
                    'line_amount' => -1 * (float) $item->line_amount,
                ]);

                if ($item->retail_product_id) {
                    $this->moveStock((int) $item->retail_product_id, (float) $item->quantity, 'credit_note', 'retail_sale_item', $creditItem->id, "赤伝 {$credit->sale_no}");
                }
            }

            $sale->forceFill(['closed_at' => $sale->closed_at ?: now()])->save();
            $this->event($sale, $credit, 'credit_note_issued', -1 * $this->itemQuantity($sale->items), -1 * (float) $sale->total_amount, $reason);

            if ($sale->brewery_sales_order_id) {
                $this->brewerySaleSyncService->syncConfirmedSale($credit->load(['items.product']));
            }

            return $credit->refresh()->load(['customer', 'items.product', 'originalSale']);
        });
    }

    private function lockSale(RetailSale $sale): RetailSale
    {
        return RetailSale::query()->with(['items', 'deliveries', 'invoiceLines'])->lockForUpdate()->findOrFail($sale->id);
    }

    private function assertEditableBeforeClose(RetailSale $sale): void
    {
        if ($sale->status === 'cancelled') {
            throw ValidationException::withMessages(['sale' => '取消済みの販売は変更できません。']);
        }

        if ($sale->invoiceLines->isNotEmpty()) {
            throw ValidationException::withMessages(['sale' => '締め後の販売は変更・取消できません。赤伝を作成してください。']);
        }

        if ($sale->deliveries->where('status', 'issued')->isNotEmpty()) {
            throw ValidationException::withMessages(['sale' => '納品書作成済みの販売は先に納品書の扱いを確認してください。']);
        }

        if ($sale->correction_type === 'credit_note') {
            throw ValidationException::withMessages(['sale' => '赤伝は変更・取消できません。']);
        }
    }

    /**
     * @param array<int, array{retail_product_id:int, quantity:numeric-string|int|float}> $items
     * @return array{subtotal:float, tax:float, items:array<int, array{product:RetailProduct, attributes:array<string, mixed>}>}
     */
    private function prepareItems(array $items): array
    {
        $products = RetailProduct::query()
            ->whereIn('id', collect($items)->pluck('retail_product_id')->all())
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $subtotal = 0.0;
        $tax = 0.0;
        $prepared = [];

        foreach ($items as $index => $item) {
            $product = $products->get((int) $item['retail_product_id']);
            if (! $product) {
                throw ValidationException::withMessages(["items.{$index}.retail_product_id" => '販売対象外の商品が含まれています。']);
            }

            $quantity = (float) $item['quantity'];
            if ($quantity <= 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => '数量は1以上で入力してください。']);
            }

            $lineAmount = round((float) $product->selling_price * $quantity, 2);
            $lineTax = round($lineAmount * (float) $product->tax_rate, 2);
            $subtotal += $lineAmount;
            $tax += $lineTax;

            $prepared[] = [
                'product' => $product,
                'attributes' => [
                    'retail_product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $product->selling_price,
                    'tax_rate' => $product->tax_rate,
                    'tax_amount' => $lineTax,
                    'line_amount' => $lineAmount,
                ],
            ];
        }

        return ['subtotal' => $subtotal, 'tax' => $tax, 'items' => $prepared];
    }

    private function moveStock(int $productId, float $quantity, string $movementType, string $sourceType, int $sourceId, string $note, bool $allowNegativeStock = false): void
    {
        $stock = RetailInventoryStock::query()->where('retail_product_id', $productId)->lockForUpdate()->firstOrCreate(
            ['retail_product_id' => $productId],
            ['quantity' => 0],
        );
        $current = (float) $stock->quantity;
        $after = $current + $quantity;
        $product = RetailProduct::query()->find($productId);
        $sourceUnavailable = $product?->procurement_source === 'brewery'
            && in_array($product->brewery_source_status, ['inactive', 'deleted'], true);
        if ($quantity < 0 && $after < 0 && (! $allowNegativeStock || $sourceUnavailable)) {
            $reason = $sourceUnavailable ? '蔵側販売停止・削除済み商品のため、' : '';
            throw ValidationException::withMessages([
                'items' => ($product?->name ?? '商品')." は{$reason}店舗在庫を超えて変更できません。在庫: {$current} ".($product?->stock_unit ?? '個'),
            ]);
        }

        $stock->update(['quantity' => $after]);
        RetailInventoryMovement::query()->create([
            'retail_product_id' => $productId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'stock_after' => $after,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'occurred_at' => now(),
            'note' => $note,
        ]);
    }

    private function allowsNegativeStockForSale(RetailSale $sale): bool
    {
        $companyKey = RetailCompany::query()
            ->whereKey($sale->retail_company_id)
            ->value('company_key');

        if (! is_string($companyKey)) {
            return false;
        }

        return RetailCompanySetting::query()
            ->where('company_key', $companyKey)
            ->value('inventory_sales_policy') === 'allow_negative_order';
    }

    /**
     * @param Collection<int, RetailSaleItem> $items
     */
    private function itemQuantity(Collection $items): float
    {
        return (float) $items->sum(fn (RetailSaleItem $item): float => (float) $item->quantity);
    }

    private function event(RetailSale $sale, ?RetailSale $relatedSale, string $eventType, float $quantityDelta, float $amountDelta, string $reason): void
    {
        RetailAdjustmentEvent::query()->create([
            'retail_sale_id' => $sale->id,
            'related_retail_sale_id' => $relatedSale?->id,
            'event_type' => $eventType,
            'brewery_sync_status' => 'pending_review',
            'quantity_delta' => $quantityDelta,
            'amount_delta' => $amountDelta,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }

    private function nextSaleNo(string $prefixCode): string
    {
        $prefix = $prefixCode.'-'.now()->format('Ymd').'-';
        $count = RetailSale::query()->where('sale_no', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
