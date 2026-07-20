<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'status',
        'awaiting_shipment_instruction',
        'shipment_returned_at',
        'shipment_returned_reason',
        'customer_id',
        'transaction_category_id',
        'settlement_receivable_category_id',
        'billing_cycle_id',
        'order_date',
        'requested_shipment_date',
        'requested_delivery_date',
        'billing_target_date',
        'customer_order_number',
        'source_type',
        'source_reference',
        'cancelled_reason',
        'cancelled_at',
        'note',
        'work_note',
    ];

    protected function casts(): array
    {
        return [
            'awaiting_shipment_instruction' => 'boolean',
            'shipment_returned_at' => 'datetime',
            'order_date' => 'date',
            'requested_shipment_date' => 'date',
            'requested_delivery_date' => 'date',
            'billing_target_date' => 'date',
            'cancelled_at' => 'datetime',
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

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function shipmentInstructionLines(): HasMany
    {
        return $this->hasMany(ShipmentInstructionLine::class);
    }
}
