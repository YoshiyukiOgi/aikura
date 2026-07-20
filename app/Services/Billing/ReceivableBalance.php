<?php

namespace App\Services\Billing;

class ReceivableBalance
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $customerCode,
        public readonly string $customerName,
        public readonly string $scheduledAmount,
        public readonly string $receivedAmount,
        public readonly string $outstandingAmount,
        public readonly int $openScheduleCount,
        public readonly int $partialScheduleCount,
        public readonly int $closedScheduleCount,
    ) {
    }
}
