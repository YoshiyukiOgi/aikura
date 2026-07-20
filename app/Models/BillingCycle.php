<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'closing_day',
        'payment_month_offset',
        'payment_day',
        'billing_method',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'closing_day' => 'integer',
            'payment_month_offset' => 'integer',
            'payment_day' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
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
