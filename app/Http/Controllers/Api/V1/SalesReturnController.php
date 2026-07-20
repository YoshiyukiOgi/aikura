<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CancelBillingRequest;
use App\Http\Requests\Api\V1\StoreSalesReturnRequest;
use App\Models\SalesReturnHeader;
use App\Models\SalesReturnLine;
use App\Models\ShipmentLine;
use App\Models\ShipmentLotAllocation;
use App\Services\Billing\CancelSalesReturnService;
use App\Services\Billing\CreateSalesReturnData;
use App\Services\Billing\CreateSalesReturnLineData;
use App\Services\Billing\CreateSalesReturnLineLotData;
use App\Services\Billing\CreateSalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesReturnController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer' => ['nullable', 'string', 'max:120'],
            'return_date_from' => ['nullable', 'date'],
            'return_date_to' => ['nullable', 'date', 'after_or_equal:return_date_from'],
            'status' => ['nullable', 'string', 'in:active,cancelled,all'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $returns = SalesReturnHeader::query()
            ->with(['customer', 'creditInvoiceHeader', 'lines'])
            ->when($validated['customer'] ?? null, function ($query, string $customer): void {
                $query->whereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$customer}%"));
            })
            ->when($validated['return_date_from'] ?? null, fn ($query, string $date) => $query->whereDate('return_date', '>=', $date))
            ->when($validated['return_date_to'] ?? null, fn ($query, string $date) => $query->whereDate('return_date', '<=', $date))
            ->when(($validated['status'] ?? 'active') === 'active', fn ($query) => $query->whereNull('cancelled_at')->where('status', '!=', 'cancelled'))
            ->when(($validated['status'] ?? 'active') === 'cancelled', fn ($query) => $query->where(function ($where): void {
                $where->whereNotNull('cancelled_at')->orWhere('status', 'cancelled');
            }))
            ->orderByDesc('id')
            ->limit((int) ($validated['limit'] ?? 50))
            ->get()
            ->map(fn (SalesReturnHeader $return): array => $this->serializeReturn($return))
            ->values()
            ->all();

        return $this->ok(['sales_returns' => $returns]);
    }

    public function show(SalesReturnHeader $salesReturnHeader): JsonResponse
    {
        return $this->ok([
            'sales_return' => $this->serializeReturn($salesReturnHeader->load(['customer', 'creditInvoiceHeader.lines', 'lines'])),
        ]);
    }

    public function sourceLineLots(ShipmentLine $shipmentLine): JsonResponse
    {
        $allocations = ShipmentLotAllocation::query()
            ->with(['productionLot', 'stockLocation', 'unit'])
            ->where('shipment_line_id', $shipmentLine->id)
            ->whereIn('status', ['allocated', 'confirmed'])
            ->whereNull('cancelled_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ShipmentLotAllocation $allocation): array => [
                'production_lot_id' => $allocation->production_lot_id,
                'lot_code' => $allocation->productionLot?->lot_code,
                'display_name' => $allocation->productionLot?->display_name,
                'quantity' => $allocation->quantity,
                'quantity_display' => $this->formatQuantity($allocation->quantity),
                'stock_location_id' => $allocation->stock_location_id,
                'stock_location_name' => $allocation->stockLocation?->name,
                'unit_id' => $allocation->unit_id,
                'unit_name' => $allocation->unit?->name,
            ])
            ->values()
            ->all();

        return $this->ok(['source_lots' => $allocations]);
    }

    public function store(StoreSalesReturnRequest $request, CreateSalesReturnService $service): JsonResponse
    {
        $validated = $request->validated();

        $return = $service->create(new CreateSalesReturnData(
            customerId: (int) $validated['customer_id'],
            returnDate: $validated['return_date'],
            reason: $validated['reason'],
            lines: array_map(
                fn (array $line): CreateSalesReturnLineData => new CreateSalesReturnLineData(
                    sourceInvoiceLineId: (int) $line['source_invoice_line_id'],
                    quantity: $line['quantity'],
                    stockAction: $line['stock_action'] ?? 'return_dedicated_stock',
                    liquorTaxReturnTreatment: $line['liquor_tax_return_treatment'] ?? 'review',
                    liquorTaxReturnReason: $line['liquor_tax_return_reason'] ?? null,
                    liquorTaxReviewedBy: $request->user()?->id,
                    stockLocationId: isset($line['stock_location_id']) ? (int) $line['stock_location_id'] : null,
                    productionLotId: isset($line['production_lot_id']) ? (int) $line['production_lot_id'] : null,
                    lotCode: $line['lot_code'] ?? null,
                    reason: $line['reason'] ?? null,
                    note: $line['note'] ?? null,
                    lots: array_map(
                        fn (array $lot): CreateSalesReturnLineLotData => new CreateSalesReturnLineLotData(
                            productionLotId: (int) $lot['production_lot_id'],
                            quantity: $lot['quantity'],
                            stockLocationId: isset($lot['stock_location_id']) ? (int) $lot['stock_location_id'] : null,
                            lotCode: $lot['lot_code'] ?? null,
                            note: $lot['note'] ?? null,
                        ),
                        $line['lots'] ?? [],
                    ),
                ),
                $validated['lines'],
            ),
            settlementMethod: $validated['settlement_method'] ?? 'credit_memo',
            note: $validated['note'] ?? null,
        ));

        return $this->created(['sales_return' => $this->serializeReturn($return)]);
    }

    public function cancel(
        CancelBillingRequest $request,
        SalesReturnHeader $salesReturnHeader,
        CancelSalesReturnService $service,
    ): JsonResponse {
        return $this->ok([
            'sales_return' => $this->serializeReturn($service->cancel($salesReturnHeader, $request->validated('reason'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReturn(SalesReturnHeader $return): array
    {
        return [
            'id' => $return->id,
            'return_number' => $return->return_number,
            'status' => $return->status,
            'customer_id' => $return->customer_id,
            'customer_name' => $return->customer?->name,
            'return_date' => $return->return_date?->toDateString(),
            'settlement_method' => $return->settlement_method,
            'credit_invoice_header_id' => $return->credit_invoice_header_id,
            'credit_invoice_number' => $return->creditInvoiceHeader?->invoice_number,
            'credited_at' => $return->credited_at?->toISOString(),
            'cancelled_at' => $return->cancelled_at?->toISOString(),
            'cancelled_reason' => $return->cancelled_reason,
            'reason' => $return->reason,
            'lines' => $return->lines
                ->map(fn (SalesReturnLine $line): array => [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'source_invoice_line_id' => $line->source_invoice_line_id,
                    'source_shipment_line_id' => $line->source_shipment_line_id,
                    'credit_invoice_line_id' => $line->credit_invoice_line_id,
                    'product_id' => $line->product_id,
                    'product_code' => $line->product_code,
                    'product_name' => $line->product_name,
                    'quantity' => $line->quantity,
                    'quantity_display' => $this->formatQuantity($line->quantity),
                    'unit_price' => $line->unit_price,
                    'amount' => $line->amount,
                    'tax_rate' => $line->tax_rate,
                    'tax_amount' => $line->tax_amount,
                    'total_amount' => $line->total_amount,
                    'stock_action' => $line->stock_action,
                    'liquor_tax_return_treatment' => $line->liquor_tax_return_treatment,
                    'liquor_tax_return_reason' => $line->liquor_tax_return_reason,
                    'liquor_tax_reviewed_at' => $line->liquor_tax_reviewed_at?->toISOString(),
                    'stock_location_id' => $line->stock_location_id,
                    'stock_movement_id' => $line->stock_movement_id,
                    'lots' => $line->lots
                        ->map(fn ($lot): array => [
                            'production_lot_id' => $lot->production_lot_id,
                            'quantity' => $lot->quantity,
                            'quantity_display' => $this->formatQuantity($lot->quantity),
                            'stock_location_id' => $lot->stock_location_id,
                            'stock_movement_id' => $lot->stock_movement_id,
                            'lot_code' => $lot->lot_code,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function formatQuantity(mixed $quantity): string
    {
        $formatted = rtrim(rtrim(number_format((float) $quantity, 4, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
