<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailCustomer extends RetailModel
{
    protected $fillable = [
        'retail_company_id',
        'customer_code',
        'name',
        'name_kana',
        'billing_name',
        'billing_method',
        'postal_code',
        'address1',
        'address2',
        'phone',
        'fax',
        'email',
        'closing_day',
        'payment_month_offset',
        'payment_day',
        'credit_limit',
        'invoice_required',
        'note',
        'is_active',
    ];

    public function scopeAvailableToCompany(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $query) use ($companyId): void {
            $query->whereNull('retail_company_id')->orWhere('retail_company_id', $companyId);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(RetailCompany::class, 'retail_company_id');
    }

    protected function casts(): array
    {
        return [
            'closing_day' => 'integer',
            'payment_month_offset' => 'integer',
            'payment_day' => 'integer',
            'credit_limit' => 'decimal:2',
            'invoice_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(RetailSale::class, 'retail_customer_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(RetailInvoice::class, 'retail_customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RetailPayment::class, 'retail_customer_id');
    }
}
