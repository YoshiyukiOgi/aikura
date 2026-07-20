<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_number',
        'status',
        'action_type',
        'target_type',
        'target_id',
        'requested_by_user_id',
        'approved_by_user_id',
        'requested_at',
        'approved_at',
        'rejected_at',
        'returned_at',
        'consumed_at',
        'payload',
        'reason',
        'approver_comment',
        'return_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'returned_at' => 'datetime',
            'consumed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalRequestAction::class);
    }
}
