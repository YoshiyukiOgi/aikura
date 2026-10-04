<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailLedgerDocument extends RetailModel
{
    use HasFactory;

    protected $fillable = [
        'retail_company_id', 'retail_customer_id', 'document_no', 'business_date',
        'source', 'source_document_id', 'source_hash', 'status', 'source_payload',
    ];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'source_payload' => 'array'];
    }

    public function company(): BelongsTo { return $this->belongsTo(RetailCompany::class, 'retail_company_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(RetailCustomer::class, 'retail_customer_id'); }
    public function lines(): HasMany { return $this->hasMany(RetailLedgerLine::class); }
}
