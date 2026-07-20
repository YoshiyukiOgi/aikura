<?php

namespace App\Services\Tax;

use InvalidArgumentException;

class TaxRoundingService
{
    public function round(string $amount, string $method, int $scale = 2): string
    {
        $amount = $this->normalize($amount, $scale + 1);

        return match ($method) {
            'floor' => $this->floor($amount, $scale),
            'ceil' => $this->ceil($amount, $scale),
            'round' => $this->roundHalfUp($amount, $scale),
            default => throw new InvalidArgumentException("Unsupported rounding method: {$method}."),
        };
    }

    private function floor(string $amount, int $scale): string
    {
        return bcadd($amount, '0', $scale);
    }

    private function ceil(string $amount, int $scale): string
    {
        $truncated = bcadd($amount, '0', $scale);

        if (bccomp($amount, $truncated, $scale + 1) === 1) {
            return bcadd($truncated, $this->increment($scale), $scale);
        }

        return $truncated;
    }

    private function roundHalfUp(string $amount, int $scale): string
    {
        return bcadd($amount, $this->roundingIncrement($scale), $scale);
    }

    private function normalize(string $amount, int $scale): string
    {
        return bcadd($amount, '0', $scale);
    }

    private function increment(int $scale): string
    {
        return '0.'.str_repeat('0', max(0, $scale - 1)).'1';
    }

    private function roundingIncrement(int $scale): string
    {
        return '0.'.str_repeat('0', $scale).'5';
    }
}
