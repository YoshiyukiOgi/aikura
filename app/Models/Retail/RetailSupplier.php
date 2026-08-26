<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailSupplier extends RetailModel
{
    protected $fillable = [
        'supplier_code',
        'name',
        'supplier_type',
        'ordering_method',
        'brewery_partner_id',
        'phone',
        'email',
        'postal_code',
        'address1',
        'address2',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(RetailProduct::class, 'retail_supplier_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(RetailPurchaseOrder::class, 'retail_supplier_id');
    }
}
