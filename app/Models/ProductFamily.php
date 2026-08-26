<?php

namespace App\Models;

use App\Support\SearchTextNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductFamily extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (ProductFamily $family): void {
            if (blank($family->search_key)) {
                $family->search_key = SearchTextNormalizer::searchKey(
                    $family->name,
                    $family->name_kana,
                    $family->brand_name,
                    $family->category_name,
                    $family->liquor_type_name,
                );
            }

            $family->search_key_normalized = SearchTextNormalizer::normalize($family->search_key);
        });
    }

    protected $fillable = [
        'family_code', 'product_type', 'name', 'name_kana', 'brand_name', 'category_name',
        'consumption_tax_category_id', 'alcohol_percentage', 'is_alcohol',
        'liquor_tax_category_code', 'liquor_type_name', 'ingredients', 'rice_polishing_ratio',
        'production_method', 'is_unpasteurized', 'is_sales_available', 'is_inventory_managed',
        'search_key', 'search_key_normalized', 'note', 'is_active', 'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'alcohol_percentage' => 'decimal:2',
            'rice_polishing_ratio' => 'decimal:2',
            'is_alcohol' => 'boolean',
            'is_unpasteurized' => 'boolean',
            'is_sales_available' => 'boolean',
            'is_inventory_managed' => 'boolean',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function consumptionTaxCategory(): BelongsTo
    {
        return $this->belongsTo(ConsumptionTaxCategory::class);
    }
}
