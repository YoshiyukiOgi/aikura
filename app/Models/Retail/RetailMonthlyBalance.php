<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailMonthlyBalance extends RetailModel
{
    use HasFactory;

    protected $fillable = ['retail_monthly_closing_id', 'retail_customer_id', 'opening_amount', 'sales_amount', 'payment_amount', 'adjustment_amount', 'closing_amount'];

    protected function casts(): array
    {
        return ['opening_amount' => 'decimal:2', 'sales_amount' => 'decimal:2', 'payment_amount' => 'decimal:2', 'adjustment_amount' => 'decimal:2', 'closing_amount' => 'decimal:2'];
    }

    public function closing(): BelongsTo { return $this->belongsTo(RetailMonthlyClosing::class, 'retail_monthly_closing_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(RetailCustomer::class, 'retail_customer_id'); }
}
