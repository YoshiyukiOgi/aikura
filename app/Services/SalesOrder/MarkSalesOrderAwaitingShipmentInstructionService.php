<?php

namespace App\Services\SalesOrder;

use App\Exceptions\SalesOrder\SalesOrderException;
use App\Models\SalesOrder;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class MarkSalesOrderAwaitingShipmentInstructionService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function mark(SalesOrder $salesOrder): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder): SalesOrder {
            $salesOrder = SalesOrder::query()->lockForUpdate()->findOrFail($salesOrder->id);

            if ($salesOrder->status !== 'received') {
                throw SalesOrderException::notEditable($salesOrder->id, $salesOrder->status);
            }

            if (! $salesOrder->awaiting_shipment_instruction) {
                $salesOrder->update(['awaiting_shipment_instruction' => true]);
                $this->auditLogService->record(new AuditLogData(
                    event: 'sales_order.awaiting_shipment_instruction_marked',
                    auditable: $salesOrder,
                    beforeValues: ['awaiting_shipment_instruction' => false],
                    afterValues: ['awaiting_shipment_instruction' => true],
                ));
            }

            return $salesOrder->refresh()->load(['customer', 'lines.product', 'lines.unit']);
        });
    }

    public function unmark(SalesOrder $salesOrder): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder): SalesOrder {
            $salesOrder = SalesOrder::query()->lockForUpdate()->findOrFail($salesOrder->id);

            if ($salesOrder->status !== 'received') {
                throw SalesOrderException::notEditable($salesOrder->id, $salesOrder->status);
            }

            if ($salesOrder->awaiting_shipment_instruction) {
                $salesOrder->update(['awaiting_shipment_instruction' => false]);
                $this->auditLogService->record(new AuditLogData(
                    event: 'sales_order.awaiting_shipment_instruction_unmarked',
                    auditable: $salesOrder,
                    beforeValues: ['awaiting_shipment_instruction' => true],
                    afterValues: ['awaiting_shipment_instruction' => false],
                ));
            }

            return $salesOrder->refresh()->load(['customer', 'lines.product', 'lines.unit']);
        });
    }
}
