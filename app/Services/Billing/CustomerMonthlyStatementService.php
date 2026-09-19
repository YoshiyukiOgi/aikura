<?php

namespace App\Services\Billing;

use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerMonthlyStatementService
{
    /** @return Collection<int, CustomerMonthlyStatementRow> */
    public function forMonth(int $year, int $month, bool $includeZeroRows = false): Collection
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->toDateString();
        $periodEnd = CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString();

        $amounts = [];

        $this->invoiceLines($periodStart, $periodEnd)->each(function (object $row) use (&$amounts): void {
            $item = &$amounts[(int) $row->customer_id];
            $item ??= $this->emptyAmounts();

            $amount = (string) $row->amount;
            $tax = (string) $row->tax_amount;
            $invoice = (string) $row->total_amount;
            $productType = (string) ($row->product_type ?? '');
            $categoryName = (string) ($row->category_name ?? '');
            $isReturn = $row->source_sales_return_line_id !== null || bccomp($amount, '0', 2) < 0;

            if ($productType === 'sake') {
                if ($isReturn) {
                    $item['sake_return_amount'] = bcadd($item['sake_return_amount'], $amount, 2);
                } else {
                    $item['sake_shipment_amount'] = bcadd($item['sake_shipment_amount'], $amount, 2);
                }
            } elseif ($productType === 'kasu') {
                $item['kasu_shipment_amount'] = bcadd($item['kasu_shipment_amount'], $amount, 2);
            } elseif ($categoryName === '値引') {
                $item['discount_amount'] = bcadd($item['discount_amount'], $amount, 2);
            } else {
                $item['other_shipment_amount'] = bcadd($item['other_shipment_amount'], $amount, 2);
            }

            $item['tax_amount'] = bcadd($item['tax_amount'], $tax, 2);
            $item['invoice_amount'] = bcadd($item['invoice_amount'], $invoice, 2);
        });

        $this->payments($periodStart, $periodEnd)->each(function (object $row) use (&$amounts): void {
            $item = &$amounts[(int) $row->customer_id];
            $item ??= $this->emptyAmounts();

            $paymentAmount = $this->receivableDecreaseAmount((string) $row->amount);

            if ($row->payment_method === 'bank_fee') {
                $item['bank_fee_amount'] = bcadd($item['bank_fee_amount'], $paymentAmount, 2);
            } else {
                $item['payment_amount'] = bcadd($item['payment_amount'], $paymentAmount, 2);
            }
        });

        $this->previousBalances($periodStart)->each(function (object $row) use (&$amounts): void {
            $item = &$amounts[(int) $row->customer_id];
            $item ??= $this->emptyAmounts();
            $item['previous_invoice_amount'] = bcadd($item['previous_invoice_amount'], (string) $row->opening_balance_amount, 2);
        });

        /** @var Collection<int, Customer> $customers */
        $customers = Customer::query()
            ->with('settlementReceivableCategory')
            ->whereIn('id', array_keys($amounts))
            ->get()
            ->keyBy('id');

        return collect($amounts)
            ->map(function (array $amount, int $customerId) use ($customers): ?CustomerMonthlyStatementRow {
                $customer = $customers->get($customerId);
                if (! $customer) {
                    return null;
                }

                $receivableBalance = bcadd(
                    bcadd($amount['previous_invoice_amount'], $amount['invoice_amount'], 2),
                    bcadd($amount['payment_amount'], $amount['bank_fee_amount'], 2),
                    2,
                );

                return new CustomerMonthlyStatementRow(
                    customerId: (int) $customer->id,
                    customerCode: (string) $customer->customer_code,
                    legacyCustomerId: $customer->legacy_code,
                    customerName: $customer->billing_name ?: $customer->name,
                    sortOrder: $customer->settlementReceivableCategory?->id,
                    settlementReceivableCategory: $customer->settlementReceivableCategory?->name,
                    prefecture: $this->prefecture($customer->address1),
                    businessType: $this->businessType($customer->note),
                    sakeShipmentAmount: $this->zeroNormalize($amount['sake_shipment_amount']),
                    sakeReturnAmount: $this->zeroNormalize($amount['sake_return_amount']),
                    kasuShipmentAmount: $this->zeroNormalize($amount['kasu_shipment_amount']),
                    otherShipmentAmount: $this->zeroNormalize($amount['other_shipment_amount']),
                    discountAmount: $this->zeroNormalize($amount['discount_amount']),
                    taxAmount: $this->zeroNormalize($amount['tax_amount']),
                    paymentAmount: $this->zeroNormalize($amount['payment_amount']),
                    bankFeeAmount: $this->zeroNormalize($amount['bank_fee_amount']),
                    previousInvoiceAmount: $this->zeroNormalize($amount['previous_invoice_amount']),
                    invoiceAmount: $this->zeroNormalize($amount['invoice_amount']),
                    receivableBalanceAmount: $this->zeroNormalize($receivableBalance),
                );
            })
            ->filter()
            ->filter(fn (CustomerMonthlyStatementRow $row): bool => $includeZeroRows
                || bccomp($row->receivableBalanceAmount, '0.00', 2) !== 0
                || bccomp($row->invoiceAmount, '0.00', 2) !== 0
                || bccomp($row->paymentAmount, '0.00', 2) !== 0
                || bccomp($row->bankFeeAmount, '0.00', 2) !== 0)
            ->sortBy(fn (CustomerMonthlyStatementRow $row): array => [
                $row->sortOrder ?? 999999,
                (int) ($row->legacyCustomerId ?? 99999999),
                $row->customerCode,
            ])
            ->values();
    }

    /** @return array<string, string> */
    public function totals(Collection $rows): array
    {
        $keys = [
            'sakeShipmentAmount',
            'sakeReturnAmount',
            'kasuShipmentAmount',
            'otherShipmentAmount',
            'discountAmount',
            'taxAmount',
            'paymentAmount',
            'bankFeeAmount',
            'previousInvoiceAmount',
            'invoiceAmount',
            'receivableBalanceAmount',
        ];

        $totals = [];
        foreach ($keys as $property) {
            $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property));
            $totals[$snake] = $this->zeroNormalize($rows->reduce(
                fn (string $carry, CustomerMonthlyStatementRow $row): string => bcadd($carry, $row->{$property}, 2),
                '0.00',
            ));
        }

        return $totals;
    }

    /** @return array<int, array<string, mixed>> */
    public function totalsBySettlementReceivableCategory(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (CustomerMonthlyStatementRow $row): string => $row->settlementReceivableCategory ?: '未設定')
            ->map(function (Collection $categoryRows, string $categoryName): array {
                $first = $categoryRows->first();

                return [
                    'sort_order' => $first instanceof CustomerMonthlyStatementRow ? $first->sortOrder : null,
                    'settlement_receivable_category' => $categoryName,
                    'customer_count' => $categoryRows->count(),
                    'totals' => $this->totals($categoryRows),
                ];
            })
            ->sortBy(fn (array $row): array => [
                $row['sort_order'] ?? 999999,
                $row['settlement_receivable_category'],
            ])
            ->values()
            ->all();
    }

    private function invoiceLines(string $periodStart, string $periodEnd): Collection
    {
        return DB::table('invoice_lines as il')
            ->join('invoice_headers as ih', 'ih.id', '=', 'il.invoice_header_id')
            ->leftJoin('products as p', 'p.id', '=', 'il.product_id')
            ->where(function ($query) use ($periodStart, $periodEnd): void {
                $query
                    ->where(function ($periodQuery) use ($periodStart, $periodEnd): void {
                        $periodQuery
                            ->whereNotNull('ih.billing_period_end')
                            ->whereDate('ih.billing_period_end', '>=', $periodStart)
                            ->whereDate('ih.billing_period_end', '<=', $periodEnd);
                    })
                    ->orWhere(function ($invoiceDateQuery) use ($periodStart, $periodEnd): void {
                        $invoiceDateQuery
                            ->whereNull('ih.billing_period_end')
                            ->whereDate('ih.invoice_date', '>=', $periodStart)
                            ->whereDate('ih.invoice_date', '<=', $periodEnd);
                    });
            })
            ->whereIn('ih.status', ['confirmed', 'closed'])
            ->whereNull('ih.cancelled_at')
            ->selectRaw('
                ih.customer_id,
                il.source_sales_return_line_id,
                COALESCE(p.product_type, \'goods\') as product_type,
                COALESCE(p.category_name, \'\') as category_name,
                COALESCE(SUM(il.amount), 0) as amount,
                COALESCE(SUM(il.tax_amount), 0) as tax_amount,
                COALESCE(SUM(il.total_amount), 0) as total_amount
            ')
            ->groupBy('ih.customer_id', 'il.source_sales_return_line_id', 'p.product_type', 'p.category_name')
            ->get();
    }

    private function payments(string $periodStart, string $periodEnd): Collection
    {
        return DB::table('payments')
            ->whereDate('payment_date', '>=', $periodStart)
            ->whereDate('payment_date', '<=', $periodEnd)
            ->whereIn('status', ['registered', 'confirmed', 'allocated', 'review_required', 'legacy_imported'])
            ->whereNull('cancelled_at')
            ->selectRaw('customer_id, payment_method, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('customer_id', 'payment_method')
            ->get();
    }

    private function previousBalances(string $periodStart): Collection
    {
        return DB::table('opening_receivable_balances')
            ->whereDate('as_of_date', '<=', $periodStart)
            ->whereIn('status', ['calculated', 'reconciled'])
            ->selectRaw('customer_id, COALESCE(SUM(opening_balance_amount), 0) as opening_balance_amount')
            ->groupBy('customer_id')
            ->get();
    }

    /** @return array<string, string> */
    private function emptyAmounts(): array
    {
        return [
            'sake_shipment_amount' => '0.00',
            'sake_return_amount' => '0.00',
            'kasu_shipment_amount' => '0.00',
            'other_shipment_amount' => '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'payment_amount' => '0.00',
            'bank_fee_amount' => '0.00',
            'previous_invoice_amount' => '0.00',
            'invoice_amount' => '0.00',
        ];
    }

    private function prefecture(?string $address): ?string
    {
        if (! is_string($address) || trim($address) === '') {
            return null;
        }

        return preg_match('/^(.{2,3}[都道府県])/u', $address, $matches) === 1
            ? $matches[1]
            : null;
    }

    private function businessType(?string $note): ?string
    {
        if (! is_string($note) || trim($note) === '') {
            return null;
        }

        if (preg_match('/業種区分=([^;]+)/u', $note, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    private function receivableDecreaseAmount(string $amount): string
    {
        if (bccomp($amount, '0.00', 2) === 1) {
            return bcsub('0.00', $amount, 2);
        }

        return $amount;
    }

    private function zeroNormalize(string $value): string
    {
        return bcadd($value, '0', 2);
    }
}
