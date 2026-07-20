<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_type',
        'status',
        'target_type',
        'target_id',
        'idempotency_key',
        'payload',
        'attempts',
        'retry_of_operation_job_id',
        'started_at',
        'finished_at',
        'failed_at',
        'cancelled_at',
        'error_message',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
