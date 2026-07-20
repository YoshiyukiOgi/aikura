<?php

namespace App\Services\Billing;

class CreateInvoiceDraftData
{
    /**
     * @param array<int, int>|null $shipmentHeaderIds
     */
    public function __construct(
        public readonly int $customerId,
        public readonly string $invoiceDate,
        public readonly ?string $billingPeriodStart = null,
        public readonly ?string $billingPeriodEnd = null,
        public readonly ?string $dueDate = null,
        public readonly ?string $note = null,
        public readonly ?string $reason = null,
        public readonly ?array $shipmentHeaderIds = null,
        public readonly bool $includeCarriedForward = true,
    ) {
    }
}

