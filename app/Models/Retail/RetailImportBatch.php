<?php

namespace App\Models\Retail;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailImportBatch extends RetailModel
{
    use HasFactory;

    protected $fillable = ['retail_company_id', 'source', 'period_from', 'period_to', 'source_file_name', 'source_file_hash', 'status', 'document_count', 'line_count', 'sales_total', 'payment_total', 'source_payload', 'imported_at', 'imported_by'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'sales_total' => 'decimal:2', 'payment_total' => 'decimal:2', 'source_payload' => 'array', 'imported_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(RetailCompany::class, 'retail_company_id'); }
    public function importer(): BelongsTo { return $this->belongsTo(User::class, 'imported_by'); }
}
