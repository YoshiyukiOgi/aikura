<?php

namespace App\Services\Retail;

use App\Models\Product;
use App\Models\Retail\RetailProduct;
use App\Models\Retail\RetailPurchaseOrder;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CreateRetailPurchaseOrderSuggestionsService
{
    public function __construct(
        private readonly RetailDocumentNumber $numbers,
        private readonly CreateSalesOrderService $createSalesOrderService,
    ) {}

    /**
     * @return Collection<int, RetailPurchaseOrder>
     */
    public function create(): Collection
    {
        return \DB::connection(config('retail.database.connection', 'retail'))->transaction(function (): Collection {
            $products = RetailProduct::query()
                ->with(['supplier', 'inventoryStock'])
                ->where('is_active', true)
                ->whereNotNull('reorder_point')
                ->whereNotNull('reorder_quantity')
                ->get()
                ->filter(fn (RetailProduct $product): bool => (float) ($product->inventoryStock?->quantity ?? 0) <= (float) $product->reorder_point);

            if ($products->isEmpty()) {
                throw ValidationException::withMessages(['products' => '発注案の対象商品がありません。']);
            }

            return $products
                ->groupBy('retail_supplier_id')
                ->map(function (Collection $supplierProducts): RetailPurchaseOrder {
                    /** @var RetailProduct $first */
                    $first = $supplierProducts->first();
                    $supplier = $first->supplier;
                    $subtotal = 0.0;
                    $tax = 0.0;

                    $order = RetailPurchaseOrder::query()->create([
                        'purchase_order_no' => $this->numbers->purchaseOrderNo(),
                        'retail_supplier_id' => $supplier->id,
                        'supplier_type' => $supplier->supplier_type,
                        'order_route' => $supplier->supplier_type === 'brewery' ? 'brewery_api' : 'external_manual',
                        'status' => 'draft',
                        'subtotal_amount' => 0,
                        'tax_amount' => 0,
                        'total_amount' => 0,
                        'note' => '在庫下限から自動作成',
                    ]);

                    foreach ($supplierProducts as $product) {
                        $quantity = (float) $product->reorder_quantity;
                        $lineAmount = round((float) $product->cost_price * $quantity, 2);
                        $lineTax = round($lineAmount * (float) $product->tax_rate, 2);
                        $subtotal += $lineAmount;
                        $tax += $lineTax;

                        $order->lines()->create([
                            'retail_product_id' => $product->id,
                            'description' => $product->name,
                            'quantity' => $quantity,
                            'unit_cost' => $product->cost_price,
                            'tax_amount' => $lineTax,
                            'line_amount' => $lineAmount,
                        ]);
                    }

                    $order->update([
                        'subtotal_amount' => $subtotal,
                        'tax_amount' => $tax,
                        'total_amount' => $subtotal + $tax,
                    ]);

                    return $order->load(['supplier', 'lines.product']);
                })
                ->values();
        });
    }

    public function sendToBrewery(RetailPurchaseOrder $order): RetailPurchaseOrder
    {
        $order->load(['supplier', 'lines.product']);

        if ($order->supplier_type !== 'brewery' || $order->order_route !== 'brewery_api') {
            throw ValidationException::withMessages(['order' => '蔵商品向けの発注だけ蔵API送信できます。']);
        }

        if ($order->status !== 'draft') {
            throw ValidationException::withMessages(['order' => '下書き状態の発注だけ送信できます。']);
        }

        if ($order->brewery_sales_order_id) {
            throw ValidationException::withMessages(['order' => 'この発注は既に蔵側受注へ連携済みです。']);
        }

        if (! $order->supplier?->brewery_partner_id) {
            throw ValidationException::withMessages(['order' => '蔵側取引先IDが仕入先に設定されていません。']);
        }

        $lines = [];
        foreach ($order->lines as $line) {
            $retailProduct = $line->product;
            if (! $retailProduct?->brewery_product_id) {
                throw ValidationException::withMessages(['order' => "{$line->description} に蔵側商品IDが設定されていません。"]);
            }

            $breweryProduct = Product::query()->findOrFail($retailProduct->brewery_product_id);
            $unitId = $breweryProduct->sales_unit_id ?? $breweryProduct->base_unit_id;
            if (! $unitId) {
                throw ValidationException::withMessages(['order' => "{$breweryProduct->name} の蔵側販売単位が設定されていません。"]);
            }

            $lines[] = new CreateSalesOrderLineData(
                productId: $breweryProduct->id,
                quantity: (string) $line->quantity,
                unitId: (int) $unitId,
                note: "小売発注 {$order->purchase_order_no}",
            );
        }

        try {
            $salesOrder = $this->createSalesOrderService->create(new CreateSalesOrderData(
                customerId: (int) $order->supplier->brewery_partner_id,
                orderDate: now()->toDateString(),
                customerOrderNumber: $order->purchase_order_no,
                sourceType: 'retail_purchase_order',
                sourceReference: $order->purchase_order_no,
                note: '小売販売システムからの自動発注',
                workNote: "小売発注ID: {$order->id}",
                reason: '小売発注案から蔵側受注を自動作成',
                applyPricing: false,
                awaitingShipmentInstruction: false,
                lines: $lines,
            ));
        } catch (\Throwable $exception) {
            $order->update([
                'status' => 'api_failed',
                'brewery_api_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $order->update([
            'status' => 'ordered',
            'ordered_at' => now(),
            'brewery_sales_order_id' => $salesOrder->id,
            'brewery_api_error' => null,
            'note' => trim(($order->note ? $order->note."\n" : '')."蔵API送信済み: {$salesOrder->order_number}"),
        ]);

        return $order->refresh()->load(['supplier', 'lines.product']);
    }
}
