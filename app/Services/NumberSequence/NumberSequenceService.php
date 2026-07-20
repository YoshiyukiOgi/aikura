<?php

namespace App\Services\NumberSequence;

use App\Exceptions\NumberSequence\NumberSequenceException;
use App\Models\NumberSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    public function next(string $code, ?Carbon $issuedAt = null): GeneratedNumber
    {
        $issuedAt ??= Carbon::now();

        return DB::transaction(function () use ($code, $issuedAt): GeneratedNumber {
            $sequence = NumberSequence::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                throw NumberSequenceException::notFound($code);
            }

            if (! $sequence->is_active) {
                throw NumberSequenceException::inactive($code);
            }

            if ($this->shouldReset($sequence, $issuedAt)) {
                $sequence->current_number = 0;
                $sequence->last_reset_on = $issuedAt->toDateString();
            }

            $sequence->current_number++;
            $sequence->save();

            $formatted = $this->format($sequence, $sequence->current_number, $issuedAt);

            return new GeneratedNumber(
                sequenceCode: $sequence->code,
                number: $sequence->current_number,
                formatted: $formatted,
                issuedAt: $issuedAt,
            );
        });
    }

    private function shouldReset(NumberSequence $sequence, Carbon $issuedAt): bool
    {
        $resetType = $sequence->reset_type;

        if ($resetType === 'none') {
            return false;
        }

        $lastResetOn = $sequence->last_reset_on;

        if (! $lastResetOn) {
            return true;
        }

        return match ($resetType) {
            'year' => $lastResetOn->year !== $issuedAt->year,
            'month' => $lastResetOn->format('Y-m') !== $issuedAt->format('Y-m'),
            'day' => $lastResetOn->toDateString() !== $issuedAt->toDateString(),
            default => throw NumberSequenceException::unsupportedResetType($resetType),
        };
    }

    private function format(NumberSequence $sequence, int $number, Carbon $issuedAt): string
    {
        $body = str_pad((string) $number, $sequence->padding_length, '0', STR_PAD_LEFT);

        return $this->replaceDateTokens((string) $sequence->prefix, $issuedAt)
            .$body
            .$this->replaceDateTokens((string) $sequence->suffix, $issuedAt);
    }

    private function replaceDateTokens(string $value, Carbon $issuedAt): string
    {
        return strtr($value, [
            '{YYYY}' => $issuedAt->format('Y'),
            '{YY}' => $issuedAt->format('y'),
            '{MM}' => $issuedAt->format('m'),
            '{DD}' => $issuedAt->format('d'),
        ]);
    }
}

