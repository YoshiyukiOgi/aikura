<?php

namespace App\Services\Search;

use App\Models\Customer;
use App\Models\AuditLog;
use App\Models\ConsumptionTaxMonthlyFiling;
use App\Models\InvoiceHeader;
use App\Models\LiquorTaxMonthlyFiling;
use App\Models\OperationJob;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ReceivableMonthlyBalance;
use App\Models\ReportExport;
use App\Models\SalesOrder;
use App\Models\ShipmentHeader;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentLotAllocation;
use App\Models\ShipmentPick;
use App\Models\StockLotMonthlyBalance;
use App\Models\StockMovement;
use App\Support\SearchTextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SearchService
{
    public function crossSearch(?string $query, int $limit = 20): array
    {
        $query = $this->normalizeText($query);
        $limit = $this->normalizeLimit($limit);

        if ($query === null) {
            return ['query' => null, 'results' => []];
        }

        $results = collect()
            ->merge($this->searchCustomers($query, $limit))
            ->merge($this->searchProducts($query, $limit))
            ->merge($this->searchLots($query, $limit))
            ->merge($this->searchTransactions($query, $limit))
            ->merge($this->searchOperations($query, $limit))
            ->sortByDesc('updated_at')
            ->take($limit)
            ->values()
            ->map(function (array $item): array {
                unset($item['updated_at']);

                return $item;
            })
            ->all();

        return ['query' => $query, 'results' => $results];
    }

    public function transactions(array $filters): array
    {
        $limit = $this->normalizeLimit($filters['limit'] ?? 50);
        $type = $this->normalizeText($filters['type'] ?? null) ?? 'all';
        $types = $type === 'all'
            ? ['sales_order', 'shipment_instruction', 'shipment_pick', 'shipment', 'invoice', 'payment_schedule', 'payment']
            : [$type];

        $transactions = collect();

        foreach ($types as $targetType) {
            $transactions = $transactions->merge(match ($targetType) {
                'sales_order' => $this->transactionSalesOrders($filters, $limit),
                'shipment_instruction' => $this->transactionShipmentInstructions($filters, $limit),
                'shipment_pick' => $this->transactionShipmentPicks($filters, $limit),
                'shipment' => $this->transactionShipments($filters, $limit),
                'invoice' => $this->transactionInvoices($filters, $limit),
                'payment_schedule' => $this->transactionPaymentSchedules($filters, $limit),
                'payment' => $this->transactionPayments($filters, $limit),
                default => [],
            });
        }

        return [
            'filters' => $this->visibleFilters($filters),
            'transactions' => $transactions
                ->sortByDesc('date')
                ->take($limit)
                ->values()
                ->all(),
        ];
    }

    public function statuses(array $filters): array
    {
        $limit = $this->normalizeLimit($filters['limit'] ?? 10);
        $type = $this->normalizeText($filters['type'] ?? null) ?? 'all';
        $definitions = [
            'sales_orders' => [SalesOrder::query(), 'order_number', 'order_date', 'sales_order'],
            'shipment_instructions' => [ShipmentInstruction::query(), 'instruction_number', 'instruction_date', 'shipment_instruction'],
            'shipment_picks' => [ShipmentPick::query(), 'pick_number', 'pick_date', 'shipment_pick'],
            'shipments' => [ShipmentHeader::query(), 'document_number', 'document_date', 'shipment'],
            'invoices' => [InvoiceHeader::query(), 'invoice_number', 'invoice_date', 'invoice'],
            'payment_schedules' => [PaymentSchedule::query(), 'id', 'expected_payment_date', 'payment_schedule'],
            'payments' => [Payment::query(), 'reference_number', 'payment_date', 'payment'],
            'report_exports' => [ReportExport::query(), 'file_name', 'generated_at', 'report_export'],
            'operation_jobs' => [OperationJob::query(), 'id', 'created_at', 'operation_job'],
        ];

        $statuses = [];
        foreach ($definitions as $group => [$builder, $labelColumn, $dateColumn, $itemType]) {
            if ($type !== 'all' && $type !== $itemType && $type !== $group) {
                continue;
            }

            $this->applyStatusFilter($builder, $filters);

            $statuses[$group] = [
                'counts' => (clone $builder)
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->orderBy('status')
                    ->pluck('count', 'status')
                    ->all(),
                'items' => (clone $builder)
                    ->latest($dateColumn)
                    ->limit($limit)
                    ->get()
                    ->map(fn (Model $model): array => [
                        'type' => $itemType,
                        'id' => $model->getKey(),
                        'label' => (string) ($model->getAttribute($labelColumn) ?? $model->getKey()),
                        'status' => $model->getAttribute('status'),
                        'date' => $this->dateValue($model->getAttribute($dateColumn)),
                    ])
                    ->all(),
            ];
        }

        return ['filters' => $this->visibleFilters($filters), 'statuses' => $statuses];
    }

    public function relations(array $filters): array
    {
        $limit = $this->normalizeLimit($filters['limit'] ?? 20);
        $customerId = $this->normalizeId($filters['customer_id'] ?? null);
        $productId = $this->normalizeId($filters['product_id'] ?? null);
        $lotId = $this->normalizeId($filters['production_lot_id'] ?? ($filters['lot_id'] ?? null));

        return [
            'filters' => $this->visibleFilters($filters),
            'relations' => [
                'customer' => $customerId ? Customer::query()->find($customerId)?->only(['id', 'customer_code', 'name']) : null,
                'product' => $productId ? Product::query()->find($productId)?->only(['id', 'product_code', 'name', 'display_name']) : null,
                'production_lot' => $lotId ? ProductionLot::query()->find($lotId)?->only(['id', 'lot_code', 'display_name', 'status']) : null,
                'sales_orders' => $this->relationCustomerProduct(SalesOrder::query()->with('customer'), $customerId, $productId, 'lines', 'sales_order', 'order_number', 'order_date', $limit),
                'shipment_instructions' => $this->relationCustomerProduct(ShipmentInstruction::query()->with('customer'), $customerId, $productId, 'lines', 'shipment_instruction', 'instruction_number', 'instruction_date', $limit),
                'shipment_picks' => $this->relationShipmentPicks($customerId, $productId, $limit),
                'shipments' => $this->relationShipments($customerId, $productId, $lotId, $limit),
                'invoices' => $this->relationCustomerProduct(InvoiceHeader::query()->with('customer'), $customerId, $productId, 'lines', 'invoice', 'invoice_number', 'invoice_date', $limit),
                'stock_movements' => $this->relationStockMovements($productId, $lotId, $limit),
                'shipment_lot_allocations' => $this->relationShipmentLotAllocations($productId, $lotId, $limit),
            ],
        ];
    }

    public function pending(array $filters): array
    {
        $limit = $this->normalizeLimit($filters['limit'] ?? 20);

        return [
            'filters' => $this->visibleFilters($filters),
            'pending' => [
                'sales_orders' => $this->pendingTransaction(SalesOrder::query()->with('customer'), ['received', 'partially_instructed'], 'sales_order', 'order_number', 'order_date', $limit),
                'shipment_instructions' => $this->pendingTransaction(ShipmentInstruction::query()->with('customer'), ['instructed', 'partially_picked'], 'shipment_instruction', 'instruction_number', 'instruction_date', $limit),
                'shipment_picks' => $this->pendingShipmentPicks($limit),
                'shipments' => $this->pendingTransaction(ShipmentHeader::query()->with('customer'), ['draft'], 'shipment', 'document_number', 'document_date', $limit),
                'invoices' => $this->pendingTransaction(InvoiceHeader::query()->with('customer'), ['draft'], 'invoice', 'invoice_number', 'invoice_date', $limit),
                'payment_schedules' => $this->pendingTransaction(PaymentSchedule::query()->with('customer'), ['open', 'partial'], 'payment_schedule', 'id', 'expected_payment_date', $limit),
                'operation_jobs' => $this->operationJobsByStatus(['running'], $limit),
            ],
        ];
    }

    public function reviewRequired(array $filters): array
    {
        $limit = $this->normalizeLimit($filters['limit'] ?? 20);

        return [
            'filters' => $this->visibleFilters($filters),
            'review_required' => [
                'failed_operation_jobs' => $this->operationJobsByStatus(['failed'], $limit),
                'failed_report_exports' => $this->reportExportsByStatus(['failed'], $limit),
                'cancelled_sales_orders' => $this->pendingTransaction(SalesOrder::query()->with('customer'), ['cancelled'], 'sales_order', 'order_number', 'order_date', $limit),
                'cancelled_shipments' => $this->pendingTransaction(ShipmentHeader::query()->with('customer'), ['cancelled'], 'shipment', 'document_number', 'document_date', $limit),
                'cancelled_invoices' => $this->pendingTransaction(InvoiceHeader::query()->with('customer'), ['cancelled'], 'invoice', 'invoice_number', 'invoice_date', $limit),
                'cancelled_payments' => $this->pendingTransaction(Payment::query()->with('customer'), ['cancelled'], 'payment', 'reference_number', 'payment_date', $limit),
            ],
        ];
    }

    public function closingTargets(array $filters): array
    {
        $limit = $this->normalizeLimit($filters['limit'] ?? 20);
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);
        $periodStart = sprintf('%04d-%02d-01', $year, $month);
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        return [
            'filters' => array_merge($this->visibleFilters($filters), ['year' => $year, 'month' => $month]),
            'period' => ['start' => $periodStart, 'end' => $periodEnd],
            'closing_targets' => [
                'stock_movements' => $this->closingStockMovements($periodStart, $periodEnd, $limit),
                'shipments_for_liquor_tax' => $this->closingShipments($periodStart, $periodEnd, $limit),
                'invoices_for_consumption_tax' => $this->closingInvoices($periodStart, $periodEnd, $limit),
                'payment_schedules_for_receivables' => $this->closingPaymentSchedules($periodEnd, $limit),
                'stock_monthly_balances' => $this->monthlyRows(StockLotMonthlyBalance::query(), $year, $month, 'stock_lot_monthly_balance', 'production_lot_id', $limit),
                'receivable_monthly_balances' => $this->monthlyRows(ReceivableMonthlyBalance::query(), $year, $month, 'receivable_monthly_balance', 'customer_name', $limit),
                'liquor_tax_monthly_filings' => $this->monthlyRows(LiquorTaxMonthlyFiling::query(), $year, $month, 'liquor_tax_monthly_filing', 'id', $limit),
                'consumption_tax_monthly_filings' => $this->monthlyRows(ConsumptionTaxMonthlyFiling::query(), $year, $month, 'consumption_tax_monthly_filing', 'id', $limit),
            ],
        ];
    }

    public function auditLogs(array $filters): array
    {
        $limit = $this->normalizeLimit($filters['limit'] ?? 50);
        $builder = AuditLog::query()->with('user');

        foreach (['event', 'target_table', 'target_id', 'request_id'] as $column) {
            $value = $this->normalizeText($filters[$column] ?? null);
            if ($value !== null) {
                $builder->where($column, $value);
            }
        }

        $userId = $this->normalizeId($filters['user_id'] ?? null);
        if ($userId !== null) {
            $builder->where('user_id', $userId);
        }

        if (! empty($filters['date_from'])) {
            $builder->whereDate('occurred_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $builder->whereDate('occurred_at', '<=', $filters['date_to']);
        }

        $query = $this->normalizeText($filters['q'] ?? null);
        if ($query !== null) {
            $builder->where(fn (Builder $nested): Builder => $this->whereLike($nested, ['event', 'target_table', 'target_id', 'reason', 'user_agent'], $query));
        }

        return [
            'filters' => $this->visibleFilters($filters),
            'audit_logs' => $builder
                ->latest('occurred_at')
                ->limit($limit)
                ->get()
                ->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'occurred_at' => $log->occurred_at?->toISOString(),
                    'event' => $log->event,
                    'target_table' => $log->target_table,
                    'target_id' => $log->target_id,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name,
                    'reason' => $log->reason,
                    'request_id' => $log->request_id,
                ])
                ->all(),
        ];
    }

    private function searchCustomers(string $query, int $limit): array
    {
        return Customer::query()
            ->where('search_key_normalized', 'ilike', '%'.$query.'%')
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer): array => $this->searchItem('customer', $customer, $customer->customer_code, $customer->name, $customer->is_active ? 'active' : 'inactive'))
            ->all();
    }

    private function searchProducts(string $query, int $limit): array
    {
        return Product::query()
            ->where('search_key_normalized', 'ilike', '%'.$query.'%')
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => $this->searchItem('product', $product, $product->product_code, $product->display_name ?? $product->name, $product->is_active ? 'active' : 'inactive'))
            ->all();
    }

    private function searchLots(string $query, int $limit): array
    {
        return ProductionLot::query()
            ->where(fn (Builder $builder): Builder => $this->whereLike($builder, ['lot_code', 'display_name', 'tank_code', 'rice_variety', 'production_method', 'external_system_code', 'legacy_lot_text', 'search_key'], $query))
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ProductionLot $lot): array => $this->searchItem('production_lot', $lot, $lot->lot_code, $lot->display_name, $lot->status))
            ->all();
    }

    private function searchTransactions(string $query, int $limit): array
    {
        return collect()
            ->merge($this->searchModel(SalesOrder::query(), ['order_number', 'customer_order_number', 'source_reference', 'note'], $query, $limit, 'sales_order', 'order_number', 'order_date'))
            ->merge($this->searchModel(ShipmentInstruction::query(), ['instruction_number', 'note'], $query, $limit, 'shipment_instruction', 'instruction_number', 'instruction_date'))
            ->merge($this->searchModel(ShipmentPick::query(), ['pick_number', 'note'], $query, $limit, 'shipment_pick', 'pick_number', 'pick_date'))
            ->merge($this->searchModel(ShipmentHeader::query(), ['document_number', 'correction_reason', 'note'], $query, $limit, 'shipment', 'document_number', 'document_date'))
            ->merge($this->searchModel(InvoiceHeader::query(), ['invoice_number', 'note'], $query, $limit, 'invoice', 'invoice_number', 'invoice_date'))
            ->merge($this->searchModel(Payment::query(), ['reference_number', 'payment_method', 'note'], $query, $limit, 'payment', 'reference_number', 'payment_date'))
            ->all();
    }

    private function searchOperations(string $query, int $limit): array
    {
        return collect()
            ->merge($this->searchModel(ReportExport::query(), ['report_type', 'format', 'file_name', 'file_path', 'reason'], $query, $limit, 'report_export', 'file_name', 'generated_at'))
            ->merge($this->searchModel(OperationJob::query(), ['job_type', 'target_type', 'target_id', 'reason', 'error_message'], $query, $limit, 'operation_job', 'job_type', 'created_at'))
            ->all();
    }

    private function searchModel(Builder $builder, array $columns, string $query, int $limit, string $type, string $labelColumn, string $dateColumn): array
    {
        return $builder
            ->where(fn (Builder $nested): Builder => $this->whereLike($nested, $columns, $query))
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Model $model): array => $this->searchItem($type, $model, (string) ($model->getAttribute($labelColumn) ?? $model->getKey()), null, $model->getAttribute('status'), $model->getAttribute($dateColumn)))
            ->all();
    }

    private function transactionSalesOrders(array $filters, int $limit): array
    {
        return $this->transactionCustomerProduct(SalesOrder::query()->with('customer'), $filters, 'lines', 'sales_order', 'order_number', 'order_date', ['order_number', 'customer_order_number', 'source_reference', 'note'], $limit);
    }

    private function transactionShipmentInstructions(array $filters, int $limit): array
    {
        return $this->transactionCustomerProduct(ShipmentInstruction::query()->with('customer'), $filters, 'lines', 'shipment_instruction', 'instruction_number', 'instruction_date', ['instruction_number', 'note'], $limit);
    }

    private function transactionShipmentPicks(array $filters, int $limit): array
    {
        $builder = ShipmentPick::query()->with('shipmentInstruction.customer');
        $this->applyStatusFilter($builder, $filters);
        $this->applyDateFilters($builder, $filters, 'pick_date');
        $this->applyTextFilter($builder, $filters, ['pick_number', 'note']);
        $this->applyProductFilter($builder, $filters, 'lines');

        $customerId = $this->normalizeId($filters['customer_id'] ?? null);
        if ($customerId !== null) {
            $builder->whereHas('shipmentInstruction', fn (Builder $relation): Builder => $relation->where('customer_id', $customerId));
        }

        return $builder->latest('pick_date')->limit($limit)->get()
            ->map(fn (ShipmentPick $pick): array => $this->transactionItem('shipment_pick', $pick, $pick->pick_number, $pick->status, $pick->pick_date, $pick->shipmentInstruction?->customer_id, $pick->shipmentInstruction?->customer?->name))
            ->all();
    }

    private function transactionShipments(array $filters, int $limit): array
    {
        return $this->transactionCustomerProduct(ShipmentHeader::query()->with('customer'), $filters, 'lines', 'shipment', 'document_number', 'document_date', ['document_number', 'correction_reason', 'note'], $limit);
    }

    private function transactionInvoices(array $filters, int $limit): array
    {
        return $this->transactionCustomerProduct(InvoiceHeader::query()->with('customer'), $filters, 'lines', 'invoice', 'invoice_number', 'invoice_date', ['invoice_number', 'note'], $limit);
    }

    private function transactionPaymentSchedules(array $filters, int $limit): array
    {
        return $this->transactionCustomerProduct(PaymentSchedule::query()->with('customer'), $filters, null, 'payment_schedule', 'id', 'expected_payment_date', ['note'], $limit);
    }

    private function transactionPayments(array $filters, int $limit): array
    {
        return $this->transactionCustomerProduct(Payment::query()->with('customer'), $filters, null, 'payment', 'reference_number', 'payment_date', ['reference_number', 'payment_method', 'note'], $limit);
    }

    private function transactionCustomerProduct(Builder $builder, array $filters, ?string $lineRelation, string $type, string $labelColumn, string $dateColumn, array $textColumns, int $limit): array
    {
        $this->applyStatusFilter($builder, $filters);
        $this->applyCustomerFilter($builder, $filters);
        $this->applyDateFilters($builder, $filters, $dateColumn);
        $this->applyTextFilter($builder, $filters, $textColumns);
        $this->applyProductFilter($builder, $filters, $lineRelation);

        return $builder->latest($dateColumn)->limit($limit)->get()
            ->map(fn (Model $model): array => $this->transactionItem($type, $model, (string) ($model->getAttribute($labelColumn) ?? $model->getKey()), $model->getAttribute('status'), $model->getAttribute($dateColumn), $model->getAttribute('customer_id'), $model->getRelationValue('customer')?->name))
            ->all();
    }

    private function relationCustomerProduct(Builder $builder, ?int $customerId, ?int $productId, string $lineRelation, string $type, string $labelColumn, string $dateColumn, int $limit): array
    {
        if ($customerId !== null) {
            $builder->where('customer_id', $customerId);
        }

        if ($productId !== null) {
            $builder->whereHas($lineRelation, fn (Builder $line): Builder => $line->where('product_id', $productId));
        }

        return $builder->latest($dateColumn)->limit($limit)->get()
            ->map(fn (Model $model): array => $this->transactionItem($type, $model, (string) ($model->getAttribute($labelColumn) ?? $model->getKey()), $model->getAttribute('status'), $model->getAttribute($dateColumn), $model->getAttribute('customer_id'), $model->getRelationValue('customer')?->name))
            ->all();
    }

    private function relationShipmentPicks(?int $customerId, ?int $productId, int $limit): array
    {
        $builder = ShipmentPick::query()->with('shipmentInstruction.customer');

        if ($productId !== null) {
            $builder->whereHas('lines', fn (Builder $line): Builder => $line->where('product_id', $productId));
        }

        if ($customerId !== null) {
            $builder->whereHas('shipmentInstruction', fn (Builder $relation): Builder => $relation->where('customer_id', $customerId));
        }

        return $builder->latest('pick_date')->limit($limit)->get()
            ->map(fn (ShipmentPick $pick): array => $this->transactionItem('shipment_pick', $pick, $pick->pick_number, $pick->status, $pick->pick_date, $pick->shipmentInstruction?->customer_id, $pick->shipmentInstruction?->customer?->name))
            ->all();
    }

    private function relationShipments(?int $customerId, ?int $productId, ?int $lotId, int $limit): array
    {
        $builder = ShipmentHeader::query()->with('customer');

        if ($customerId !== null) {
            $builder->where('customer_id', $customerId);
        }

        if ($productId !== null) {
            $builder->whereHas('lines', fn (Builder $line): Builder => $line->where('product_id', $productId));
        }

        if ($lotId !== null) {
            $builder->whereHas('lines.lotAllocations', fn (Builder $line): Builder => $line->where('production_lot_id', $lotId));
        }

        return $builder->latest('document_date')->limit($limit)->get()
            ->map(fn (ShipmentHeader $shipment): array => $this->transactionItem('shipment', $shipment, $shipment->document_number, $shipment->status, $shipment->document_date, $shipment->customer_id, $shipment->customer?->name))
            ->all();
    }

    private function relationStockMovements(?int $productId, ?int $lotId, int $limit): array
    {
        $builder = StockMovement::query();
        if ($productId !== null && $lotId === null) {
            return [];
        }
        if ($lotId !== null) {
            $builder->where('production_lot_id', $lotId);
        }

        return $builder->latest('movement_date')->limit($limit)->get()
            ->map(fn (StockMovement $movement): array => [
                'type' => 'stock_movement',
                'id' => $movement->id,
                'label' => $movement->source_document_number ?? (string) $movement->id,
                'status' => $movement->status,
                'date' => $this->dateValue($movement->movement_date),
                'production_lot_id' => $movement->production_lot_id,
                'quantity' => $movement->quantity,
            ])
            ->all();
    }

    private function relationShipmentLotAllocations(?int $productId, ?int $lotId, int $limit): array
    {
        $builder = ShipmentLotAllocation::query();
        if ($productId !== null) {
            $builder->where('product_id', $productId);
        }
        if ($lotId !== null) {
            $builder->where('production_lot_id', $lotId);
        }

        return $builder->latest('allocated_at')->limit($limit)->get()
            ->map(fn (ShipmentLotAllocation $allocation): array => [
                'type' => 'shipment_lot_allocation',
                'id' => $allocation->id,
                'label' => (string) $allocation->shipment_header_id,
                'status' => $allocation->status,
                'date' => $this->dateValue($allocation->allocated_at),
                'shipment_header_id' => $allocation->shipment_header_id,
                'shipment_line_id' => $allocation->shipment_line_id,
                'product_id' => $allocation->product_id,
                'production_lot_id' => $allocation->production_lot_id,
                'quantity' => $allocation->quantity,
            ])
            ->all();
    }

    private function pendingTransaction(Builder $builder, array $statuses, string $type, string $labelColumn, string $dateColumn, int $limit): array
    {
        return $builder
            ->whereIn('status', $statuses)
            ->latest($dateColumn)
            ->limit($limit)
            ->get()
            ->map(fn (Model $model): array => $this->transactionItem($type, $model, (string) ($model->getAttribute($labelColumn) ?? $model->getKey()), $model->getAttribute('status'), $model->getAttribute($dateColumn), $model->getAttribute('customer_id'), $model->getRelationValue('customer')?->name))
            ->all();
    }

    private function pendingShipmentPicks(int $limit): array
    {
        return ShipmentPick::query()
            ->with('shipmentInstruction.customer')
            ->whereIn('status', ['picked'])
            ->whereDoesntHave('shipmentHeader')
            ->latest('pick_date')
            ->limit($limit)
            ->get()
            ->map(fn (ShipmentPick $pick): array => $this->transactionItem('shipment_pick', $pick, $pick->pick_number, $pick->status, $pick->pick_date, $pick->shipmentInstruction?->customer_id, $pick->shipmentInstruction?->customer?->name))
            ->all();
    }

    private function operationJobsByStatus(array $statuses, int $limit): array
    {
        return OperationJob::query()
            ->whereIn('status', $statuses)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (OperationJob $job): array => [
                'type' => 'operation_job',
                'id' => $job->id,
                'label' => $job->job_type,
                'status' => $job->status,
                'date' => $this->dateValue($job->created_at),
                'target_type' => $job->target_type,
                'target_id' => $job->target_id,
                'reason' => $job->reason,
                'error_message' => $job->error_message,
            ])
            ->all();
    }

    private function reportExportsByStatus(array $statuses, int $limit): array
    {
        return ReportExport::query()
            ->whereIn('status', $statuses)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ReportExport $export): array => [
                'type' => 'report_export',
                'id' => $export->id,
                'label' => $export->file_name,
                'status' => $export->status,
                'date' => $this->dateValue($export->generated_at),
                'report_type' => $export->report_type,
                'format' => $export->format,
                'reason' => $export->reason,
            ])
            ->all();
    }

    private function closingStockMovements(string $periodStart, string $periodEnd, int $limit): array
    {
        return StockMovement::query()
            ->where('status', 'confirmed')
            ->whereBetween('movement_date', [$periodStart, $periodEnd])
            ->latest('movement_date')
            ->limit($limit)
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'type' => 'stock_movement',
                'id' => $movement->id,
                'label' => $movement->source_document_number ?? (string) $movement->id,
                'status' => $movement->status,
                'date' => $this->dateValue($movement->movement_date),
                'production_lot_id' => $movement->production_lot_id,
                'quantity' => $movement->quantity,
            ])
            ->all();
    }

    private function closingShipments(string $periodStart, string $periodEnd, int $limit): array
    {
        return ShipmentHeader::query()
            ->with('customer')
            ->where('status', 'confirmed')
            ->whereBetween('liquor_tax_transfer_date', [$periodStart, $periodEnd])
            ->orWhere(function (Builder $builder) use ($periodStart, $periodEnd): void {
                $builder->where('status', 'confirmed')
                    ->whereNull('liquor_tax_transfer_date')
                    ->whereBetween('document_date', [$periodStart, $periodEnd]);
            })
            ->latest('document_date')
            ->limit($limit)
            ->get()
            ->map(fn (ShipmentHeader $shipment): array => $this->transactionItem('shipment', $shipment, $shipment->document_number, $shipment->status, $shipment->liquor_tax_transfer_date ?? $shipment->document_date, $shipment->customer_id, $shipment->customer?->name))
            ->all();
    }

    private function closingInvoices(string $periodStart, string $periodEnd, int $limit): array
    {
        return InvoiceHeader::query()
            ->with('customer')
            ->where('status', 'confirmed')
            ->whereBetween('invoice_date', [$periodStart, $periodEnd])
            ->latest('invoice_date')
            ->limit($limit)
            ->get()
            ->map(fn (InvoiceHeader $invoice): array => $this->transactionItem('invoice', $invoice, $invoice->invoice_number, $invoice->status, $invoice->invoice_date, $invoice->customer_id, $invoice->customer?->name))
            ->all();
    }

    private function closingPaymentSchedules(string $periodEnd, int $limit): array
    {
        return PaymentSchedule::query()
            ->with('customer')
            ->whereIn('status', ['open', 'partial'])
            ->whereDate('expected_payment_date', '<=', $periodEnd)
            ->latest('expected_payment_date')
            ->limit($limit)
            ->get()
            ->map(fn (PaymentSchedule $schedule): array => $this->transactionItem('payment_schedule', $schedule, (string) $schedule->id, $schedule->status, $schedule->expected_payment_date, $schedule->customer_id, $schedule->customer?->name))
            ->all();
    }

    private function monthlyRows(Builder $builder, int $year, int $month, string $type, string $labelColumn, int $limit): array
    {
        return $builder
            ->where('year', $year)
            ->where('month', $month)
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Model $model): array => [
                'type' => $type,
                'id' => $model->getKey(),
                'label' => (string) ($model->getAttribute($labelColumn) ?? $model->getKey()),
                'status' => $model->getAttribute('status'),
                'date' => $this->dateValue($model->getAttribute('period_end')),
            ])
            ->all();
    }

    private function whereLike(Builder $builder, array $columns, string $query): Builder
    {
        foreach ($columns as $column) {
            $builder->orWhere($column, 'ilike', '%'.$query.'%');
        }

        return $builder;
    }

    private function applyStatusFilter(Builder $builder, array $filters): void
    {
        $status = $this->normalizeText($filters['status'] ?? null);
        if ($status !== null) {
            $builder->where('status', $status);
        }
    }

    private function applyCustomerFilter(Builder $builder, array $filters): void
    {
        $customerId = $this->normalizeId($filters['customer_id'] ?? null);
        if ($customerId !== null) {
            $builder->where('customer_id', $customerId);
        }
    }

    private function applyDateFilters(Builder $builder, array $filters, string $dateColumn): void
    {
        if (! empty($filters['date_from'])) {
            $builder->whereDate($dateColumn, '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $builder->whereDate($dateColumn, '<=', $filters['date_to']);
        }
    }

    private function applyTextFilter(Builder $builder, array $filters, array $columns): void
    {
        $query = $this->normalizeText($filters['q'] ?? null);
        if ($query !== null) {
            $builder->where(fn (Builder $nested): Builder => $this->whereLike($nested, $columns, $query));
        }
    }

    private function applyProductFilter(Builder $builder, array $filters, ?string $lineRelation): void
    {
        $productId = $this->normalizeId($filters['product_id'] ?? null);
        if ($productId !== null && $lineRelation !== null) {
            $builder->whereHas($lineRelation, fn (Builder $line): Builder => $line->where('product_id', $productId));
        }
    }

    private function searchItem(string $type, Model $model, ?string $label, mixed $matchedText, ?string $status, mixed $date = null): array
    {
        return [
            'type' => $type,
            'id' => $model->getKey(),
            'label' => $label ?? (string) $model->getKey(),
            'matched_text' => $matchedText === null ? null : (string) $matchedText,
            'status' => $status,
            'date' => $this->dateValue($date),
            'updated_at' => $this->dateValue($model->getAttribute('updated_at')),
        ];
    }

    private function transactionItem(string $type, Model $model, string $label, ?string $status, mixed $date, ?int $customerId, ?string $customerName): array
    {
        return [
            'type' => $type,
            'id' => $model->getKey(),
            'label' => $label,
            'status' => $status,
            'date' => $this->dateValue($date),
            'customer_id' => $customerId,
            'customer_name' => $customerName,
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = SearchTextNormalizer::normalize((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeLimit(mixed $value): int
    {
        return max(1, min(100, (int) $value));
    }

    private function visibleFilters(array $filters): array
    {
        return collect($filters)
            ->only(['q', 'type', 'status', 'customer_id', 'product_id', 'production_lot_id', 'lot_id', 'year', 'month', 'event', 'target_table', 'target_id', 'user_id', 'request_id', 'date_from', 'date_to', 'limit'])
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
    }
}
