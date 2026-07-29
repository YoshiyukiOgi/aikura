<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccessMigrationBatch extends Model
{
    protected $fillable = [
        'baseline_batch_id',
        'status',
        'source_file_name',
        'source_file_path',
        'source_sha256',
        'source_size',
        'source_last_modified_at',
        'extractor_version',
        'package_version',
        'source_table_count',
        'source_row_count',
        'staged_table_count',
        'staged_row_count',
        'error_count',
        'warning_count',
        'manifest',
        'validation_summary',
        'delta_summary',
        'delta_planned_at',
        'delta_applied_at',
        'failure_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_last_modified_at' => 'immutable_datetime',
            'manifest' => 'array',
            'validation_summary' => 'array',
            'delta_summary' => 'array',
            'delta_planned_at' => 'immutable_datetime',
            'delta_applied_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function tables(): HasMany
    {
        return $this->hasMany(AccessMigrationTable::class, 'batch_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(AccessMigrationIssue::class, 'batch_id');
    }

    public function baselineBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'baseline_batch_id');
    }

    public function deltas(): HasMany
    {
        return $this->hasMany(AccessMigrationDelta::class, 'batch_id');
    }
}
