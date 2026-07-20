<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquorTaxMonthlyFilingAdjustment extends Model
{
    protected $fillable = [
        'liquor_tax_monthly_filing_id', 'line_no', 'status', 'adjustment_type', 'liquor_tax_category_id',
        'description', 'taxable_kl_adjustment', 'tax_amount_adjustment', 'approval_required',
        'approval_request_id', 'created_by_user_id', 'voided_by_user_id', 'voided_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'taxable_kl_adjustment' => 'decimal:6', 'tax_amount_adjustment' => 'decimal:2',
            'approval_required' => 'boolean', 'voided_at' => 'datetime',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxMonthlyFiling::class, 'liquor_tax_monthly_filing_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxCategory::class, 'liquor_tax_category_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }
}
