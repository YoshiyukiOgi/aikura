<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessMigrationTable extends Model
{
    protected $fillable = [
        'batch_id',
        'source_table',
        'package_file',
        'package_sha256',
        'source_row_count',
        'staged_row_count',
        'source_columns',
        'status',
    ];

    protected function casts(): array
    {
        return ['source_columns' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AccessMigrationBatch::class, 'batch_id');
    }
}
