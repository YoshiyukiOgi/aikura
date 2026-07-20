<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_code',
        'name',
        'name_kana',
        'short_name',
        'billing_name',
        'postal_code',
        'address1',
        'address2',
        'phone',
        'fax',
        'email',
        'contact_name',
        'transaction_category_id',
        'settlement_receivable_category_id',
        'billing_cycle_id',
        'tax_rounding_method',
        'tax_calculation_unit',
        'amount_rounding_method',
        'invoice_required',
        'search_key',
        'legacy_code',
        'legacy_name',
        'note',
        'is_active',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_required' => 'boolean',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
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

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(ShipmentHeader::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(InvoiceHeader::class);
    }
}
