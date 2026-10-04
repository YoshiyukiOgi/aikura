<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailLedgerLine extends RetailModel
{
    use HasFactory;

    public const KIND_SALE = 'sale';
    public const KIND_PAYMENT = 'payment';
    public const KIND_ADJUSTMENT = 'adjustment';
    public const KIND_OPENING = 'opening';

    protected $fillable = [
        'retail_ledger_document_id', 'line_no', 'line_kind', 'retail_product_id',
        'source_product_id', 'description', 'quantity', 'unit_price', 'amount',
        'source_line_id', 'source_payload',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2', 'source_payload' => 'array'];
    }

    public function document(): BelongsTo { return $this->belongsTo(RetailLedgerDocument::class, 'retail_ledger_document_id'); }
    public function product(): BelongsTo { return $this->belongsTo(RetailProduct::class, 'retail_product_id'); }
}
