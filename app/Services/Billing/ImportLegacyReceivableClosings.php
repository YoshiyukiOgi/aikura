<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\ReceivableMonthlyBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportLegacyReceivableClosings
{
    private const REASON = 'Access historical receivable close migration';

    public function importStatement(
        string $path,
        int $year,
        int $month,
        ?string $expectedTotal = null,
        bool $apply = false,
    ): array {
        $rows = $this->readStatement($path);
        $customers = Customer::query()
            ->whereIn('customer_code', array_column($rows, 'customer_code'))
            ->get()
            ->keyBy('customer_code');

        $missing = collect($rows)
            ->pluck('customer_code')
            ->reject(fn (string $code): bool => $customers->has($code))
            ->values();
        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Unknown customer codes: '.$missing->implode(', '));
        }

        $total = '0.00';
        foreach ($rows as $row) {
            $total = bcadd($total, $row['balance'], 2);
        }
        if ($expectedTotal !== null && bccomp($total, $expectedTotal, 2) !== 0) {
            throw new RuntimeException("Statement total mismatch: expected {$expectedTotal}, got {$total}");
        }

        if ($apply) {
            $period = CarbonImmutable::create($year, $month, 1);
            DB::transaction(function () use ($rows, $customers, $year, $month, $period, $path): void {
                $this->assertReplaceable($year, $month);

                foreach ($rows as $row) {
                    $customer = $customers->get($row['customer_code']);
                    $this->saveClosedBalance(
                        year: $year,
                        month: $month,
                        customer: $customer,
                        scheduled: $row['balance'],
                        received: '0.00',
                        outstanding: $row['balance'],
                        period: $period,
                        note: 'paper_statement='.basename($path).'; printed_name='.$row['printed_customer_name'],
                    );
                }
            });
        }

        return [
            'year' => $year,
            'month' => $month,
            'row_count' => count($rows),
            'total' => $total,
            'applied' => $apply,
        ];
    }

    public function projectMonthFromAccess(
        int $accessMigrationBatchId,
        int $year,
        int $month,
        bool $apply = false,
    ): array {
        $period = CarbonImmutable::create($year, $month, 1);
        $previous = $period->subMonth();
        $previousBalances = ReceivableMonthlyBalance::query()
            ->where('year', $previous->year)
            ->where('month', $previous->month)
            ->where('status', 'closed')
            ->get()
            ->keyBy('customer_id');
        if ($previousBalances->isEmpty()) {
            throw new RuntimeException("No closed receivable balances for {$previous->format('Y-m')}");
        }

        $eligibleCustomerIds = DB::table('customers')
            ->join('settlement_receivable_categories', 'settlement_receivable_categories.id', '=', 'customers.settlement_receivable_category_id')
            ->where('settlement_receivable_categories.receivable_method', 'accounts_receivable')
            ->pluck('customers.id');
        $sales = DB::table('shipment_headers')
            ->whereNotNull('legacy_access_document_number')
            ->whereIn('customer_id', $eligibleCustomerIds)
            ->whereBetween('document_date', [$period->toDateString(), $period->endOfMonth()->toDateString()])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as source_count, COALESCE(SUM(legacy_access_total_amount), 0) as amount')
            ->get()
            ->keyBy('customer_id');
        $ledger = DB::table('access_receivable_ledger_entries')
            ->where('access_migration_batch_id', $accessMigrationBatchId)
            ->whereIn('customer_id', $eligibleCustomerIds)
            ->whereBetween('entry_date', [$period->toDateString(), $period->endOfMonth()->toDateString()])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as source_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN signed_amount > 0 THEN signed_amount ELSE 0 END), 0) as increases')
            ->selectRaw('COALESCE(SUM(CASE WHEN signed_amount < 0 THEN 0 - signed_amount ELSE 0 END), 0) as receipts')
            ->get()
            ->keyBy('customer_id');

        $customerIds = $previousBalances->keys()
            ->merge($sales->keys())
            ->merge($ledger->keys())
            ->unique()
            ->values();
        $customers = Customer::query()->whereIn('id', $customerIds)->get()->keyBy('id');
        $rows = [];
        $total = '0.00';

        foreach ($customerIds as $customerId) {
            $opening = bcadd((string) ($previousBalances->get($customerId)?->outstanding_amount ?? 0), '0', 2);
            $salesAmount = bcadd((string) ($sales->get($customerId)?->amount ?? 0), '0', 2);
            $increases = bcadd((string) ($ledger->get($customerId)?->increases ?? 0), '0', 2);
            $receipts = bcadd((string) ($ledger->get($customerId)?->receipts ?? 0), '0', 2);
            $scheduled = bcadd(bcadd($opening, $salesAmount, 2), $increases, 2);
            $outstanding = bcsub($scheduled, $receipts, 2);
            $total = bcadd($total, $outstanding, 2);
            $rows[] = compact('customerId', 'opening', 'salesAmount', 'increases', 'receipts', 'scheduled', 'outstanding');
        }

        if ($apply) {
            DB::transaction(function () use ($rows, $customers, $year, $month, $period): void {
                $this->assertReplaceable($year, $month);

                foreach ($rows as $row) {
                    $this->saveClosedBalance(
                        year: $year,
                        month: $month,
                        customer: $customers->get($row['customerId']),
                        scheduled: $row['scheduled'],
                        received: $row['receipts'],
                        outstanding: $row['outstanding'],
                        period: $period,
                        note: implode('; ', [
                            'opening='.$row['opening'],
                            'access_shipment_total='.$row['salesAmount'],
                            'access_ledger_increases='.$row['increases'],
                            'access_receipts_and_fees='.$row['receipts'],
                        ]),
                    );
                }
            });
        }

        return [
            'year' => $year,
            'month' => $month,
            'row_count' => count($rows),
            'shipment_count' => (int) $sales->sum('source_count'),
            'shipment_total' => bcadd((string) $sales->sum('amount'), '0', 2),
            'ledger_count' => (int) $ledger->sum('source_count'),
            'receipts_and_fees_total' => bcadd((string) $ledger->sum('receipts'), '0', 2),
            'total' => $total,
            'negative_count' => collect($rows)->where('outstanding', '<', 0)->count(),
            'negative_rows' => collect($rows)
                ->where('outstanding', '<', 0)
                ->map(fn (array $row): array => [
                    'customer_code' => $customers->get($row['customerId'])->customer_code,
                    'customer_name' => $customers->get($row['customerId'])->short_name
                        ?: $customers->get($row['customerId'])->name,
                    'opening' => $row['opening'],
                    'sales' => $row['salesAmount'],
                    'receipts' => $row['receipts'],
                    'outstanding' => $row['outstanding'],
                ])
                ->values()
                ->all(),
            'applied' => $apply,
        ];
    }

    private function assertReplaceable(int $year, int $month): void
    {
        $foreignRows = ReceivableMonthlyBalance::query()
            ->where('year', $year)
            ->where('month', $month)
            ->where(function ($query): void {
                $query->whereNull('reason')->orWhere('reason', '!=', self::REASON);
            })
            ->exists();
        if ($foreignRows) {
            throw new RuntimeException("Receivable balances for {$year}-{$month} were not created by the Access migration");
        }

        ReceivableMonthlyBalance::query()
            ->where('year', $year)
            ->where('month', $month)
            ->delete();
    }

    private function saveClosedBalance(
        int $year,
        int $month,
        Customer $customer,
        string $scheduled,
        string $received,
        string $outstanding,
        CarbonImmutable $period,
        string $note,
    ): void {
        ReceivableMonthlyBalance::query()->create([
            'status' => 'closed',
            'year' => $year,
            'month' => $month,
            'period_start' => $period->toDateString(),
            'period_end' => $period->endOfMonth()->toDateString(),
            'customer_id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->name,
            'scheduled_amount' => $scheduled,
            'received_amount' => $received,
            'outstanding_amount' => $outstanding,
            'open_schedule_count' => bccomp($outstanding, '0.00', 2) > 0 ? 1 : 0,
            'partial_schedule_count' => 0,
            'closed_schedule_count' => bccomp($outstanding, '0.00', 2) === 0 ? 1 : 0,
            'calculated_at' => now(),
            'confirmed_at' => now(),
            'closed_at' => now(),
            'reason' => self::REASON,
            'note' => $note,
        ]);
    }

    private function readStatement(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open statement CSV: {$path}");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new RuntimeException('Statement CSV is empty');
            }
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            $indexes = array_flip($header);
            foreach (['printed_customer_name', 'may_end_balance', 'customer_code'] as $required) {
                if (! isset($indexes[$required])) {
                    throw new RuntimeException("Missing CSV column: {$required}");
                }
            }

            $rows = [];
            $seen = [];
            while (($values = fgetcsv($handle)) !== false) {
                if ($values === [null] || $values === []) {
                    continue;
                }
                $code = trim((string) ($values[$indexes['customer_code']] ?? ''));
                if ($code === '' || isset($seen[$code])) {
                    throw new RuntimeException("Blank or duplicate customer code: {$code}");
                }
                $seen[$code] = true;
                $rows[] = [
                    'customer_code' => $code,
                    'printed_customer_name' => trim((string) ($values[$indexes['printed_customer_name']] ?? '')),
                    'balance' => number_format((float) ($values[$indexes['may_end_balance']] ?? 0), 2, '.', ''),
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
