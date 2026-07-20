<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceReviewTask extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_KEPT = 'kept';
    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'product_id',
        'changed_price_rule_id',
        'affected_price_rule_id',
        'customer_id',
        'transaction_category_id',
        'old_reference_price',
        'new_reference_price',
        'current_individual_price',
        'status',
        'message',
        'reason',
        'created_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_reference_price' => 'decimal:4',
            'new_reference_price' => 'decimal:4',
            'current_individual_price' => 'decimal:4',
            'reviewed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedPriceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class, 'changed_price_rule_id');
    }

    public function affectedPriceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class, 'affected_price_rule_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactionCategory(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }
}
