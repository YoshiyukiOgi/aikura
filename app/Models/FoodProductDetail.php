<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodProductDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'food_category',
        'allergen_note',
        'storage_method',
        'shelf_life_days',
    ];

    protected function casts(): array
    {
        return [
            'shelf_life_days' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

