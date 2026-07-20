<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCountHeader extends Model
{
    protected $fillable = ['year', 'month', 'count_date', 'status', 'started_at', 'counted_at', 'confirmed_at', 'reason', 'note'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'count_date' => 'date', 'started_at' => 'datetime', 'counted_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class)->orderBy('line_no');
    }
}
