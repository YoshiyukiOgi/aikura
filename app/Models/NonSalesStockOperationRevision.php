<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonSalesStockOperationRevision extends Model
{
    protected $fillable = ['non_sales_stock_operation_header_id', 'revision_no', 'action', 'operation_type', 'operation_date', 'reason', 'note', 'lines', 'created_by_user_id'];

    protected function casts(): array
    {
        return ['revision_no' => 'integer', 'operation_date' => 'date', 'lines' => 'array'];
    }

    public function header(): BelongsTo
    {
        return $this->belongsTo(NonSalesStockOperationHeader::class, 'non_sales_stock_operation_header_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
