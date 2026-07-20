<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_code',
        'name',
        'name_kana',
        'email',
        'phone',
        'department',
        'position',
        'is_active',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}

