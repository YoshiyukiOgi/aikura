<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasuProductDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'kasu_type',
        'storage_method',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

