<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'prefix',
        'suffix',
        'current_number',
        'padding_length',
        'reset_type',
        'last_reset_on',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_number' => 'integer',
            'padding_length' => 'integer',
            'last_reset_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}

