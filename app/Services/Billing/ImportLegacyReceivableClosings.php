<?php

namespace App\Services\Billing;

use App\Models\AccessMigrationBatch;
use App\Models\Customer;
use App\Models\OpeningReceivableBalance;
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
            throw new RuntimeException('未登録の取引先コードがあります: '.$missing->implode(', '));
        }

        $total = '0.00';
        foreach ($rows as $row) {
            $total = bcadd($total, $row['balance'], 2);
        }
        if ($expectedTotal !== null && bccomp($total, $expectedTotal, 2) !== 0) {
            throw new RuntimeException("残高表合計が一致しません。期待値: {$expectedTotal} / 実値: {$total}");
        }

        if ($apply) {
            $period = CarbonImmutable::create($year, $month, 1);
            DB::transaction(function () use ($rows, $customers, $year, $month, $period, $path): void {
                $this->assertReplaceable($year, $month);

                foreach ($rows as $row) {
                    $customer = $customers->get($row['customer_code']);
                    $this->saveBalance(
                        year: $year,
                        month: $month,
                        customer: $customer,
                        scheduled: $row['balance'],
                        received: '0.00',
                        outstanding: $row['balance'],
                        period: $period,
                        status: 'closed',
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
        string $status = 'closed',
    ): array {
        if (! in_array($status, ['draft', 'closed'], true)) {
            throw new RuntimeException("未対応の売掛残高状態です: {$status}");
        }

        $period = CarbonImmutable::create($year, $month, 1);
        $previous = $period->subMonth();
        $previousBalances = ReceivableMonthlyBalance::query()
            ->where('year', $previous->year)
            ->where('month', $previous->month)
            ->where('status', 'closed')
            ->get()
            ->keyBy('customer_id');
        if ($previousBalances->isEmpty()) {
            throw new RuntimeException($previous->format('Y年n月').' の締め済み売掛残高がありません。');
        }

        $sales = DB::table('shipment_headers')
            ->whereNotNull('legacy_access_document_number')
            ->whereBetween('document_date', [$period->toDateString(), $period->endOfMonth()->toDateString()])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as source_count, COALESCE(SUM(legacy_access_total_amount), 0) as amount')
            ->get()
            ->keyBy('customer_id');
        $ledger = DB::table('access_receivable_ledger_entries')
            ->where('access_migration_batch_id', $accessMigrationBatchId)
            ->whereBetween('entry_date', [$period->toDateString(), $period->endOfMonth()->toDateString()])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as source_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN signed_amount > 0 THEN signed_amount ELSE 0 END), 0) as increases')
            ->selectRaw('COALESCE(SUM(CASE WHEN signed_amount < 0 THEN 0 - signed_amount ELSE 0 END), 0) as receipts')
            ->get()
            ->keyBy('customer_id');
        $containers = DB::table('access_migration_staging_rows as s')
            ->join('access_migration_mappings as m', function ($join) use ($accessMigrationBatchId): void {
                $join->where('m.batch_id', $accessMigrationBatchId)
                    ->where('m.source_table', '取引先マスター')
                    ->where('m.target_table', 'customers')
                    ->whereColumn('m.source_key', DB::raw("s.payload->>'取引先ID'"));
            })
            ->where('s.batch_id', $accessMigrationBatchId)
            ->where('s.source_table', '空容器伝票-取引先')
            ->whereRaw("CAST(s.payload->>'年月日' AS date) BETWEEN ? AND ?", [
                $period->toDateString(),
                $period->endOfMonth()->toDateString(),
            ])
            ->groupBy('m.target_id')
            ->selectRaw('CAST(m.target_id AS bigint) AS customer_id, COUNT(*) AS source_count')
            ->selectRaw("COALESCE(SUM(COALESCE(NULLIF(s.payload->>'合計', ''), '0')::numeric), 0) AS amount")
            ->get()
            ->keyBy('customer_id');

        $customerIds = $previousBalances->keys()
            ->merge($sales->keys())
            ->merge($ledger->keys())
            ->merge($containers->keys())
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
            $containerAmount = bcadd((string) ($containers->get($customerId)?->amount ?? 0), '0', 2);
            $scheduled = bcsub(bcadd(bcadd($opening, $salesAmount, 2), $increases, 2), $containerAmount, 2);
            $outstanding = bcsub($scheduled, $receipts, 2);
            $total = bcadd($total, $outstanding, 2);
            $rows[] = compact('customerId', 'opening', 'salesAmount', 'increases', 'receipts', 'containerAmount', 'scheduled', 'outstanding');
        }

        if ($apply) {
            DB::transaction(function () use ($rows, $customers, $year, $month, $period, $status): void {
                $this->assertReplaceable($year, $month);

                foreach ($rows as $row) {
                    $this->saveBalance(
                        year: $year,
                        month: $month,
                        customer: $customers->get($row['customerId']),
                        scheduled: $row['scheduled'],
                        received: $row['receipts'],
                        outstanding: $row['outstanding'],
                        period: $period,
                        status: $status,
                        note: implode('; ', [
                            'opening='.$row['opening'],
                            'access_shipment_total='.$row['salesAmount'],
                            'access_ledger_increases='.$row['increases'],
                            'access_receipts_and_fees='.$row['receipts'],
                            'access_empty_container_total='.$row['containerAmount'],
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
            'empty_container_total' => bcadd((string) $containers->sum('amount'), '0', 2),
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
            'status' => $status,
            'applied' => $apply,
        ];
    }

    public function carryForwardOpening(
        AccessMigrationBatch $batch,
        int $year,
        int $month,
        string $asOfDate,
        ?string $expectedTotal = null,
    ): array {
        $balances = ReceivableMonthlyBalance::query()
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', 'closed')
            ->orderBy('customer_id')
            ->get();
        if ($balances->isEmpty()) {
            throw new RuntimeException(sprintf('%04d年%02d月の締め済み売掛残高がありません。', $year, $month));
        }

        $total = $this->sum($balances, 'outstanding_amount');
        if ($expectedTotal !== null && bccomp($total, $expectedTotal, 2) !== 0) {
            throw new RuntimeException("繰越合計が一致しません。期待値: {$expectedTotal} / 実値: {$total}");
        }

        DB::transaction(function () use ($balances, $batch, $asOfDate, $year, $month): void {
            foreach ($balances as $balance) {
                OpeningReceivableBalance::query()->updateOrCreate(
                    [
                        'access_migration_batch_id' => $batch->id,
                        'customer_id' => $balance->customer_id,
                    ],
                    [
                        'status' => 'reconciled',
                        'as_of_date' => $asOfDate,
                        'calculated_balance_amount' => $balance->outstanding_amount,
                        'statement_balance_amount' => $balance->outstanding_amount,
                        'adjustment_amount' => 0,
                        'opening_balance_amount' => $balance->outstanding_amount,
                        'calculated_at' => now(),
                        'reconciled_at' => now(),
                        'reconciliation_note' => sprintf(
                            '%04d-%02d紙請求書の確定残高を%s開始残高へ繰越。空容器履歴と帳票丸めを照合済み。',
                            $year,
                            $month,
                            $asOfDate,
                        ),
                    ],
                );
            }

            OpeningReceivableBalance::query()
                ->where('access_migration_batch_id', $batch->id)
                ->whereNotIn('customer_id', $balances->pluck('customer_id'))
                ->delete();
        });

        return [
            'as_of_date' => CarbonImmutable::parse($asOfDate)->toDateString(),
            'row_count' => $balances->count(),
            'total' => $total,
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
            throw new RuntimeException("{$year}年{$month}月の売掛残高はAccess移行で作成されたものではありません。");
        }

        ReceivableMonthlyBalance::query()
            ->where('year', $year)
            ->where('month', $month)
            ->delete();
    }

    private function saveBalance(
        int $year,
        int $month,
        Customer $customer,
        string $scheduled,
        string $received,
        string $outstanding,
        CarbonImmutable $period,
        string $note,
        string $status = 'closed',
    ): void {
        ReceivableMonthlyBalance::query()->create([
            'status' => $status,
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
            'confirmed_at' => $status === 'closed' ? now() : null,
            'closed_at' => $status === 'closed' ? now() : null,
            'reason' => self::REASON,
            'note' => $note,
        ]);
    }

    private function sum($rows, string $column): string
    {
        return $rows->reduce(
            fn (string $carry, $row): string => bcadd($carry, (string) $row->{$column}, 2),
            '0.00',
        );
    }

    private function readStatement(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("残高表CSVを開けません: {$path}");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new RuntimeException('残高表CSVが空です。');
            }
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            $indexes = array_flip($header);
            foreach (['printed_customer_name', 'may_end_balance', 'customer_code'] as $required) {
                if (! isset($indexes[$required])) {
                    throw new RuntimeException("CSV列が不足しています: {$required}");
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
                    throw new RuntimeException("取引先コードが空、または重複しています: {$code}");
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
