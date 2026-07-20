<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SakeProductDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'liquor_tax_category_code',
        'liquor_type_name',
        'ingredients',
        'rice_polishing_ratio',
        'production_method',
        'is_unpasteurized',
    ];

    protected function casts(): array
    {
        return [
            'rice_polishing_ratio' => 'decimal:2',
            'is_unpasteurized' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

