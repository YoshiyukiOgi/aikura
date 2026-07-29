<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessMigrationDelta extends Model
{
    protected $fillable = [
        'batch_id',
        'baseline_batch_id',
        'source_table',
        'source_key',
        'change_type',
        'current_staging_row_id',
        'baseline_staging_row_id',
        'current_payload_sha256',
        'baseline_payload_sha256',
        'apply_status',
        'note',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AccessMigrationBatch::class, 'batch_id');
    }

    public function baselineBatch(): BelongsTo
    {
        return $this->belongsTo(AccessMigrationBatch::class, 'baseline_batch_id');
    }
}
