<?php

namespace App\Models\Retail;

class RetailCompanySetting extends RetailModel
{
    protected $fillable = [
        'company_key',
        'sale_mode',
        'inventory_sales_policy',
        'delivery_note_policy',
        'invoice_policy',
        'brewery_procurement_policy',
        'external_procurement_policy',
    ];
}
