<?php

namespace App\Services\Billing;

class CreateSalesReturnData
{
    /**
     * @param array<int, CreateSalesReturnLineData> $lines
     */
    public function __construct(
        public readonly int $customerId,
        public readonly string $returnDate,
        public readonly string $reason,
        public readonly array $lines,
        public readonly string $settlementMethod = 'credit_memo',
        public readonly ?string $note = null,
    ) {
    }
}
