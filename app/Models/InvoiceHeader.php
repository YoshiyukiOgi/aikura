<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InvoiceHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'status',
        'document_type',
        'customer_id',
        'billing_cycle_id',
        'source_sales_return_header_id',
        'invoice_date',
        'billing_period_start',
        'billing_period_end',
        'due_date',
        'previous_balance_amount',
        'period_payment_amount',
        'carried_forward_amount',
        'current_sales_amount',
        'current_tax_amount',
        'current_invoice_amount',
        'tax_calculation_unit',
        'tax_rounding_method',
        'amount_rounding_method',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'confirmed_at',
        'cancelled_at',
        'cancelled_reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'due_date' => 'date',
            'previous_balance_amount' => 'decimal:2',
            'period_payment_amount' => 'decimal:2',
            'carried_forward_amount' => 'decimal:2',
            'current_sales_amount' => 'decimal:2',
            'current_tax_amount' => 'decimal:2',
            'current_invoice_amount' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function reportExports(): MorphMany
    {
        return $this->morphMany(ReportExport::class, 'exportable');
    }

    public function paymentSchedule(): HasOne
    {
        return $this->hasOne(PaymentSchedule::class);
    }

    public function sourceSalesReturnHeader(): BelongsTo
    {
        return $this->belongsTo(SalesReturnHeader::class, 'source_sales_return_header_id');
    }
}
