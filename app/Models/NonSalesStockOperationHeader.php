<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NonSalesStockOperationHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_number',
        'revision_no',
        'status',
        'operation_type',
        'operation_date',
        'liquor_tax_treatment',
        'requires_tax_review',
        'source_sales_return_header_id',
        'confirmed_at',
        'cancelled_at',
        'cancelled_reason',
        'reason',
        'note',
        'legacy_access_stock_operation_id',
        'legacy_access_volume_delta_ml',
    ];

    protected function casts(): array
    {
        return [
            'operation_date' => 'date',
            'revision_no' => 'integer',
            'requires_tax_review' => 'boolean',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'legacy_access_volume_delta_ml' => 'decimal:4',
        ];
    }

    public function sourceSalesReturnHeader(): BelongsTo
    {
        return $this->belongsTo(SalesReturnHeader::class, 'source_sales_return_header_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(NonSalesStockOperationLine::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(NonSalesStockOperationRevision::class)->orderBy('revision_no');
    }
}
