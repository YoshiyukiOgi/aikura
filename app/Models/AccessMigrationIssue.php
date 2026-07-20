<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessMigrationIssue extends Model
{
    protected $fillable = [
        'batch_id',
        'severity',
        'issue_code',
        'source_table',
        'source_row_number',
        'source_key',
        'source_field',
        'message',
        'context',
        'resolved_at',
        'resolved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AccessMigrationBatch::class, 'batch_id');
    }
}
