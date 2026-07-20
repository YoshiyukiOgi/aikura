<?php

namespace App\Services\SalesOrder;

use App\Exceptions\SalesOrder\SalesOrderException;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class CancelSalesOrderService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function cancel(SalesOrder $salesOrder, string $reason): SalesOrder
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw SalesOrderException::emptyCancellationReason();
        }

        return DB::transaction(function () use ($salesOrder, $reason): SalesOrder {
            $salesOrder = SalesOrder::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($salesOrder->id);

            if ($salesOrder->status === 'cancelled' || $salesOrder->cancelled_at !== null) {
                throw SalesOrderException::alreadyCancelled($salesOrder->id);
            }

            $hasInstructed = $salesOrder->lines->contains(
                fn (SalesOrderLine $line): bool => bccomp((string) $line->remaining_quantity, (string) $line->quantity, 4) < 0,
            );

            if ($hasInstructed) {
                throw SalesOrderException::alreadyInstructed($salesOrder->id);
            }

            $salesOrder->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ]);

            $this->auditLogService->record(new AuditLogData(
                event: 'sales_order.cancelled',
                auditable: $salesOrder->refresh(),
                beforeValues: ['status' => 'received'],
                afterValues: [
                    'status' => 'cancelled',
                    'cancelled_at' => $salesOrder->cancelled_at?->toISOString(),
                    'cancelled_reason' => $salesOrder->cancelled_reason,
                ],
                reason: $reason,
            ));

            return $salesOrder->refresh()->load(['customer', 'lines.product', 'lines.unit']);
        });
    }
}
