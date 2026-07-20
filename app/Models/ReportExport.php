<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReportExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'format',
        'status',
        'exportable_type',
        'exportable_id',
        'disk',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'checksum_sha256',
        'generated_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function exportable(): MorphTo
    {
        return $this->morphTo();
    }
}
