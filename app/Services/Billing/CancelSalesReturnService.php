<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\SalesReturnException;
use App\Models\SalesReturnHeader;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\EnsureStockPeriodIsOpenService;
use Illuminate\Support\Facades\DB;

class CancelSalesReturnService
{
    public function __construct(
        private readonly CancelInvoiceService $cancelInvoiceService,
        private readonly EnsureStockPeriodIsOpenService $ensureStockPeriodIsOpenService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function cancel(SalesReturnHeader $salesReturn, string $reason): SalesReturnHeader
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw SalesReturnException::emptyCancelReason();
        }

        return DB::transaction(function () use ($salesReturn, $reason): SalesReturnHeader {
            $salesReturn = SalesReturnHeader::query()
                ->with(['creditInvoiceHeader', 'lines.lots'])
                ->lockForUpdate()
                ->findOrFail($salesReturn->id);

            if ($salesReturn->status === 'cancelled' || $salesReturn->cancelled_at !== null) {
                throw SalesReturnException::alreadyCancelled($salesReturn->id);
            }

            $beforeStatus = $salesReturn->status;

            if ($salesReturn->creditInvoiceHeader !== null && $salesReturn->creditInvoiceHeader->status !== 'cancelled') {
                $this->cancelInvoiceService->cancel($salesReturn->creditInvoiceHeader, $reason);
            }

            $movementIds = $salesReturn->lines
                ->flatMap(fn ($line) => collect([$line->stock_movement_id])
                    ->merge($line->lots->pluck('stock_movement_id')))
                ->filter()
                ->unique()
                ->values();

            $movements = StockMovement::query()
                ->whereIn('id', $movementIds)
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->get();

            foreach ($movements as $movement) {
                $this->ensureStockPeriodIsOpenService->ensureOpen($movement->movement_date->toDateString());
                $movement->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_reason' => $reason,
                ])->save();
            }

            $salesReturn->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ])->save();

            $this->auditLogService->record(new AuditLogData(
                event: 'sales_return.cancelled',
                auditable: $salesReturn->refresh(),
                beforeValues: ['status' => $beforeStatus],
                afterValues: [
                    'status' => 'cancelled',
                    'cancelled_at' => $salesReturn->cancelled_at?->toISOString(),
                    'cancelled_reason' => $salesReturn->cancelled_reason,
                ],
                reason: $reason,
            ));

            return $salesReturn->refresh()->load(['customer', 'creditInvoiceHeader', 'lines.lots']);
        });
    }
}
