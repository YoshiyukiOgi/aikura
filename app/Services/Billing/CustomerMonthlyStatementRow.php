<?php

namespace App\Services\Billing;

readonly class CustomerMonthlyStatementRow
{
    public function __construct(
        public int $customerId,
        public string $customerCode,
        public ?string $legacyCustomerId,
        public string $customerName,
        public ?int $sortOrder,
        public ?string $settlementReceivableCategory,
        public ?string $prefecture,
        public ?string $businessType,
        public string $sakeShipmentAmount,
        public string $sakeReturnAmount,
        public string $kasuShipmentAmount,
        public string $otherShipmentAmount,
        public string $discountAmount,
        public string $taxAmount,
        public string $paymentAmount,
        public string $bankFeeAmount,
        public string $previousInvoiceAmount,
        public string $invoiceAmount,
        public string $receivableBalanceAmount,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'customer_id' => $this->customerId,
            'customer_code' => $this->customerCode,
            'legacy_customer_id' => $this->legacyCustomerId,
            'customer_name' => $this->customerName,
            'sort_order' => $this->sortOrder,
            'settlement_receivable_category' => $this->settlementReceivableCategory,
            'prefecture' => $this->prefecture,
            'business_type' => $this->businessType,
            'sake_shipment_amount' => $this->sakeShipmentAmount,
            'sake_return_amount' => $this->sakeReturnAmount,
            'kasu_shipment_amount' => $this->kasuShipmentAmount,
            'other_shipment_amount' => $this->otherShipmentAmount,
            'discount_amount' => $this->discountAmount,
            'tax_amount' => $this->taxAmount,
            'payment_amount' => $this->paymentAmount,
            'bank_fee_amount' => $this->bankFeeAmount,
            'previous_invoice_amount' => $this->previousInvoiceAmount,
            'invoice_amount' => $this->invoiceAmount,
            'receivable_balance_amount' => $this->receivableBalanceAmount,
        ];
    }
}
