<?php

namespace App\Services\Retail;

use App\Models\Product;
use App\Models\Retail\RetailSale;
use App\Models\Retail\RetailSaleItem;
use App\Models\Retail\RetailSupplier;
use App\Models\SalesOrder;
use App\Services\SalesOrder\CancelSalesOrderService;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\SalesOrder\UpdateSalesOrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RetailBrewerySaleSyncService
{
    public function __construct(
        private readonly CreateSalesOrderService $createSalesOrderService,
        private readonly CancelSalesOrderService $cancelSalesOrderService,
        private readonly UpdateSalesOrderService $updateSalesOrderService,
    ) {}

    public function syncConfirmedSale(RetailSale $sale): RetailSale
    {
        $sale->loadMissing(['items.product']);
        if ($sale->correction_type === 'credit_note' && $sale->original_retail_sale_id) {
            $originalSale = RetailSale::query()->find($sale->original_retail_sale_id);
            $originalOrder = $originalSale ? $this->originalOrder($originalSale) : null;
            if ($originalOrder && $this->hasClosedInvoice($originalOrder)) {
                return $this->markFailed($sale, '蔵側で請求処理済みのため、赤伝依頼はできません。');
            }
        }

        $lines = $this->linesFromSaleItems($sale->items);

        if ($lines === []) {
            return $this->markNotRequired($sale);
        }

        return $this->createAndLinkOrder(
            sale: $sale,
            sourceType: $sale->correction_type === 'credit_note' ? 'retail_sale_credit_note' : 'retail_sale',
            sourceReference: $sale->sale_no,
            note: $sale->correction_type === 'credit_note'
                ? "小売赤伝 {$sale->sale_no}"
                : "小売販売 {$sale->sale_no}",
            lines: $lines,
            allowNegativeLines: $this->hasNegativeQuantity($lines),
            linkAsCorrection: $sale->correction_type === 'credit_note',
        );
    }

    /**
     * @param  Collection<int, RetailSaleItem>  $items
     */
    public function syncCancellation(RetailSale $sale, Collection $items): RetailSale
    {
        $originalOrder = $this->originalOrder($sale);
        if ($originalOrder && $this->hasClosedInvoice($originalOrder)) {
            return $this->markFailed($sale, '蔵側で請求処理済みのため、取消の赤伝依頼はできません。');
        }

        if ($originalOrder) {
            try {
                $this->cancelSalesOrderService->cancel(
                    $originalOrder,
                    "小売販売取消 {$sale->sale_no}",
                    allowRetailManaged: true,
                );

                return $this->markSynced($sale, 'order_cancelled');
            } catch (Throwable) {
                // 出荷指示済み等で取消できない場合は、差分受注で相殺する。
            }
        }

        $items->loadMissing('product');
        $lines = $this->linesFromSaleItems($items, -1);

        if ($lines === []) {
            return $this->markNotRequired($sale);
        }

        return $this->createAndLinkOrder(
            sale: $sale,
            sourceType: 'retail_sale_correction',
            sourceReference: $sale->sale_no.':cancel',
            note: "小売販売取消 {$sale->sale_no}",
            lines: $lines,
            allowNegativeLines: true,
            linkAsCorrection: true,
        );
    }

    /**
     * @param  Collection<int, RetailSaleItem>  $oldItems
     * @param  Collection<int, RetailSaleItem>  $newItems
     */
    public function syncRevision(RetailSale $sale, Collection $oldItems, Collection $newItems): RetailSale
    {
        $originalOrder = $this->originalOrder($sale);
        if ($originalOrder && $this->hasClosedInvoice($originalOrder)) {
            return $this->markFailed($sale, '蔵側で請求処理済みのため、受注変更はできません。');
        }

        $oldItems->loadMissing('product');
        $newItems->loadMissing('product');

        if ($originalOrder) {
            try {
                $replacementLines = $this->linesFromSaleItems($newItems);
                $existingLines = $originalOrder->lines->keyBy(fn ($line): string => $line->product_id.'|'.$line->unit_id);
                $this->updateSalesOrderService->update(
                    $originalOrder,
                    attributes: [
                        'requested_shipment_date' => $originalOrder->requested_shipment_date?->toDateString(),
                        'requested_delivery_date' => $originalOrder->requested_delivery_date?->toDateString(),
                        'customer_order_number' => $sale->sale_no,
                        'note' => "小売販売変更 {$sale->sale_no}",
                        'work_note' => "小売販売ID: {$sale->id}",
                    ],
                    lines: array_map(function (array $line) use ($existingLines): array {
                        $existing = $existingLines->get($line['brewery_product_id'].'|'.$line['unit_id']);

                        return array_filter([
                            'id' => $existing?->id,
                            'product_id' => $line['brewery_product_id'],
                            'quantity' => $line['quantity'],
                            'unit_id' => $line['unit_id'],
                            'note' => '小売販売変更',
                        ], fn ($value): bool => $value !== null);
                    }, $replacementLines),
                    reason: "小売販売変更 {$sale->sale_no}",
                    allowRetailManaged: true,
                );

                return $this->markSynced($sale, 'order_revised');
            } catch (Throwable) {
                // 出荷指示済み等で直接変更できない場合は、差分受注に切り替える。
            }
        }

        $deltas = [];
        foreach ($this->linesFromSaleItems($oldItems, -1) as $line) {
            $key = $line['brewery_product_id'].'|'.$line['unit_id'];
            $deltas[$key] = $line + ['quantity' => (float) $line['quantity']];
        }
        foreach ($this->linesFromSaleItems($newItems) as $line) {
            $key = $line['brewery_product_id'].'|'.$line['unit_id'];
            if (! isset($deltas[$key])) {
                $deltas[$key] = $line + ['quantity' => 0.0];
            }
            $deltas[$key]['quantity'] += (float) $line['quantity'];
        }

        $lines = collect($deltas)
            ->filter(fn (array $line): bool => abs((float) $line['quantity']) > 0.0001)
            ->map(fn (array $line): array => [
                'brewery_product_id' => $line['brewery_product_id'],
                'unit_id' => $line['unit_id'],
                'quantity' => number_format((float) $line['quantity'], 4, '.', ''),
                'retail_sale_item_id' => null,
            ])
            ->values()
            ->all();

        if ($lines === []) {
            return $sale->forceFill([
                'brewery_sync_status' => 'revision_no_change',
                'brewery_sync_error' => null,
                'brewery_synced_at' => now(),
            ])->save() ? $sale->refresh() : $sale;
        }

        return $this->createAndLinkOrder(
            sale: $sale,
            sourceType: 'retail_sale_correction',
            sourceReference: $sale->sale_no.':revision:'.now()->format('YmdHis'),
            note: "小売販売変更 {$sale->sale_no}",
            lines: $lines,
            allowNegativeLines: true,
            linkAsCorrection: true,
        );
    }

    /**
     * @param  Collection<int, RetailSaleItem>  $items
     * @return array<int, array{brewery_product_id:int, unit_id:int, quantity:string, retail_sale_item_id:int|null}>
     */
    private function linesFromSaleItems(Collection $items, int $sign = 1): array
    {
        $lines = [];

        foreach ($items as $item) {
            $retailProduct = $item->product;
            if (! $retailProduct
                || $retailProduct->procurement_source !== 'brewery'
                || ! $retailProduct->brewery_product_id
                || in_array($retailProduct->brewery_source_status, ['inactive', 'deleted'], true)) {
                continue;
            }

            $breweryProduct = Product::query()->findOrFail($retailProduct->brewery_product_id);
            $unitId = $breweryProduct->sales_unit_id ?? $breweryProduct->base_unit_id;
            if (! $unitId) {
                throw new \RuntimeException("{$breweryProduct->name} の蔵側販売単位が設定されていません。");
            }
            $lines[] = [
                'brewery_product_id' => $breweryProduct->id,
                'unit_id' => (int) $unitId,
                'quantity' => number_format((float) $item->quantity * $sign, 4, '.', ''),
                'retail_sale_item_id' => $item->id,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<int, array{brewery_product_id:int, unit_id:int, quantity:string, retail_sale_item_id:int|null}>  $lines
     */
    private function createAndLinkOrder(
        RetailSale $sale,
        string $sourceType,
        string $sourceReference,
        string $note,
        array $lines,
        bool $allowNegativeLines,
        bool $linkAsCorrection,
    ): RetailSale {
        try {
            $supplier = $this->brewerySupplier();
            $salesOrder = SalesOrder::query()
                ->with('lines')
                ->where('source_type', $sourceType)
                ->where('source_reference', $sourceReference)
                ->first();

            if (! $salesOrder) {
                $salesOrder = $this->createSalesOrderService->create(new CreateSalesOrderData(
                    customerId: (int) $supplier->brewery_partner_id,
                    orderDate: now()->toDateString(),
                    customerOrderNumber: $sourceReference,
                    sourceType: $sourceType,
                    sourceReference: $sourceReference,
                    note: $note,
                    workNote: "小売販売ID: {$sale->id}",
                    applyPricing: true,
                    awaitingShipmentInstruction: false,
                    allowNegativeLines: $allowNegativeLines,
                    lines: array_map(
                        fn (array $line): CreateSalesOrderLineData => new CreateSalesOrderLineData(
                            productId: (int) $line['brewery_product_id'],
                            quantity: $line['quantity'],
                            unitId: (int) $line['unit_id'],
                            note: $note,
                        ),
                        $lines,
                    ),
                ));
            }

            return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($sale, $salesOrder, $lines, $linkAsCorrection): RetailSale {
                $freshSale = RetailSale::query()->lockForUpdate()->findOrFail($sale->id);
                $attributes = [
                    'brewery_sync_status' => $linkAsCorrection ? 'correction_sent' : 'ordered',
                    'brewery_sync_error' => null,
                    'brewery_synced_at' => now(),
                ];

                if ($linkAsCorrection) {
                    $attributes['brewery_correction_sales_order_id'] = $salesOrder->id;
                    $attributes['brewery_correction_order_number'] = $salesOrder->order_number;
                } else {
                    $attributes['brewery_sales_order_id'] = $salesOrder->id;
                    $attributes['brewery_order_number'] = $salesOrder->order_number;
                }

                $freshSale->forceFill($attributes)->save();

                if (! $linkAsCorrection) {
                    foreach ($lines as $index => $line) {
                        if ($line['retail_sale_item_id'] === null) {
                            continue;
                        }
                        $orderLine = $salesOrder->lines->values()->get($index);
                        if ($orderLine) {
                            RetailSaleItem::query()
                                ->whereKey($line['retail_sale_item_id'])
                                ->update(['brewery_sales_order_line_id' => $orderLine->id]);
                        }
                    }
                }

                return $freshSale->refresh();
            });
        } catch (Throwable $exception) {
            $sale->forceFill([
                'brewery_sync_status' => 'failed',
                'brewery_sync_error' => $exception->getMessage(),
            ])->save();

            return $sale->refresh();
        }
    }

    private function brewerySupplier(): RetailSupplier
    {
        $supplier = RetailSupplier::query()
            ->where('supplier_type', 'brewery')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $supplier?->brewery_partner_id) {
            throw new \RuntimeException('会社設定で酒蔵側取引先が設定されていません。');
        }

        return $supplier;
    }

    /**
     * @param  array<int, array{quantity:string}>  $lines
     */
    private function hasNegativeQuantity(array $lines): bool
    {
        foreach ($lines as $line) {
            if (bccomp($line['quantity'], '0', 4) < 0) {
                return true;
            }
        }

        return false;
    }

    private function markNotRequired(RetailSale $sale): RetailSale
    {
        $sale->forceFill([
            'brewery_sync_status' => 'not_required',
            'brewery_sync_error' => null,
            'brewery_synced_at' => now(),
        ])->save();

        return $sale->refresh();
    }

    private function originalOrder(RetailSale $sale): ?SalesOrder
    {
        if (! $sale->brewery_sales_order_id) {
            return null;
        }

        return SalesOrder::query()->with('lines')->find($sale->brewery_sales_order_id);
    }

    private function hasClosedInvoice(SalesOrder $salesOrder): bool
    {
        return DB::table('invoice_lines')
            ->join('invoice_headers', 'invoice_headers.id', '=', 'invoice_lines.invoice_header_id')
            ->join('shipment_lines', 'shipment_lines.id', '=', 'invoice_lines.shipment_line_id')
            ->join('shipment_instruction_lines', 'shipment_instruction_lines.id', '=', 'shipment_lines.shipment_instruction_line_id')
            ->where('shipment_instruction_lines.sales_order_id', $salesOrder->id)
            ->where('invoice_headers.status', 'confirmed')
            ->whereNull('invoice_headers.cancelled_at')
            ->exists();
    }

    private function markSynced(RetailSale $sale, string $status): RetailSale
    {
        $sale->forceFill([
            'brewery_sync_status' => $status,
            'brewery_sync_error' => null,
            'brewery_synced_at' => now(),
        ])->save();

        return $sale->refresh();
    }

    private function markFailed(RetailSale $sale, string $message): RetailSale
    {
        $sale->forceFill([
            'brewery_sync_status' => 'failed',
            'brewery_sync_error' => $message,
            'brewery_synced_at' => now(),
        ])->save();

        return $sale->refresh();
    }
}
