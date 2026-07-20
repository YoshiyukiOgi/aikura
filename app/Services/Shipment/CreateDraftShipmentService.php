<?php

namespace App\Services\Shipment;

use App\Exceptions\Shipment\ShipmentDraftException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ShipmentHeader;
use App\Models\Unit;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\NumberSequence\NumberSequenceService;
use Illuminate\Support\Facades\DB;

class CreateDraftShipmentService
{
    public function __construct(
        private readonly NumberSequenceService $numberSequenceService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function create(CreateDraftShipmentData $data): ShipmentHeader
    {
        if ($data->lines === []) {
            throw ShipmentDraftException::emptyLines();
        }

        return DB::transaction(function () use ($data): ShipmentHeader {
            $customer = Customer::query()->findOrFail($data->customerId);

            if (! $customer->is_active) {
                throw ShipmentDraftException::inactiveCustomer($customer->id);
            }

            $documentNumber = $this->numberSequenceService
                ->next('shipment_document')
                ->formatted;

            $shipment = ShipmentHeader::create([
                'document_number' => $documentNumber,
                'status' => 'draft',
                'customer_id' => $customer->id,
                'transaction_category_id' => $customer->transaction_category_id,
                'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
                'billing_cycle_id' => $customer->billing_cycle_id,
                'document_date' => $data->documentDate,
                'order_date' => $data->orderDate,
                'scheduled_shipment_date' => $data->scheduledShipmentDate,
                'billing_target_date' => $data->billingTargetDate ?? $data->documentDate,
                'liquor_tax_transfer_date' => $data->liquorTaxTransferDate,
                'note' => $data->note,
            ]);

            foreach (array_values($data->lines) as $index => $lineData) {
                $this->validateLine($lineData);

                $shipment->lines()->create([
                    'line_no' => $index + 1,
                    'product_id' => $lineData->productId,
                    'quantity' => $lineData->quantity,
                    'unit_id' => $lineData->unitId,
                    'note' => $lineData->note,
                ]);
            }

            $this->auditLogService->record(new AuditLogData(
                event: 'shipment.draft_created',
                auditable: $shipment,
                afterValues: [
                    'document_number' => $shipment->document_number,
                    'status' => $shipment->status,
                    'customer_id' => $shipment->customer_id,
                    'line_count' => count($data->lines),
                ],
                reason: $data->reason,
            ));

            return $shipment->load(['customer', 'lines.product', 'lines.unit']);
        });
    }

    private function validateLine(CreateDraftShipmentLineData $lineData): void
    {
        if (bccomp($lineData->quantity, '0', 4) <= 0) {
            throw ShipmentDraftException::invalidQuantity($lineData->quantity);
        }

        $product = Product::query()->findOrFail($lineData->productId);

        if (! $product->is_active || ! $product->is_sales_available) {
            throw ShipmentDraftException::inactiveProduct($product->id);
        }

        $unit = Unit::query()->findOrFail($lineData->unitId);

        if (! $unit->is_active) {
            throw ShipmentDraftException::inactiveUnit($unit->id);
        }
    }
}
