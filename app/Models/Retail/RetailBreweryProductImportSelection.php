<?php

namespace App\Models\Retail;

class RetailBreweryProductImportSelection extends RetailModel
{
    protected $fillable = [
        'brewery_product_id',
        'is_selected',
    ];

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
        ];
    }
}
