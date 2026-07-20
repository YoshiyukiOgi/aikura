<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'location_type',
        'parent_stock_location_id',
        'is_default_shipping_location',
        'is_default_receiving_location',
        'is_inventory_managed',
        'is_shippable',
        'is_sellable',
        'is_tax_relevant',
        'postal_code',
        'address1',
        'address2',
        'phone',
        'sort_order',
        'description',
        'is_active',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default_shipping_location' => 'boolean',
            'is_default_receiving_location' => 'boolean',
            'is_inventory_managed' => 'boolean',
            'is_shippable' => 'boolean',
            'is_sellable' => 'boolean',
            'is_tax_relevant' => 'boolean',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_stock_location_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_stock_location_id');
    }

    public function productionLots(): HasMany
    {
        return $this->hasMany(ProductionLot::class);
    }
}
