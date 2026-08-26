<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitializeAccessCutover
{
    private const RESET_TABLES = [
        'approval_request_actions',
        'approval_requests',
        'report_exports',
        'consumption_tax_monthly_filing_lines',
        'consumption_tax_monthly_filings',
        'liquor_tax_monthly_filing_sources',
        'liquor_tax_monthly_filing_lines',
        'liquor_tax_monthly_filing_adjustments',
        'liquor_tax_monthly_filings',
        'payment_allocations',
        'payment_schedules',
        'payments',
        'receivable_monthly_balances',
        'opening_receivable_balances',
        'access_receivable_ledger_entries',
        'sales_return_line_lots',
        'sales_return_lines',
        'sales_return_headers',
        'invoice_lines',
        'invoice_headers',
        'inventory_count_lines',
        'inventory_count_headers',
        'stock_lot_monthly_balances',
        'stock_movements',
        'non_sales_stock_operation_revisions',
        'non_sales_stock_operation_lines',
        'non_sales_stock_operation_headers',
        'shipment_liquor_tax_evidences',
        'shipment_lot_allocations',
        'shipment_lines',
        'shipment_headers',
        'shipment_pick_lines',
        'shipment_picks',
        'shipment_instruction_lines',
        'shipment_instructions',
        'sales_order_lines',
        'sales_orders',
        'production_lots',
    ];

    private const RESET_MAPPING_TARGETS = [
        'access_receivable_ledger_entries',
        'invoice_headers',
        'invoice_lines',
        'non_sales_stock_operation_headers',
        'non_sales_stock_operation_lines',
        'payments',
        'production_lots',
        'shipment_headers',
        'shipment_lines',
        'stock_movements',
    ];

    public function __construct(
        private readonly ImportAccessMasters $masters,
        private readonly ImportAccessPrices $prices,
        private readonly ImportAccessShipments $shipments,
        private readonly ImportAccessInventoryHistory $inventoryHistory,
        private readonly ImportAccessReceivables $receivables,
        private readonly ImportAccessOpeningStock $openingStock,
    ) {}

    /** @return array<string, mixed> */
    public function preview(
        AccessMigrationBatch $batch,
        string $cutoverDate,
        string $openingDate,
        string $stockAsOfDate,
    ): array {
        $this->guard($batch, $cutoverDate, $openingDate, $stockAsOfDate);

        return [
            'batch_id' => $batch->id,
            'source_sha256' => $batch->source_sha256,
            'cutover_date' => CarbonImmutable::parse($cutoverDate)->toDateString(),
            'opening_date' => CarbonImmutable::parse($openingDate)->toDateString(),
            'stock_as_of_date' => CarbonImmutable::parse($stockAsOfDate)->toDateString(),
            'delete_counts' => collect(self::RESET_TABLES)
                ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
                ->all(),
            'committed' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function initialize(
        AccessMigrationBatch $batch,
        string $cutoverDate,
        string $openingDate,
        string $stockAsOfDate,
    ): array {
        $plan = $this->preview($batch, $cutoverDate, $openingDate, $stockAsOfDate);

        return DB::transaction(function () use ($batch, $plan): array {
            $tableSql = collect(self::RESET_TABLES)
                ->map(fn (string $table): string => DB::getPdo()->quote($table))
                ->map(fn (string $table): string => str_replace("'", '"', $table))
                ->implode(', ');
            DB::statement("TRUNCATE TABLE {$tableSql} RESTART IDENTITY CASCADE");

            DB::table('access_migration_mappings')
                ->whereIn('target_table', self::RESET_MAPPING_TARGETS)
                ->delete();
            DB::table('access_migration_staging_rows')
                ->where('batch_id', $batch->id)
                ->update([
                    'status' => 'staged',
                    'target_table' => null,
                    'target_id' => null,
                    'updated_at' => now(),
                ]);

            $warningStatus = $batch->warning_count > 0 ? 'ready_with_warnings' : 'ready';
            $batch->update([
                'status' => $warningStatus,
                'completed_at' => null,
                'reconciled_at' => null,
            ]);

            $imports = [];
            $imports['masters'] = $this->masters->import($batch->fresh());
            $imports['prices'] = $this->prices->import($batch->fresh());
            $imports['shipments'] = $this->shipments->import(
                $batch->fresh(),
                cutoverDate: $plan['cutover_date'],
            );
            $imports['inventory_history'] = $this->inventoryHistory->import(
                $batch->fresh(),
                cutoverDate: $plan['cutover_date'],
            );
            $imports['receivables'] = $this->receivables->import(
                $batch->fresh(),
                cutoverDate: $plan['cutover_date'],
                openingDate: $plan['opening_date'],
            );

            $batch->refresh()->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $imports['opening_stock'] = $this->openingStock->import(
                $batch->fresh(),
                $plan['stock_as_of_date'],
            );

            $checks = $this->checks($batch->fresh(), $imports);
            if (collect($checks)->contains(fn (array $check): bool => ! $check['passed'])) {
                throw new RuntimeException('初期移行後の件数または重複検査が不一致です。全処理をロールバックしました。');
            }

            $validationSummary = $batch->fresh()->validation_summary ?? [];
            $validationSummary['cutover_initialization'] = [
                'cutover_date' => $plan['cutover_date'],
                'opening_date' => $plan['opening_date'],
                'stock_as_of_date' => $plan['stock_as_of_date'],
                'checks' => $checks,
                'completed_at' => now()->toIso8601String(),
            ];
            $batch->refresh()->update(['validation_summary' => $validationSummary]);

            return array_replace($plan, [
                'committed' => true,
                'imports' => $imports,
                'checks' => $checks,
            ]);
        }, 3);
    }

    private function guard(
        AccessMigrationBatch $batch,
        string $cutoverDate,
        string $openingDate,
        string $stockAsOfDate,
    ): void {
        if (! in_array($batch->status, [
            'ready',
            'ready_with_warnings',
            'masters_imported',
            'shipments_imported',
            'inventory_history_imported',
            'receivables_imported',
            'completed',
        ], true)) {
            throw new RuntimeException("初期移行に使用できないバッチ状態です: {$batch->status}");
        }

        $cutover = CarbonImmutable::parse($cutoverDate)->startOfDay();
        $opening = CarbonImmutable::parse($openingDate)->startOfDay();
        $stockAsOf = CarbonImmutable::parse($stockAsOfDate)->startOfDay();
        if (! $opening->isSameDay($cutover->subDay())) {
            throw new RuntimeException('開始売掛日は取引開始日の前日を指定してください。');
        }
        if ($stockAsOf->lessThan($cutover)) {
            throw new RuntimeException('在庫基準日は取引開始日以降を指定してください。');
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function checks(AccessMigrationBatch $batch, array $imports): array
    {
        $checks = [];
        foreach ([
            'shipment_headers' => ['shipment_headers', $imports['shipments']['shipment_headers']],
            'shipment_lines' => ['shipment_lines', $imports['shipments']['shipment_lines']],
            'non_sales_headers' => ['non_sales_stock_operation_headers', $imports['inventory_history']['operation_headers']],
            'non_sales_lines' => ['non_sales_stock_operation_lines', $imports['inventory_history']['operation_lines']],
            'receivable_ledgers' => ['access_receivable_ledger_entries', $imports['receivables']['ledger_entries']],
            'payments' => ['payments', $imports['receivables']['payments']],
            'opening_receivables' => ['opening_receivable_balances', $imports['receivables']['opening_balances']],
        ] as $name => [$table, $expected]) {
            $actual = DB::table($table)->count();
            $checks[$name] = [
                'expected' => (int) $expected,
                'actual' => $actual,
                'passed' => $actual === (int) $expected,
            ];
        }

        $duplicateChecks = [
            'duplicate_shipment_documents' => DB::table('shipment_headers')
                ->whereNotNull('legacy_access_document_number')
                ->select('legacy_access_document_number')
                ->groupBy('legacy_access_document_number')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
            'duplicate_shipment_lines' => DB::table('shipment_lines')
                ->whereNotNull('legacy_access_line_id')
                ->select('legacy_access_line_id')
                ->groupBy('legacy_access_line_id')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
            'duplicate_payments' => DB::table('access_receivable_ledger_entries')
                ->select('legacy_access_payment_id')
                ->groupBy('legacy_access_payment_id')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
            'duplicate_non_sales' => DB::table('non_sales_stock_operation_headers')
                ->whereNotNull('legacy_access_stock_operation_id')
                ->select('legacy_access_stock_operation_id')
                ->groupBy('legacy_access_stock_operation_id')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
        ];
        foreach ($duplicateChecks as $name => $actual) {
            $checks[$name] = ['expected' => 0, 'actual' => $actual, 'passed' => $actual === 0];
        }
        $checks['billing_not_calculated'] = [
            'expected' => 0,
            'actual' => DB::table('invoice_headers')->count(),
            'passed' => DB::table('invoice_headers')->count() === 0,
        ];
        $checks['batch'] = ['expected' => $batch->id, 'actual' => $batch->id, 'passed' => true];

        return $checks;
    }
}
