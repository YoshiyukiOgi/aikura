<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementReceivableCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'receivable_method',
        'export_type',
        'liquor_tax_type',
        'consumption_tax_type',
        'accounting_code',
        'sub_accounting_code',
        'department_code',
        'invoice_required',
        'reduces_stock',
        'requires_tax_review',
        'requires_evidence',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'invoice_required' => 'boolean',
            'reduces_stock' => 'boolean',
            'requires_tax_review' => 'boolean',
            'requires_evidence' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}

