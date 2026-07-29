<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquorTaxMonthlyFilingSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'liquor_tax_monthly_filing_id', 'liquor_tax_monthly_filing_line_id',
        'source_type', 'source_header_id', 'source_line_id', 'source_document_number',
        'source_date', 'tax_treatment', 'quantity', 'taxable_kl', 'gross_tax_amount',
        'reporting_alcohol_percentage', 'requires_review', 'review_reason',
        'evidence_status', 'evidence_reference',
    ];

    protected function casts(): array
    {
        return [
            'source_date' => 'date', 'quantity' => 'decimal:4', 'taxable_kl' => 'decimal:6',
            'gross_tax_amount' => 'decimal:2', 'requires_review' => 'boolean',
            'reporting_alcohol_percentage' => 'integer',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxMonthlyFiling::class, 'liquor_tax_monthly_filing_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxMonthlyFilingLine::class, 'liquor_tax_monthly_filing_line_id');
    }
}
