<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShipmentHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'status',
        'customer_id',
        'transaction_category_id',
        'settlement_receivable_category_id',
        'billing_cycle_id',
        'document_date',
        'document_issued_at',
        'order_date',
        'scheduled_shipment_date',
        'actual_shipment_date',
        'sales_recorded_on',
        'billing_target_date',
        'liquor_tax_transfer_date',
        'confirmed_settlement_receivable_category_id',
        'confirmed_settlement_receivable_category_code',
        'confirmed_settlement_receivable_category_name',
        'confirmed_liquor_tax_treatment',
        'confirmed_consumption_tax_treatment',
        'confirmed_export_type',
        'confirmed_receivable_method',
        'confirmed_invoice_required',
        'confirmed_requires_tax_review',
        'confirmed_requires_evidence',
        'source_shipment_header_id',
        'source_shipment_pick_id',
        'source_shipment_instruction_id',
        'correction_reason',
        'cancelled_at',
        'cancelled_reason',
        'note',
        'legacy_access_document_number',
        'legacy_access_net_amount',
        'legacy_access_consumption_tax_amount',
        'legacy_access_total_amount',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'document_issued_at' => 'datetime',
            'order_date' => 'date',
            'scheduled_shipment_date' => 'date',
            'actual_shipment_date' => 'date',
            'sales_recorded_on' => 'date',
            'billing_target_date' => 'date',
            'liquor_tax_transfer_date' => 'date',
            'confirmed_invoice_required' => 'boolean',
            'confirmed_requires_tax_review' => 'boolean',
            'confirmed_requires_evidence' => 'boolean',
            'cancelled_at' => 'datetime',
            'legacy_access_net_amount' => 'decimal:2',
            'legacy_access_consumption_tax_amount' => 'decimal:2',
            'legacy_access_total_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactionCategory(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    public function settlementReceivableCategory(): BelongsTo
    {
        return $this->belongsTo(SettlementReceivableCategory::class);
    }

    public function confirmedSettlementReceivableCategory(): BelongsTo
    {
        return $this->belongsTo(SettlementReceivableCategory::class, 'confirmed_settlement_receivable_category_id');
    }

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function sourceShipment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_shipment_header_id');
    }

    public function sourceShipmentPick(): BelongsTo
    {
        return $this->belongsTo(ShipmentPick::class, 'source_shipment_pick_id');
    }

    public function sourceShipmentInstruction(): BelongsTo
    {
        return $this->belongsTo(ShipmentInstruction::class, 'source_shipment_instruction_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ShipmentLine::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'source_shipment_header_id');
    }

    public function liquorTaxEvidence(): HasOne
    {
        return $this->hasOne(ShipmentLiquorTaxEvidence::class);
    }
}
