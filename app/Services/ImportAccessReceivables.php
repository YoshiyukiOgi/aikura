<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportAccessReceivables
{
    private const SOURCE_TABLE = '入金';

    public function import(
        AccessMigrationBatch $batch,
        bool $deltaOnly = false,
        ?string $cutoverDate = null,
        ?string $openingDate = null,
    ): array
    {
        if (! in_array($batch->status, ['inventory_history_imported', 'receivables_imported'], true)) {
            throw new RuntimeException("入金・開始売掛を移行できないバッチ状態です: {$batch->status}");
        }

        $cutoverDate = $cutoverDate === null ? null : CarbonImmutable::parse($cutoverDate)->toDateString();
        $openingDate = $openingDate === null ? null : CarbonImmutable::parse($openingDate)->toDateString();

        return DB::transaction(function () use ($batch, $deltaOnly, $cutoverDate, $openingDate): array {
            $customerIds = DB::table('access_migration_mappings')
                ->where('batch_id', $batch->id)
                ->where('source_table', '取引先マスター')
                ->where('target_table', 'customers')
                ->pluck('target_id', 'source_key');
            $ledgerCount = $this->importLedgerEntries($batch, $customerIds, $deltaOnly, $cutoverDate);
            $paymentCount = $this->importPayments($batch);
            $asOfDate = $deltaOnly ? null : ($openingDate ?? $this->asOfDate($batch));
            $openingResult = $deltaOnly
                ? ['count' => 0, 'non_zero_count' => 0, 'total' => '0.00']
                : ($openingDate === null
                    ? $this->calculateOpeningBalances($batch, $asOfDate)
                    : $this->calculateOpeningBalancesFromStaging($batch, $asOfDate, $customerIds));
            $this->recordMappings($batch, $deltaOnly, $cutoverDate);

            $summary = [
                'ledger_entries' => $ledgerCount,
                'payments' => $paymentCount,
                'opening_balances' => $openingResult['count'],
                'non_zero_opening_balances' => $openingResult['non_zero_count'],
                'opening_balance_total' => $openingResult['total'],
                'as_of_date' => $asOfDate,
                'cutover_date' => $cutoverDate,
                'imported_at' => now()->toIso8601String(),
            ];
            $validationSummary = $batch->validation_summary ?? [];
            $validationSummary['imports']['receivables'] = $summary;
            $batch->update([
                'status' => 'receivables_imported',
                'validation_summary' => $validationSummary,
                'completed_at' => now(),
            ]);

            return $summary;
        });
    }

    private function importLedgerEntries(AccessMigrationBatch $batch, $customerIds, bool $deltaOnly, ?string $cutoverDate): int
    {
        $count = 0;

        $this->sourceQuery($batch, $deltaOnly, $cutoverDate)->chunkById(500, function ($rows) use ($batch, $customerIds, &$count): void {
            $records = [];
            $now = now();
            foreach ($rows as $row) {
                $source = $this->payload($row);
                $sourceCustomerId = (string) ($source['取引先ID'] ?? '');
                $customerId = $customerIds->get($sourceCustomerId);
                if ($customerId === null) {
                    throw new RuntimeException("Access入金{$row->source_key}の得意先を解決できません: {$sourceCustomerId}");
                }

                $amount = $this->money($source['金額'] ?? null);
                $isPreviousBill = (bool) ($source['前月分請求'] ?? false);
                $isTransferFee = (bool) ($source['振込料'] ?? false);
                $records[] = [
                    'access_migration_batch_id' => $batch->id,
                    'customer_id' => (int) $customerId,
                    'legacy_access_payment_id' => (string) $row->source_key,
                    'entry_date' => $this->date($source['年月日'] ?? null, "Access入金{$row->source_key}"),
                    'billing_year' => (int) ($source['請求年'] ?? 0),
                    'billing_month' => (int) ($source['請求月'] ?? 0),
                    'signed_amount' => $amount,
                    'entry_type' => $this->entryType($amount, $isPreviousBill, $isTransferFee),
                    'is_previous_month_bill' => $isPreviousBill,
                    'is_transfer_fee' => $isTransferFee,
                    'description' => $this->blankToNull($source['摘要'] ?? null),
                    'source_payload' => json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'source_payload_sha256' => $row->payload_sha256,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $count++;
            }

            DB::table('access_receivable_ledger_entries')->upsert(
                $records,
                ['access_migration_batch_id', 'legacy_access_payment_id'],
                array_diff(array_keys($records[0]), ['created_at', 'access_migration_batch_id', 'legacy_access_payment_id']),
            );
        });

        return $count;
    }

    private function importPayments(AccessMigrationBatch $batch): int
    {
        $count = 0;

        DB::table('access_receivable_ledger_entries')
            ->where('access_migration_batch_id', $batch->id)
            ->where('signed_amount', '<', 0)
            ->where('is_previous_month_bill', false)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$count): void {
                $records = [];
                $now = now();
                foreach ($rows as $row) {
                    $description = $this->blankToNull($row->description);
                    $records[] = [
                        'access_receivable_ledger_entry_id' => $row->id,
                        'legacy_access_payment_id' => $row->legacy_access_payment_id,
                        'is_legacy_history' => true,
                        'customer_id' => $row->customer_id,
                        'status' => 'legacy_imported',
                        'payment_date' => $row->entry_date,
                        'payment_method' => $this->paymentMethod($description, (bool) $row->is_transfer_fee),
                        'amount' => bcsub('0.00', (string) $row->signed_amount, 2),
                        'unapplied_amount' => 0,
                        'reference_number' => 'ITARO-PAY-'.$row->legacy_access_payment_id,
                        'note' => implode('; ', array_filter([
                            'Access入金履歴',
                            "請求年月={$row->billing_year}-".str_pad((string) $row->billing_month, 2, '0', STR_PAD_LEFT),
                            (bool) $row->is_transfer_fee ? '振込料' : null,
                            $description,
                        ])),
                        'cancelled_at' => null,
                        'cancelled_reason' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $count++;
                }

                DB::table('payments')->upsert(
                    $records,
                    ['legacy_access_payment_id'],
                    array_diff(array_keys($records[0]), ['created_at', 'legacy_access_payment_id']),
                );
            });

        return $count;
    }

    private function calculateOpeningBalances(AccessMigrationBatch $batch, string $asOfDate): array
    {
        $eligibleCustomerIds = DB::table('customers')
            ->join('settlement_receivable_categories', 'settlement_receivable_categories.id', '=', 'customers.settlement_receivable_category_id')
            ->where('settlement_receivable_categories.receivable_method', 'accounts_receivable')
            ->pluck('customers.id');
        $sales = DB::table('shipment_headers')
            ->whereNotNull('legacy_access_document_number')
            ->whereIn('customer_id', $eligibleCustomerIds)
            ->whereDate('document_date', '<=', $asOfDate)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as source_count, COALESCE(SUM(legacy_access_total_amount), 0) as amount')
            ->get()
            ->keyBy('customer_id');
        $ledger = DB::table('access_receivable_ledger_entries')
            ->where('access_migration_batch_id', $batch->id)
            ->whereIn('customer_id', $eligibleCustomerIds)
            ->whereDate('entry_date', '<=', $asOfDate)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as source_count, COALESCE(SUM(signed_amount), 0) as amount')
            ->get()
            ->keyBy('customer_id');
        $containers = $this->containerBalancesByCustomer($batch, $asOfDate);
        $customerIds = $sales->keys()->merge($ledger->keys())->merge($containers->keys())->unique()->values();
        $records = [];
        $total = '0.00';
        $nonZeroCount = 0;
        $now = now();

        foreach ($customerIds as $customerId) {
            $salesRow = $sales->get($customerId);
            $ledgerRow = $ledger->get($customerId);
            $containerRow = $containers->get($customerId);
            $salesAmount = bcadd((string) ($salesRow->amount ?? 0), '0', 2);
            $ledgerAmount = bcadd((string) ($ledgerRow->amount ?? 0), '0', 2);
            $containerAmount = bcadd((string) ($containerRow->amount ?? 0), '0', 2);
            $balance = bcsub(bcadd($salesAmount, $ledgerAmount, 2), $containerAmount, 2);
            $total = bcadd($total, $balance, 2);
            $nonZeroCount += bccomp($balance, '0.00', 2) === 0 ? 0 : 1;
            $records[] = [
                'access_migration_batch_id' => $batch->id,
                'customer_id' => (int) $customerId,
                'status' => 'calculated',
                'as_of_date' => $asOfDate,
                'source_sales_count' => (int) ($salesRow->source_count ?? 0),
                'source_ledger_entry_count' => (int) ($ledgerRow->source_count ?? 0),
                'source_container_entry_count' => (int) ($containerRow->source_count ?? 0),
                'source_sales_amount' => $salesAmount,
                'source_ledger_amount' => $ledgerAmount,
                'source_container_amount' => $containerAmount,
                'calculated_balance_amount' => $balance,
                'statement_balance_amount' => null,
                'adjustment_amount' => 0,
                'opening_balance_amount' => $balance,
                'calculated_at' => $now,
                'reconciled_at' => null,
                'reconciliation_note' => '紙帳票との照合前。Access出荷合計＋符号付き入金台帳－空容器取引で算出。',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($records !== []) {
            DB::table('opening_receivable_balances')->upsert(
                $records,
                ['access_migration_batch_id', 'customer_id'],
                array_diff(array_keys($records[0]), ['created_at', 'access_migration_batch_id', 'customer_id']),
            );
        }
        DB::table('opening_receivable_balances')
            ->where('access_migration_batch_id', $batch->id)
            ->when($customerIds->isNotEmpty(), fn ($query) => $query->whereNotIn('customer_id', $customerIds))
            ->delete();

        return ['count' => count($records), 'non_zero_count' => $nonZeroCount, 'total' => $total];
    }

    private function calculateOpeningBalancesFromStaging(
        AccessMigrationBatch $batch,
        string $asOfDate,
        $customerIds,
    ): array {
        $eligibleCustomerIds = DB::table('customers')
            ->join('settlement_receivable_categories', 'settlement_receivable_categories.id', '=', 'customers.settlement_receivable_category_id')
            ->where('settlement_receivable_categories.receivable_method', 'accounts_receivable')
            ->pluck('customers.id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        $salesBySourceCustomer = DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', '出荷伝票・取引先')
            ->whereRaw("CAST(payload->>'年月日' AS date) <= ?", [$asOfDate])
            ->selectRaw("payload->>'取引先ID' AS source_customer_id, COUNT(*) AS source_count, COALESCE(SUM(COALESCE(NULLIF(payload->>'合計', ''), '0')::numeric), 0) AS amount")
            ->groupByRaw("payload->>'取引先ID'")
            ->get()
            ->keyBy('source_customer_id');

        $ledgerBySourceCustomer = DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', self::SOURCE_TABLE)
            ->whereRaw("CAST(payload->>'年月日' AS date) <= ?", [$asOfDate])
            ->selectRaw("payload->>'取引先ID' AS source_customer_id, COUNT(*) AS source_count, COALESCE(SUM(COALESCE(NULLIF(payload->>'金額', ''), '0')::numeric), 0) AS amount")
            ->groupByRaw("payload->>'取引先ID'")
            ->get()
            ->keyBy('source_customer_id');
        $containersBySourceCustomer = DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', '空容器伝票-取引先')
            ->whereRaw("CAST(payload->>'年月日' AS date) <= ?", [$asOfDate])
            ->selectRaw("payload->>'取引先ID' AS source_customer_id, COUNT(*) AS source_count, COALESCE(SUM(COALESCE(NULLIF(payload->>'合計', ''), '0')::numeric), 0) AS amount")
            ->groupByRaw("payload->>'取引先ID'")
            ->get()
            ->keyBy('source_customer_id');

        $records = [];
        $total = '0.00';
        $nonZeroCount = 0;
        $now = now();

        foreach ($customerIds as $sourceCustomerId => $targetCustomerId) {
            $targetCustomerId = (int) $targetCustomerId;
            if (! $eligibleCustomerIds->has($targetCustomerId)) {
                continue;
            }

            $sales = $salesBySourceCustomer->get((string) $sourceCustomerId);
            $ledger = $ledgerBySourceCustomer->get((string) $sourceCustomerId);
            $containers = $containersBySourceCustomer->get((string) $sourceCustomerId);
            if ($sales === null && $ledger === null && $containers === null) {
                continue;
            }

            $salesAmount = bcadd((string) ($sales->amount ?? 0), '0', 2);
            $ledgerAmount = bcadd((string) ($ledger->amount ?? 0), '0', 2);
            $containerAmount = bcadd((string) ($containers->amount ?? 0), '0', 2);
            $balance = bcsub(bcadd($salesAmount, $ledgerAmount, 2), $containerAmount, 2);
            $total = bcadd($total, $balance, 2);
            $nonZeroCount += bccomp($balance, '0.00', 2) === 0 ? 0 : 1;
            $records[] = [
                'access_migration_batch_id' => $batch->id,
                'customer_id' => $targetCustomerId,
                'status' => 'calculated',
                'as_of_date' => $asOfDate,
                'source_sales_count' => (int) ($sales->source_count ?? 0),
                'source_ledger_entry_count' => (int) ($ledger->source_count ?? 0),
                'source_container_entry_count' => (int) ($containers->source_count ?? 0),
                'source_sales_amount' => $salesAmount,
                'source_ledger_amount' => $ledgerAmount,
                'source_container_amount' => $containerAmount,
                'calculated_balance_amount' => $balance,
                'statement_balance_amount' => null,
                'adjustment_amount' => 0,
                'opening_balance_amount' => $balance,
                'calculated_at' => $now,
                'reconciled_at' => null,
                'reconciliation_note' => '移行開始日前のItaro出荷・符号付き入金台帳・空容器取引から算出。',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($records !== []) {
            DB::table('opening_receivable_balances')->upsert(
                $records,
                ['access_migration_batch_id', 'customer_id'],
                array_diff(array_keys($records[0]), ['created_at', 'access_migration_batch_id', 'customer_id']),
            );
        }

        return ['count' => count($records), 'non_zero_count' => $nonZeroCount, 'total' => $total];
    }

    private function containerBalancesByCustomer(AccessMigrationBatch $batch, string $asOfDate)
    {
        return DB::table('access_migration_staging_rows as s')
            ->join('access_migration_mappings as m', function ($join) use ($batch): void {
                $join->where('m.batch_id', $batch->id)
                    ->where('m.source_table', '取引先マスター')
                    ->where('m.target_table', 'customers')
                    ->whereColumn('m.source_key', DB::raw("s.payload->>'取引先ID'"));
            })
            ->where('s.batch_id', $batch->id)
            ->where('s.source_table', '空容器伝票-取引先')
            ->whereRaw("CAST(s.payload->>'年月日' AS date) <= ?", [$asOfDate])
            ->groupBy('m.target_id')
            ->selectRaw('CAST(m.target_id AS bigint) AS customer_id, COUNT(*) AS source_count')
            ->selectRaw("COALESCE(SUM(COALESCE(NULLIF(s.payload->>'合計', ''), '0')::numeric), 0) AS amount")
            ->get()
            ->keyBy('customer_id');
    }

    private function recordMappings(AccessMigrationBatch $batch, bool $deltaOnly, ?string $cutoverDate): void
    {
        $this->sourceQuery($batch, $deltaOnly, $cutoverDate)->chunkById(500, function ($rows) use ($batch): void {
            $sourceKeys = $rows->pluck('source_key')->map(fn ($value) => (string) $value);
            $targets = DB::table('access_receivable_ledger_entries')
                ->where('access_migration_batch_id', $batch->id)
                ->whereIn('legacy_access_payment_id', $sourceKeys)
                ->pluck('id', 'legacy_access_payment_id');
            $now = now();
            $records = $rows->map(function ($row) use ($batch, $targets, $now): array {
                $targetId = $targets->get((string) $row->source_key);
                if ($targetId === null) {
                    throw new RuntimeException("Access入金対応を作成できません: {$row->source_key}");
                }

                return [
                    'batch_id' => $batch->id,
                    'source_table' => self::SOURCE_TABLE,
                    'source_key' => (string) $row->source_key,
                    'target_table' => 'access_receivable_ledger_entries',
                    'target_id' => (string) $targetId,
                    'action' => 'imported',
                    'source_payload_sha256' => $row->payload_sha256,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();
            DB::table('access_migration_mappings')->upsert(
                $records,
                ['batch_id', 'source_table', 'source_key'],
                ['target_table', 'target_id', 'action', 'source_payload_sha256', 'updated_at'],
            );
        });
        $this->sourceQuery($batch, $deltaOnly, $cutoverDate)
            ->update(['status' => 'imported', 'target_table' => 'access_receivable_ledger_entries', 'updated_at' => now()]);
    }

    private function sourceQuery(AccessMigrationBatch $batch, bool $deltaOnly = false, ?string $cutoverDate = null)
    {
        return DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', self::SOURCE_TABLE)
            ->when($cutoverDate !== null, fn ($query) => $query
                ->whereRaw("CAST(payload->>'年月日' AS date) >= ?", [$cutoverDate]))
            ->when($deltaOnly, fn ($query) => $query->whereExists(function ($delta) use ($batch): void {
                $delta->selectRaw('1')
                    ->from('access_migration_deltas')
                    ->whereColumn('access_migration_deltas.current_staging_row_id', 'access_migration_staging_rows.id')
                    ->where('access_migration_deltas.batch_id', $batch->id)
                    ->where('access_migration_deltas.change_type', 'new');
            }))
            ->orderBy('id');
    }

    private function payload(object $row): array
    {
        return is_array($row->payload) ? $row->payload : json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
    }

    private function asOfDate(AccessMigrationBatch $batch): string
    {
        $date = $batch->source_last_modified_at ?? $batch->started_at;

        return CarbonImmutable::parse($date)->setTimezone(config('app.timezone'))->toDateString();
    }

    private function entryType(string $amount, bool $isPreviousBill, bool $isTransferFee): string
    {
        return match (true) {
            $isPreviousBill => 'previous_month_bill',
            $isTransferFee => 'transfer_fee',
            bccomp($amount, '0.00', 2) < 0 => 'receipt',
            bccomp($amount, '0.00', 2) > 0 => 'adjustment_increase',
            default => 'zero',
        };
    }

    private function paymentMethod(?string $description, bool $isTransferFee): string
    {
        if ($isTransferFee) {
            return 'bank_fee';
        }

        return match (true) {
            str_contains((string) $description, 'PayPay') => 'paypay_bank',
            str_contains((string) $description, '郵貯'), str_contains((string) $description, '郵便') => 'postal_transfer',
            str_contains((string) $description, '銀行'), str_contains((string) $description, '振込') => 'bank_transfer',
            default => 'legacy_unknown',
        };
    }

    private function date(mixed $value, string $label): string
    {
        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw new RuntimeException("{$label}の日付を解釈できません: ".(string) $value);
        }
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
