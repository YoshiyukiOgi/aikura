<?php

namespace App\Models\Retail;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailMonthlyClosing extends RetailModel
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = ['retail_company_id', 'period', 'status', 'document_count', 'line_count', 'checksum', 'closed_at', 'closed_by', 'note'];

    protected function casts(): array
    {
        return ['period' => 'date', 'closed_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(RetailCompany::class, 'retail_company_id'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
    public function balances(): HasMany { return $this->hasMany(RetailMonthlyBalance::class); }
}
