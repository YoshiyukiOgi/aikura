<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquorTaxAdjustmentSetting extends Model
{
    protected $fillable = ['manufacturing_site_code', 'approval_amount_threshold', 'approval_quantity_threshold_kl', 'is_active'];

    protected function casts(): array
    {
        return [
            'approval_amount_threshold' => 'decimal:2',
            'approval_quantity_threshold_kl' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }
}
