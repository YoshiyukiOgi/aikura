<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentLiquorTaxEvidence extends Model
{
    use HasFactory;

    protected $table = 'shipment_liquor_tax_evidences';

    protected $fillable = [
        'shipment_header_id', 'tax_treatment', 'status', 'evidence_reference',
        'evidence_date', 'destination', 'customs_office', 'exporter_type', 'note',
        'document_file_path', 'document_file_name', 'document_file_size',
        'document_mime_type', 'document_checksum_sha256', 'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_date' => 'date',
            'document_file_size' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ShipmentHeader::class, 'shipment_header_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
