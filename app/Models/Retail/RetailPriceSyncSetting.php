<?php

namespace App\Models\Retail;

class RetailPriceSyncSetting extends RetailModel
{
    protected $fillable = [
        'detection_mode',
        'interval_minutes',
        'last_detected_at',
        'last_detection_summary',
        'last_detection_error',
    ];

    protected function casts(): array
    {
        return [
            'last_detected_at' => 'datetime',
            'last_detection_summary' => 'array',
        ];
    }

    public function isDue(): bool
    {
        if ($this->detection_mode === 'manual') {
            return false;
        }

        if ($this->last_detected_at === null) {
            return true;
        }

        if (in_array($this->detection_mode, ['daily', 'auto'], true)) {
            return $this->last_detected_at->lt(now()->startOfDay());
        }

        return $this->detection_mode === 'interval'
            && $this->last_detected_at->lte(now()->subMinutes(max(15, (int) $this->interval_minutes)));
    }

    public function nextDetectionAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->detection_mode === 'manual') {
            return null;
        }

        if ($this->last_detected_at === null) {
            return now();
        }

        return match ($this->detection_mode) {
            'daily', 'auto' => $this->last_detected_at?->gte(now()->startOfDay())
                ? now()->addDay()->startOfDay()
                : now()->startOfDay(),
            'interval' => ($this->last_detected_at ?? now())->copy()->addMinutes(max(15, (int) $this->interval_minutes)),
            default => null,
        };
    }
}
