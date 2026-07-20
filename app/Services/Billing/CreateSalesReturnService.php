<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\SalesReturnException;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\InvoiceLine;
use App\Models\ProductionLot;
use App\Models\SalesReturnHeader;
use App\Models\SalesReturnLine;
use App\Models\ShipmentLotAllocation;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\EnsureStockPeriodIsOpenService;
use App\Services\NumberSequence\NumberSequenceService;
use App\Services\Tax\TaxRoundingService;
use Illuminate\Support\Facades\DB;

class CreateSalesReturnService
{
    private const SUPPORTED_STOCK_ACTIONS = [
        'return_stock',
        'return_dedicated_stock',
        'non_sales_stock_operation',
        'no_stock',
    ];

    private const STOCK_ACTIONS_REQUIRING_MOVEMENT = [
        'return_stock',
        'return_dedicated_stock',
    ];

    public function __construct(
        private readonly NumberSequenceService $numberSequenceService,
        private readonly TaxRoundingService $taxRoundingService,
        private readonly AuditLogService $auditLogService,
        private readonly EnsureReceivableMonthlyBalancePeriodIsOpenService $ensureReceivableMonthlyBalancePeriodIsOpenService,
        private readonly EnsureStockPeriodIsOpenService $ensureStockPeriodIsOpenService,
    ) {}

    public function create(CreateSalesReturnData $data): SalesReturnHeader
    {
        $reason = trim($data->reason);

        if ($reason === '') {
            throw SalesReturnException::emptyReason();
        }

        if ($data->lines === []) {
            throw SalesReturnException::emptyLines();
        }

        $this->ensureReceivableMonthlyBalancePeriodIsOpenService->ensureOpen($data->returnDate);

        return DB::transaction(function () use ($data, $reason): SalesReturnHeader {
            $customer = Customer::query()->findOrFail($data->customerId);

            $return = SalesReturnHeader::create([
                'return_number' => $this->numberSequenceService->next('sales_return')->formatted,
                'status' => 'draft',
                'customer_id' => $customer->id,
                'return_date' => $data->returnDate,
                'settlement_method' => $data->settlementMethod,
                'reason' => $reason,
                'note' => $data->note,
            ]);

            $creditInvoice = InvoiceHeader::create([
                'invoice_number' => $this->numberSequenceService->next('credit_memo')->formatted,
                'status' => 'draft',
                'document_type' => 'credit_memo',
                'customer_id' => $customer->id,
                'billing_cycle_id' => $customer->billing_cycle_id,
                'source_sales_return_header_id' => $return->id,
                'invoice_date' => $data->returnDate,
                'previous_balance_amount' => '0.00',
                'period_payment_amount' => '0.00',
                'carried_forward_amount' => '0.00',
                'current_sales_amount' => '0.00',
                'current_tax_amount' => '0.00',
                'current_invoice_amount' => '0.00',
                'tax_calculation_unit' => $customer->tax_calculation_unit,
                'tax_rounding_method' => $customer->tax_rounding_method,
                'amount_rounding_method' => $customer->amount_rounding_method,
                'note' => $data->note,
            ]);

            $lineNo = 1;
            $subtotal = '0.00';
            $tax = '0.00';
            $total = '0.00';

            foreach ($data->lines as $lineData) {
                $sourceLine = InvoiceLine::query()
                    ->with(['invoiceHeader', 'shipmentHeader', 'shipmentLine'])
                    ->lockForUpdate()
                    ->findOrFail($lineData->sourceInvoiceLineId);

                if ($sourceLine->invoiceHeader->status !== 'confirmed') {
                    throw SalesReturnException::sourceInvoiceNotConfirmed(
                        $sourceLine->invoice_header_id,
                        $sourceLine->invoiceHeader->status,
                    );
                }

                if ($sourceLine->invoiceHeader->customer_id !== $customer->id) {
                    throw SalesReturnException::customerMismatch($customer->id, $sourceLine->invoiceHeader->customer_id);
                }

                if (bccomp($lineData->quantity, '0', 4) <= 0) {
                    throw SalesReturnException::quantityMustBePositive();
                }

                if (! in_array($lineData->stockAction, self::SUPPORTED_STOCK_ACTIONS, true)) {
                    throw SalesReturnException::invalidStockAction($lineData->stockAction);
                }

                if (! in_array($lineData->liquorTaxReturnTreatment, ['eligible', 'not_eligible', 'review'], true)) {
                    throw new \DomainException('未対応の酒税戻入判定です。');
                }
                if ($lineData->liquorTaxReturnTreatment !== 'review' && trim((string) $lineData->liquorTaxReturnReason) === '') {
                    throw new \DomainException('酒税戻入判定の理由を入力してください。');
                }
                if ($lineData->liquorTaxReturnTreatment === 'eligible') {
                    $location = $lineData->stockLocationId === null ? null : StockLocation::query()->find($lineData->stockLocationId);
                    if (! in_array($lineData->stockAction, self::STOCK_ACTIONS_REQUIRING_MOVEMENT, true)
                        || $location?->location_type !== 'brewery') {
                        throw new \DomainException('戻入控除対象は製造場へ現物を戻す返品だけ指定できます。');
                    }
                    if ($sourceLine->shipmentHeader?->confirmed_liquor_tax_treatment !== 'taxable') {
                        throw new \DomainException('課税移出された元出荷だけ戻入控除対象にできます。');
                    }
                }

                $remaining = $this->remainingReturnableQuantity($sourceLine);
                if (bccomp($lineData->quantity, $remaining, 4) > 0) {
                    throw SalesReturnException::quantityExceedsRemaining($lineData->quantity, $remaining);
                }

                if (in_array($lineData->stockAction, self::STOCK_ACTIONS_REQUIRING_MOVEMENT, true)) {
                    if ($lineData->stockLocationId === null) {
                        throw SalesReturnException::stockLocationRequired($lineData->stockAction);
                    }
                    $this->ensureStockPeriodIsOpenService->ensureOpen($data->returnDate);
                }

                if ($lineData->stockAction === 'return_stock') {
                    $returnLots = $lineData->lots !== []
                        ? $lineData->lots
                        : ($lineData->productionLotId === null ? [] : [new CreateSalesReturnLineLotData(
                            productionLotId: $lineData->productionLotId,
                            quantity: $lineData->quantity,
                            stockLocationId: $lineData->stockLocationId,
                            lotCode: $lineData->lotCode,
                        )]);

                    if ($returnLots === []) {
                        throw SalesReturnException::productionLotRequiredForReturnStock();
                    }

                    $lotQuantityTotal = '0.0000';
                    $lotQuantitiesByProductionLotId = [];
                    $sourceLotQuantitiesByProductionLotId = ShipmentLotAllocation::query()
                        ->where('shipment_line_id', $sourceLine->shipment_line_id)
                        ->whereIn('status', ['allocated', 'confirmed'])
                        ->whereNull('cancelled_at')
                        ->selectRaw('production_lot_id, COALESCE(SUM(quantity), 0) as quantity')
                        ->groupBy('production_lot_id')
                        ->pluck('quantity', 'production_lot_id');

                    foreach ($returnLots as $lotData) {
                        $lotQuantityTotal = bcadd($lotQuantityTotal, $lotData->quantity, 4);
                        $lotQuantitiesByProductionLotId[$lotData->productionLotId] = bcadd(
                            $lotQuantitiesByProductionLotId[$lotData->productionLotId] ?? '0.0000',
                            $lotData->quantity,
                            4,
                        );

                        if (! $sourceLotQuantitiesByProductionLotId->has($lotData->productionLotId)) {
                            throw SalesReturnException::productionLotNotInSourceShipment($lotData->productionLotId);
                        }
                    }

                    foreach ($lotQuantitiesByProductionLotId as $productionLotId => $requestedQuantity) {
                        $sourceQuantity = bcadd((string) $sourceLotQuantitiesByProductionLotId->get($productionLotId), '0', 4);
                        if (bccomp($requestedQuantity, $sourceQuantity, 4) > 0) {
                            throw SalesReturnException::lotQuantityExceedsSource((int) $productionLotId, $requestedQuantity, $sourceQuantity);
                        }
                    }

                    if (bccomp($lineData->quantity, $lotQuantityTotal, 4) !== 0) {
                        throw SalesReturnException::lotQuantityMismatch($lineData->quantity, $lotQuantityTotal);
                    }
                }

                $amount = bcmul($lineData->quantity, (string) $sourceLine->unit_price, 2);
                $taxAmount = $this->returnTaxAmount($sourceLine, $lineData->quantity, $amount);
                $lineTotal = bcadd($amount, $taxAmount, 2);

                $returnLine = $return->lines()->create([
                    'source_invoice_line_id' => $sourceLine->id,
                    'source_shipment_header_id' => $sourceLine->shipment_header_id,
                    'source_shipment_line_id' => $sourceLine->shipment_line_id,
                    'line_no' => $lineNo,
                    'product_id' => $sourceLine->product_id,
                    'product_code' => $sourceLine->product_code,
                    'product_name' => $sourceLine->product_name,
                    'display_name' => $sourceLine->display_name,
                    'quantity' => bcadd($lineData->quantity, '0', 4),
                    'unit_code' => $sourceLine->unit_code,
                    'unit_name' => $sourceLine->unit_name,
                    'unit_price' => $sourceLine->unit_price,
                    'amount' => $amount,
                    'consumption_tax_category_id' => $sourceLine->consumption_tax_category_id,
                    'consumption_tax_category_code' => $sourceLine->consumption_tax_category_code,
                    'consumption_tax_category_name' => $sourceLine->consumption_tax_category_name,
                    'consumption_taxability' => $sourceLine->consumption_taxability,
                    'consumption_tax_rate_id' => $sourceLine->consumption_tax_rate_id,
                    'tax_rate' => $sourceLine->tax_rate,
                    'consumption_tax_rate_effective_from' => $sourceLine->consumption_tax_rate_effective_from,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $lineTotal,
                    'stock_action' => $lineData->stockAction,
                    'liquor_tax_return_treatment' => $lineData->liquorTaxReturnTreatment,
                    'liquor_tax_return_reason' => $lineData->liquorTaxReturnReason,
                    'liquor_tax_reviewed_by' => $lineData->liquorTaxReturnTreatment === 'review' ? null : $lineData->liquorTaxReviewedBy,
                    'liquor_tax_reviewed_at' => $lineData->liquorTaxReturnTreatment === 'review' ? null : now(),
                    'stock_location_id' => $lineData->stockLocationId,
                    'production_lot_id' => $lineData->productionLotId,
                    'lot_code' => $lineData->lotCode,
                    'reason' => $lineData->reason,
                    'note' => $lineData->note,
                ]);

                $creditLine = $creditInvoice->lines()->create([
                    'shipment_header_id' => $sourceLine->shipment_header_id,
                    'shipment_line_id' => $sourceLine->shipment_line_id,
                    'source_invoice_line_id' => $sourceLine->id,
                    'source_sales_return_line_id' => $returnLine->id,
                    'line_no' => $lineNo++,
                    'product_id' => $sourceLine->product_id,
                    'product_code' => $sourceLine->product_code,
                    'product_name' => $sourceLine->product_name,
                    'display_name' => $sourceLine->display_name,
                    'quantity' => bcmul($lineData->quantity, '-1', 4),
                    'unit_code' => $sourceLine->unit_code,
                    'unit_name' => $sourceLine->unit_name,
                    'unit_price' => $sourceLine->unit_price,
                    'amount' => bcmul($amount, '-1', 2),
                    'consumption_tax_category_id' => $sourceLine->consumption_tax_category_id,
                    'consumption_tax_category_code' => $sourceLine->consumption_tax_category_code,
                    'consumption_tax_category_name' => $sourceLine->consumption_tax_category_name,
                    'consumption_taxability' => $sourceLine->consumption_taxability,
                    'consumption_tax_rate_id' => $sourceLine->consumption_tax_rate_id,
                    'tax_rate' => $sourceLine->tax_rate,
                    'consumption_tax_rate_effective_from' => $sourceLine->consumption_tax_rate_effective_from,
                    'tax_amount' => bcmul($taxAmount, '-1', 2),
                    'total_amount' => bcmul($lineTotal, '-1', 2),
                    'note' => $lineData->note,
                ]);

                $returnLine->update(['credit_invoice_line_id' => $creditLine->id]);

                if ($lineData->stockAction === 'return_stock') {
                    $this->createReturnStockLotMovements($return, $returnLine, $lineData);
                } elseif (in_array($lineData->stockAction, self::STOCK_ACTIONS_REQUIRING_MOVEMENT, true)) {
                    $movement = $this->createReturnStockMovement($return, $returnLine);
                    $returnLine->update(['stock_movement_id' => $movement->id]);
                }

                $subtotal = bcsub($subtotal, $amount, 2);
                $tax = bcsub($tax, $taxAmount, 2);
                $total = bcsub($total, $lineTotal, 2);
            }

            $creditInvoice->update([
                'current_sales_amount' => $subtotal,
                'current_tax_amount' => $tax,
                'current_invoice_amount' => $total,
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
            ]);

            $return->update([
                'status' => 'credit_drafted',
                'credit_invoice_header_id' => $creditInvoice->id,
                'credited_at' => now(),
            ]);

            $this->auditLogService->record(new AuditLogData(
                event: 'sales_return.credit_drafted',
                auditable: $return->refresh(),
                afterValues: [
                    'return_number' => $return->return_number,
                    'credit_invoice_header_id' => $creditInvoice->id,
                    'credit_invoice_number' => $creditInvoice->invoice_number,
                    'line_count' => $return->lines()->count(),
                    'total_amount' => $creditInvoice->total_amount,
                ],
                reason: $reason,
            ));

            return $return->refresh()->load(['customer', 'creditInvoiceHeader.lines', 'lines']);
        });
    }

    private function remainingReturnableQuantity(InvoiceLine $sourceLine): string
    {
        $returned = SalesReturnLine::query()
            ->where('source_invoice_line_id', $sourceLine->id)
            ->whereHas('salesReturnHeader', fn ($query) => $query->whereNull('cancelled_at'))
            ->sum('quantity');

        return bcsub((string) $sourceLine->quantity, bcadd((string) $returned, '0', 4), 4);
    }

    private function returnTaxAmount(InvoiceLine $sourceLine, string $quantity, string $amount): string
    {
        if ($sourceLine->tax_rate === null) {
            return '0.00';
        }

        if (bccomp((string) $sourceLine->quantity, '0', 4) !== 0 && bccomp((string) $sourceLine->tax_amount, '0', 2) !== 0) {
            $ratio = bcdiv($quantity, (string) $sourceLine->quantity, 8);

            return $this->taxRoundingService->round(
                bcmul((string) $sourceLine->tax_amount, $ratio, 6),
                $sourceLine->invoiceHeader->tax_rounding_method ?? 'round',
            );
        }

        return $this->taxRoundingService->round(
            bcmul($amount, (string) $sourceLine->tax_rate, 6),
            $sourceLine->invoiceHeader->tax_rounding_method ?? 'round',
        );
    }

    private function createReturnStockMovement(SalesReturnHeader $return, SalesReturnLine $line): StockMovement
    {
        $location = StockLocation::query()->findOrFail($line->stock_location_id);

        return StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'sales_return',
            'movement_date' => $return->return_date,
            'stock_location_id' => $location->id,
            'unit_id' => $line->sourceInvoiceLine->shipmentLine->unit_id,
            'quantity' => $line->quantity,
            'source_type' => 'sales_return',
            'source_document_number' => $return->return_number,
            'source_line_no' => $line->line_no,
            'source_shipment_header_id' => $line->source_shipment_header_id,
            'source_shipment_line_id' => $line->source_shipment_line_id,
            'production_lot_id' => $line->production_lot_id,
            'lot_code' => $line->lot_code,
            'confirmed_at' => now(),
            'reason' => $line->reason ?? $return->reason,
            'note' => $line->stock_action,
        ]);
    }

    private function createReturnStockLotMovements(SalesReturnHeader $return, SalesReturnLine $line, CreateSalesReturnLineData $lineData): void
    {
        $lots = $lineData->lots !== []
            ? $lineData->lots
            : [new CreateSalesReturnLineLotData(
                productionLotId: (int) $lineData->productionLotId,
                quantity: $lineData->quantity,
                stockLocationId: $lineData->stockLocationId,
                lotCode: $lineData->lotCode,
            )];

        $firstMovementId = null;

        foreach ($lots as $lotData) {
            $lot = ProductionLot::query()->findOrFail($lotData->productionLotId);
            $locationId = $lotData->stockLocationId ?? $line->stock_location_id;
            $location = StockLocation::query()->findOrFail($locationId);

            $movement = StockMovement::create([
                'status' => 'confirmed',
                'movement_type' => 'sales_return',
                'movement_date' => $return->return_date,
                'stock_location_id' => $location->id,
                'unit_id' => $line->sourceInvoiceLine->shipmentLine->unit_id,
                'quantity' => $lotData->quantity,
                'source_type' => 'sales_return',
                'source_document_number' => $return->return_number,
                'source_line_no' => $line->line_no,
                'source_shipment_header_id' => $line->source_shipment_header_id,
                'source_shipment_line_id' => $line->source_shipment_line_id,
                'production_lot_id' => $lot->id,
                'lot_code' => $lotData->lotCode ?? $lot->lot_code,
                'confirmed_at' => now(),
                'reason' => $line->reason ?? $return->reason,
                'note' => 'return_stock',
            ]);

            $line->lots()->create([
                'production_lot_id' => $lot->id,
                'stock_location_id' => $location->id,
                'stock_movement_id' => $movement->id,
                'quantity' => $lotData->quantity,
                'lot_code' => $lotData->lotCode ?? $lot->lot_code,
                'note' => $lotData->note,
            ]);

            $firstMovementId ??= $movement->id;
        }

        $line->update(['stock_movement_id' => $firstMovementId]);
    }
}
