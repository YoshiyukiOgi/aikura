<?php

namespace App\Models\Retail;

class RetailDocumentSequence extends RetailModel
{
    protected $fillable = [
        'code',
        'document_date',
        'current_number',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'current_number' => 'integer',
        ];
    }
}
