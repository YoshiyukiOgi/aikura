<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailPurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\SalesOrder\CancelSalesOrderService;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelRetailPurchaseOrderService
{
    public function __construct(
        private readonly CancelSalesOrderService $cancelSalesOrderService,
        private readonly CreateSalesOrderService $createSalesOrderService,
    ) {
    }

    public function deleteDraft(RetailPurchaseOrder $order): void
    {
        DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($order): void {
            $order = RetailPurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== 'draft' || $order->brewery_sales_order_id !== null) {
                throw ValidationException::withMessages(['order' => '蔵API未送信の下書き発注だけ削除できます。']);
            }

            $order->delete();
        });
    }

    public function cancel(RetailPurchaseOrder $order, string $reason): RetailPurchaseOrder
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => '取消理由を入力してください。']);
        }

        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($order, $reason): RetailPurchaseOrder {
            $order = RetailPurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages(['order' => 'この発注は既に取消済みです。']);
            }

            if ($order->status !== 'ordered') {
                throw ValidationException::withMessages(['order' => '蔵API送信済みの発注だけ取消できます。']);
            }

            $attributes = [
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ];

            if ($order->brewery_sales_order_id === null) {
                $order->update($attributes + ['brewery_cancel_status' => 'not_required']);

                return $order->refresh();
            }

            $salesOrder = SalesOrder::query()
                ->with('lines')
                ->findOrFail($order->brewery_sales_order_id);

            $hasInstructedLines = $salesOrder->lines->contains(
                fn (SalesOrderLine $line): bool => bccomp((string) $line->remaining_quantity, (string) $line->quantity, 4) < 0,
            );

            if (! $hasInstructedLines) {
                $this->cancelSalesOrderService->cancel(
                    $salesOrder,
                    "小売発注取消 {$order->purchase_order_no}: {$reason}",
                    true,
                );

                $order->update($attributes + [
                    'brewery_cancel_status' => 'cancelled',
                    'brewery_cancellation_sales_order_id' => null,
                    'brewery_cancel_error' => null,
                    'brewery_cancelled_at' => now(),
                ]);

                return $order->refresh();
            }

            $correction = $this->createSalesOrderService->create(new CreateSalesOrderData(
                customerId: $salesOrder->customer_id,
                orderDate: now()->toDateString(),
                settlementReceivableCategoryId: $salesOrder->settlement_receivable_category_id,
                billingTargetDate: now()->toDateString(),
                customerOrderNumber: $order->purchase_order_no,
                sourceType: 'retail_purchase_order_cancellation',
                sourceReference: $order->purchase_order_no.':cancel',
                note: "小売発注取消: {$order->purchase_order_no}",
                workNote: "取消元受注: {$salesOrder->order_number}",
                reason: "小売発注取消に伴うマイナス訂正受注: {$reason}",
                allowNegativeLines: true,
                lines: $salesOrder->lines->map(fn (SalesOrderLine $line): CreateSalesOrderLineData => new CreateSalesOrderLineData(
                    productId: $line->product_id,
                    quantity: bcmul((string) $line->quantity, '-1', 4),
                    unitId: $line->unit_id,
                    note: "小売発注取消 {$order->purchase_order_no}",
                ))->all(),
            ));

            $order->update($attributes + [
                'brewery_cancel_status' => 'correction_sent',
                'brewery_cancellation_sales_order_id' => $correction->id,
                'brewery_cancel_error' => null,
                'brewery_cancelled_at' => now(),
            ]);

            return $order->refresh();
        });
    }
}
