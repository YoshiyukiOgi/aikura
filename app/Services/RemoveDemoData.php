<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RemoveDemoData
{
    public function summary(): array
    {
        $ids = $this->discover();

        return [
            'users' => $ids['users']->count(),
            'customers' => $ids['customers']->count(),
            'products' => $ids['products']->count(),
            'production_lots' => $ids['lots']->count(),
            'sales_orders' => $ids['orders']->count(),
            'shipment_instructions' => $ids['instructions']->count(),
            'shipment_picks' => $ids['picks']->count(),
            'shipments' => $ids['shipments']->count(),
            'invoices' => $ids['invoices']->count(),
            'payments' => $ids['payments']->count(),
            'stock_movements' => $ids['stock_movements']->count(),
            'non_sales_operations' => $ids['non_sales_operations']->count(),
            'audit_logs' => $this->demoAuditQuery($ids)->count(),
        ];
    }

    public function remove(): array
    {
        return DB::transaction(function (): array {
            $ids = $this->discover();
            $this->guardTaxAndReturns($ids);
            $summary = $this->summary();

            $this->deleteUserDependencies($ids);
            $this->deleteBilling($ids);
            $this->deleteInventoryOperations($ids);
            $this->deleteShipmentFlow($ids);
            $this->deleteMasterDependencies($ids);
            $this->demoAuditQuery($ids)->delete();

            DB::table('users')->whereIn('id', $ids['users'])->delete();
            DB::table('production_lots')->whereIn('id', $ids['lots'])->delete();
            DB::table('products')->whereIn('id', $ids['products'])->delete();
            DB::table('customers')->whereIn('id', $ids['customers'])->delete();

            return $summary;
        }, 3);
    }

    private function discover(): array
    {
        $customerIds = DB::table('customers')->where('customer_code', 'like', 'DEMO-%')->pluck('id');
        $productIds = DB::table('products')->where('product_code', 'like', 'DEMO-%')->pluck('id');
        $lotIds = DB::table('production_lots')->where('lot_code', 'like', 'DEMO-%')->pluck('id');
        $userIds = DB::table('users')->where('email', 'like', 'demo.%@example.test')->pluck('id');

        $orderIds = DB::table('sales_orders')->whereIn('customer_id', $customerIds)->pluck('id');
        $orderIds = $this->merge($orderIds, DB::table('sales_order_lines')->whereIn('product_id', $productIds)->pluck('sales_order_id'));
        $orderLineIds = DB::table('sales_order_lines')->whereIn('sales_order_id', $orderIds)->pluck('id');

        $instructionIds = DB::table('shipment_instructions')->whereIn('customer_id', $customerIds)->pluck('id');
        $instructionIds = $this->merge($instructionIds, DB::table('shipment_instruction_lines')->whereIn('sales_order_id', $orderIds)->pluck('shipment_instruction_id'));
        $instructionLineIds = DB::table('shipment_instruction_lines')->whereIn('shipment_instruction_id', $instructionIds)->pluck('id');
        $pickIds = DB::table('shipment_picks')->whereIn('shipment_instruction_id', $instructionIds)->pluck('id');

        $shipmentIds = DB::table('shipment_headers')->whereIn('customer_id', $customerIds)->pluck('id');
        $shipmentIds = $this->merge($shipmentIds, DB::table('shipment_headers')->whereIn('source_shipment_pick_id', $pickIds)->pluck('id'));
        $shipmentIds = $this->merge($shipmentIds, DB::table('shipment_headers')->whereIn('source_shipment_instruction_id', $instructionIds)->pluck('id'));
        $shipmentIds = $this->merge($shipmentIds, DB::table('shipment_lines')->whereIn('product_id', $productIds)->pluck('shipment_header_id'));
        $shipmentLineIds = DB::table('shipment_lines')->whereIn('shipment_header_id', $shipmentIds)->pluck('id');

        $invoiceIds = DB::table('invoice_headers')->whereIn('customer_id', $customerIds)->pluck('id');
        $invoiceIds = $this->merge($invoiceIds, DB::table('invoice_lines')->whereIn('shipment_header_id', $shipmentIds)->pluck('invoice_header_id'));
        $invoiceIds = $this->merge($invoiceIds, DB::table('invoice_lines')->whereIn('product_id', $productIds)->pluck('invoice_header_id'));
        $paymentScheduleIds = DB::table('payment_schedules')->whereIn('invoice_header_id', $invoiceIds)->pluck('id');
        $paymentIds = DB::table('payments')->whereIn('customer_id', $customerIds)->pluck('id');
        $paymentIds = $this->merge($paymentIds, DB::table('payment_allocations')->whereIn('payment_schedule_id', $paymentScheduleIds)->pluck('payment_id'));

        $nonSalesOperationIds = DB::table('non_sales_stock_operation_lines')->whereIn('production_lot_id', $lotIds)->pluck('non_sales_stock_operation_header_id');
        $nonSalesLineIds = DB::table('non_sales_stock_operation_lines')->whereIn('non_sales_stock_operation_header_id', $nonSalesOperationIds)->pluck('id');
        $stockMovementIds = DB::table('stock_movements')->whereIn('production_lot_id', $lotIds)->pluck('id');
        $stockMovementIds = $this->merge($stockMovementIds, DB::table('stock_movements')->whereIn('source_shipment_header_id', $shipmentIds)->pluck('id'));
        $stockMovementIds = $this->merge($stockMovementIds, DB::table('stock_movements')->whereIn('source_shipment_line_id', $shipmentLineIds)->pluck('id'));
        $stockMovementIds = $this->merge($stockMovementIds, DB::table('stock_movements')->where('source_type', 'like', '%demo%')->pluck('id'));

        return [
            'users' => $userIds, 'customers' => $customerIds, 'products' => $productIds, 'lots' => $lotIds,
            'orders' => $orderIds, 'order_lines' => $orderLineIds,
            'instructions' => $instructionIds, 'instruction_lines' => $instructionLineIds, 'picks' => $pickIds,
            'shipments' => $shipmentIds, 'shipment_lines' => $shipmentLineIds,
            'invoices' => $invoiceIds, 'payment_schedules' => $paymentScheduleIds, 'payments' => $paymentIds,
            'non_sales_operations' => $nonSalesOperationIds, 'non_sales_lines' => $nonSalesLineIds,
            'stock_movements' => $stockMovementIds,
        ];
    }

    private function guardTaxAndReturns(array $ids): void
    {
        $returnHeaderIds = DB::table('sales_return_headers')->whereIn('customer_id', $ids['customers'])->pluck('id');
        $returnHeaderIds = $this->merge(
            $returnHeaderIds,
            DB::table('sales_return_lines')->whereIn('source_shipment_header_id', $ids['shipments'])->pluck('sales_return_header_id'),
        );
        $returns = $returnHeaderIds->count();
        $taxSources = DB::table('liquor_tax_monthly_filing_sources')
            ->where('source_type', 'shipment')
            ->whereIn('source_header_id', $ids['shipments'])
            ->count();

        if ($returns > 0 || $taxSources > 0) {
            throw new RuntimeException("デモ削除を安全に実行できません。返品={$returns}、確定酒税集計元={$taxSources}");
        }
    }

    private function deleteUserDependencies(array $ids): void
    {
        $approvalIds = DB::table('approval_requests')->whereIn('requested_by_user_id', $ids['users'])->pluck('id');
        $approvalIds = $this->merge($approvalIds, DB::table('approval_request_actions')->whereIn('user_id', $ids['users'])->pluck('approval_request_id'));
        DB::table('approval_requests')->whereIn('id', $approvalIds)->delete();
        DB::table('operation_jobs')->where('reason', 'ilike', '%demo%')->orWhere('reason', 'like', '%デモ%')->delete();
    }

    private function deleteBilling(array $ids): void
    {
        DB::table('payment_allocations')->whereIn('payment_id', $ids['payments'])->orWhereIn('payment_schedule_id', $ids['payment_schedules'])->delete();
        DB::table('payments')->whereIn('id', $ids['payments'])->delete();
        DB::table('payment_schedules')->whereIn('id', $ids['payment_schedules'])->delete();
        DB::table('invoice_lines')->whereIn('invoice_header_id', $ids['invoices'])->delete();
        DB::table('invoice_headers')->whereIn('id', $ids['invoices'])->delete();
        DB::table('receivable_monthly_balances')->whereIn('customer_id', $ids['customers'])->delete();
    }

    private function deleteInventoryOperations(array $ids): void
    {
        DB::table('non_sales_stock_operation_lines')->whereIn('id', $ids['non_sales_lines'])->update(['stock_movement_id' => null]);
        DB::table('non_sales_stock_operation_headers')->whereIn('id', $ids['non_sales_operations'])->delete();
        DB::table('stock_movements')->whereIn('related_stock_movement_id', $ids['stock_movements'])->update(['related_stock_movement_id' => null]);
        DB::table('stock_movements')->whereIn('id', $ids['stock_movements'])->delete();
        DB::table('inventory_count_lines')->whereIn('production_lot_id', $ids['lots'])->delete();
        DB::table('stock_lot_monthly_balances')->whereIn('production_lot_id', $ids['lots'])->delete();
    }

    private function deleteShipmentFlow(array $ids): void
    {
        DB::table('report_exports')->where(function ($query) use ($ids): void {
            $query->where('exportable_type', 'App\\Models\\ShipmentHeader')->whereIn('exportable_id', $ids['shipments']);
        })->orWhere(function ($query) use ($ids): void {
            $query->where('exportable_type', 'App\\Models\\InvoiceHeader')->whereIn('exportable_id', $ids['invoices']);
        })->delete();
        DB::table('shipment_lot_allocations')->whereIn('shipment_header_id', $ids['shipments'])->orWhereIn('production_lot_id', $ids['lots'])->delete();
        DB::table('shipment_lines')->whereIn('id', $ids['shipment_lines'])->delete();
        DB::table('shipment_headers')->whereIn('id', $ids['shipments'])->delete();
        DB::table('shipment_pick_lines')->whereIn('shipment_pick_id', $ids['picks'])->delete();
        DB::table('shipment_picks')->whereIn('id', $ids['picks'])->delete();
        DB::table('shipment_instruction_lines')->whereIn('id', $ids['instruction_lines'])->delete();
        DB::table('shipment_instructions')->whereIn('id', $ids['instructions'])->delete();
        DB::table('sales_order_lines')->whereIn('id', $ids['order_lines'])->delete();
        DB::table('sales_orders')->whereIn('id', $ids['orders'])->delete();
    }

    private function deleteMasterDependencies(array $ids): void
    {
        DB::table('price_review_tasks')->whereIn('product_id', $ids['products'])->orWhereIn('customer_id', $ids['customers'])->delete();
        DB::table('price_rules')->whereIn('product_id', $ids['products'])->orWhereIn('customer_id', $ids['customers'])->delete();
        DB::table('unit_conversions')->whereIn('product_id', $ids['products'])->delete();
    }

    private function demoAuditQuery(array $ids)
    {
        return DB::table('audit_logs')->where(function ($query) use ($ids): void {
            $query->whereIn('user_id', $ids['users'])
                ->orWhereIn('approved_by_user_id', $ids['users'])
                ->orWhere('reason', 'ilike', '%demo%')
                ->orWhere('reason', 'like', '%デモ%');

            foreach (['customers' => 'customers', 'products' => 'products', 'sales_orders' => 'orders', 'shipment_headers' => 'shipments', 'invoice_headers' => 'invoices'] as $table => $key) {
                $query->orWhere(function ($target) use ($table, $key, $ids): void {
                    $target->where('target_table', $table)->whereIn('target_id', $ids[$key]->map(fn ($id) => (string) $id));
                });
            }
        });
    }

    private function merge(Collection $left, Collection $right): Collection
    {
        return $left->merge($right)->unique()->values();
    }
}
