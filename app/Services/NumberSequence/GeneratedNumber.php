<?php

namespace App\Services\NumberSequence;

use Illuminate\Support\Carbon;

class GeneratedNumber
{
    public function __construct(
        public readonly string $sequenceCode,
        public readonly int $number,
        public readonly string $formatted,
        public readonly Carbon $issuedAt,
    ) {
    }
}

