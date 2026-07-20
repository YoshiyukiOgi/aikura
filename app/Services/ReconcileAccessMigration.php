<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ReconcileAccessMigration
{
    public function run(AccessMigrationBatch $batch, bool $finalize = false): array
    {
        if (! in_array($batch->status, ['receivables_imported', 'completed'], true)) {
            throw new RuntimeException("照合できないAccess移行バッチ状態です: {$batch->status}");
        }

        $rows = $this->reconciliationRows($batch);
        $tableRows = $this->tableRows($batch);
        $passed = collect($rows)->every(fn (array $row): bool => $row['passed'])
            && collect($tableRows)->every(fn (array $row): bool => $row['passed'])
            && $batch->error_count === 0;

        if ($finalize && ! $passed) {
            throw new RuntimeException('不一致または未解決エラーがあるため、Access移行バッチを確定できません。');
        }

        $relativePath = 'access-migrations/reports/access-migration-reconciliation-batch-'.$batch->id.'.xlsx';
        $absolutePath = storage_path('app/'.$relativePath);
        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $displayStatus = $finalize ? 'completed' : $batch->status;
        $this->writeWorkbook($batch, $rows, $tableRows, $absolutePath, $displayStatus, $passed);

        $summary = [
            'passed' => $passed,
            'check_count' => count($rows) + count($tableRows),
            'mismatch_count' => collect($rows)->where('passed', false)->count()
                + collect($tableRows)->where('passed', false)->count(),
            'report_path' => $relativePath,
            'reconciled_at' => now()->toIso8601String(),
        ];

        $validationSummary = $batch->validation_summary ?? [];
        $validationSummary['reconciliation'] = $summary;
        $batch->update([
            'status' => $finalize ? 'completed' : $batch->status,
            'validation_summary' => $validationSummary,
            'completed_at' => $finalize ? now() : $batch->completed_at,
        ]);

        return $summary + ['rows' => $rows, 'table_rows' => $tableRows];
    }

    private function reconciliationRows(AccessMigrationBatch $batch): array
    {
        $asOfDate = CarbonImmutable::parse($batch->source_last_modified_at ?? $batch->started_at)
            ->setTimezone(config('app.timezone'))
            ->toDateString();
        $rows = [];

        foreach ([
            ['マスター', '得意先件数', '件', '取引先マスター', 'customers'],
            ['マスター', '商品件数', '件', '商品マスター', 'products'],
            ['出荷', '出荷伝票件数', '件', '出荷伝票・取引先', 'shipment_headers'],
            ['出荷', '出荷明細件数', '件', '出荷伝票・商品', 'shipment_lines'],
            ['在庫履歴', '伝票外在庫出入件数', '件', '伝票外在庫出入', 'non_sales_stock_operation_headers'],
            ['在庫履歴', '伝票外在庫明細件数', '件', '伝票外在庫出入:明細', 'non_sales_stock_operation_lines'],
            ['売掛', '符号付き入金台帳件数', '件', '入金', 'access_receivable_ledger_entries'],
        ] as [$section, $metric, $unit, $sourceTable, $targetTable]) {
            $source = $sourceTable === '伝票外在庫出入:明細'
                ? $this->inventorySourceLineCount($batch)
                : $this->sourceCount($batch, $sourceTable);
            $target = $this->mappedTargetCount($batch, $sourceTable, $targetTable);
            $rows[] = $this->row($section, $metric, $unit, $source, $target, '0', '移行対応表と実テーブルの存在を照合');
        }

        $expectedPayments = DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', '入金')
            ->whereRaw("COALESCE((payload->>'前月分請求')::boolean, false) = false")
            ->whereRaw("COALESCE(NULLIF(payload->>'金額', ''), '0')::numeric < 0")
            ->count();
        $actualPayments = DB::table('payments as p')
            ->join('access_receivable_ledger_entries as l', 'l.id', '=', 'p.access_receivable_ledger_entry_id')
            ->where('l.access_migration_batch_id', $batch->id)
            ->where('p.is_legacy_history', true)
            ->count();
        $rows[] = $this->row('売掛', '入金・振込料履歴件数', '件', $expectedPayments, $actualPayments, '0', '負額かつ前月請求以外を入金履歴化');

        $sourceShipmentQuantity = $this->sourceSum($batch, '出荷伝票・商品', '個数');
        $targetShipmentQuantity = DB::table('shipment_lines as l')
            ->join('access_migration_mappings as m', function ($join) use ($batch): void {
                $join->where('m.batch_id', $batch->id)
                    ->where('m.source_table', '出荷伝票・商品')
                    ->where('m.target_table', 'shipment_lines')
                    ->whereColumn('m.target_id', DB::raw('CAST(l.id AS text)'));
            })
            ->sum('l.confirmed_quantity');
        $rows[] = $this->row('出荷', '出荷数量合計', '個', $sourceShipmentQuantity, $targetShipmentQuantity, '0.0001', '負数・0数量を含む');

        foreach ([
            ['売上額合計', '金額', 'legacy_access_net_amount'],
            ['消費税額合計', '消費税額', 'legacy_access_consumption_tax_amount'],
        ] as [$metric, $sourceField, $targetField]) {
            $source = $this->sourceSum($batch, '出荷伝票・取引先', $sourceField);
            $target = DB::table('shipment_headers as h')
                ->join('access_migration_mappings as m', function ($join) use ($batch): void {
                    $join->where('m.batch_id', $batch->id)
                        ->where('m.source_table', '出荷伝票・取引先')
                        ->where('m.target_table', 'shipment_headers')
                        ->whereColumn('m.target_id', DB::raw('CAST(h.id AS text)'));
                })
                ->sum('h.'.$targetField);
            $rows[] = $this->row('出荷', $metric, '円', $source, $target, '0.01', 'Access保存済み伝票金額');
        }

        $sourceLiquorTax = $this->sourceLiquorTaxAmount($batch);
        $targetLiquorTax = DB::table('shipment_lines as l')
            ->join('access_migration_mappings as m', function ($join) use ($batch): void {
                $join->where('m.batch_id', $batch->id)
                    ->where('m.source_table', '出荷伝票・商品')
                    ->where('m.target_table', 'shipment_lines')
                    ->whereColumn('m.target_id', DB::raw('CAST(l.id AS text)'));
            })
            ->sum('l.confirmed_liquor_tax_estimated_amount');
        $rows[] = $this->row('酒税', '酒税算定額合計', '円', $sourceLiquorTax, $targetLiquorTax, '0.01', '酒類商品の数量・容量・税率・旧軽減率から行単位で再計算');

        $inventorySource = DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', '伝票外在庫出入')
            ->selectRaw("SUM(ABS(COALESCE(NULLIF(payload->>'増個数', ''), '0')::numeric)) as increase")
            ->selectRaw("SUM(ABS(COALESCE(NULLIF(payload->>'減個数', ''), '0')::numeric)) as decrease")
            ->first();
        $inventoryTarget = DB::table('non_sales_stock_operation_lines as l')
            ->join('access_migration_mappings as m', function ($join) use ($batch): void {
                $join->where('m.batch_id', $batch->id)
                    ->where('m.source_table', '伝票外在庫出入:明細')
                    ->where('m.target_table', 'non_sales_stock_operation_lines')
                    ->whereColumn('m.target_id', DB::raw('CAST(l.id AS text)'));
            })
            ->selectRaw("SUM(CASE WHEN m.source_key LIKE '%:plus' THEN ABS(l.quantity) ELSE 0 END) as increase")
            ->selectRaw("SUM(CASE WHEN m.source_key LIKE '%:minus' THEN ABS(l.quantity) ELSE 0 END) as decrease")
            ->first();
        $rows[] = $this->row('在庫履歴', '増加数量合計', '個', $inventorySource->increase, $inventoryTarget->increase, '0.0001', '0数量を含む');
        $rows[] = $this->row('在庫履歴', '減少数量合計', '個', $inventorySource->decrease, $inventoryTarget->decrease, '0.0001', '絶対値で比較、0数量を含む');

        [$sourceReceivableSales, $sourceLedger] = $this->sourceOpeningComponents($batch, $asOfDate);
        $targetOpening = DB::table('opening_receivable_balances')
            ->where('access_migration_batch_id', $batch->id)
            ->sum('opening_balance_amount');
        $sourceOpening = bcadd($sourceReceivableSales, $sourceLedger, 2);
        $rows[] = $this->row('売掛', '開始売掛残高', '円', $sourceOpening, $targetOpening, '0.01', "基準日{$asOfDate}、売掛対象出荷＋符号付き台帳");

        return $rows;
    }

    private function tableRows(AccessMigrationBatch $batch): array
    {
        return $batch->tables()->orderBy('source_table')->get()->map(function ($table): array {
            $difference = bcsub((string) $table->staged_row_count, (string) $table->source_row_count, 0);

            return [
                'source_table' => $table->source_table,
                'source_count' => (string) $table->source_row_count,
                'staged_count' => (string) $table->staged_row_count,
                'difference' => $difference,
                'passed' => bccomp($difference, '0', 0) === 0,
                'status' => $table->status,
            ];
        })->all();
    }

    private function row(string $section, string $metric, string $unit, mixed $source, mixed $target, string $tolerance, string $note): array
    {
        $scale = $unit === '件' ? 0 : ($unit === '円' ? 2 : 4);
        $source = bcadd((string) ($source ?? 0), '0', $scale);
        $target = bcadd((string) ($target ?? 0), '0', $scale);
        $difference = bcsub($target, $source, $scale);

        return compact('section', 'metric', 'unit', 'source', 'target', 'difference', 'tolerance', 'note') + [
            'passed' => bccomp(ltrim($difference, '-'), $tolerance, $scale) <= 0,
        ];
    }

    private function sourceCount(AccessMigrationBatch $batch, string $sourceTable): int
    {
        return (int) $batch->tables()->where('source_table', $sourceTable)->value('source_row_count');
    }

    private function mappedTargetCount(AccessMigrationBatch $batch, string $sourceTable, string $targetTable): int
    {
        return DB::table('access_migration_mappings as m')
            ->join($targetTable.' as t', DB::raw('CAST(t.id AS text)'), '=', 'm.target_id')
            ->where('m.batch_id', $batch->id)
            ->where('m.source_table', $sourceTable)
            ->where('m.target_table', $targetTable)
            ->distinct('m.source_key')
            ->count('m.source_key');
    }

    private function inventorySourceLineCount(AccessMigrationBatch $batch): int
    {
        $row = DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', '伝票外在庫出入')
            ->selectRaw("COUNT(*) FILTER (WHERE NULLIF(payload->>'増商品ID', '') IS NOT NULL AND NULLIF(payload->>'増商品詳細ID', '') IS NOT NULL) as plus_count")
            ->selectRaw("COUNT(*) FILTER (WHERE NULLIF(payload->>'減商品ID', '') IS NOT NULL AND NULLIF(payload->>'減商品詳細ID', '') IS NOT NULL) as minus_count")
            ->first();

        return (int) $row->plus_count + (int) $row->minus_count;
    }

    private function sourceSum(AccessMigrationBatch $batch, string $sourceTable, string $field): string
    {
        return (string) DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', $sourceTable)
            ->selectRaw("COALESCE(SUM(COALESCE(NULLIF(payload->>?, ''), '0')::numeric), 0) as amount", [$field])
            ->value('amount');
    }

    private function sourceLiquorTaxAmount(AccessMigrationBatch $batch): string
    {
        $row = DB::table('access_migration_staging_rows as l')
            ->join('access_migration_staging_rows as p', function ($join) use ($batch): void {
                $join->where('p.batch_id', $batch->id)
                    ->where('p.source_table', '商品マスター')
                    ->whereColumn('p.source_key', DB::raw("l.payload->>'商品ID'"));
            })
            ->join('shipment_headers as h', 'h.legacy_access_document_number', '=', DB::raw("l.payload->>'伝票番号'"))
            ->where('l.batch_id', $batch->id)
            ->where('l.source_table', '出荷伝票・商品')
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(
                    CASE
                        WHEN NULLIF(p.payload->>'酒種類ID', '') IS NULL
                          OR h.confirmed_liquor_tax_treatment IN ('export_exempt', 'untaxed_transfer')
                        THEN 0
                        ELSE ROUND(
                            COALESCE(NULLIF(l.payload->>'個数', ''), '0')::numeric
                            * COALESCE(NULLIF(p.payload->>'容量(ml)', ''), '0')::numeric / 1000000
                            * COALESCE(NULLIF(l.payload->>'酒税', ''), '0')::numeric
                            * COALESCE(NULLIF(l.payload->>'軽減率', ''), '100')::numeric / 100,
                            2
                        )
                    END
                ), 0) as amount
                SQL)
            ->first();

        return (string) $row->amount;
    }

    private function sourceOpeningComponents(AccessMigrationBatch $batch, string $asOfDate): array
    {
        $sales = DB::table('access_migration_staging_rows as s')
            ->join('access_migration_mappings as m', function ($join) use ($batch): void {
                $join->where('m.batch_id', $batch->id)
                    ->where('m.source_table', '取引先マスター')
                    ->where('m.target_table', 'customers')
                    ->whereColumn('m.source_key', DB::raw("s.payload->>'取引先ID'"));
            })
            ->join('customers as c', DB::raw('CAST(c.id AS text)'), '=', 'm.target_id')
            ->join('settlement_receivable_categories as r', 'r.id', '=', 'c.settlement_receivable_category_id')
            ->where('s.batch_id', $batch->id)
            ->where('s.source_table', '出荷伝票・取引先')
            ->where('r.receivable_method', 'accounts_receivable')
            ->whereRaw("CAST(s.payload->>'年月日' AS date) <= ?", [$asOfDate])
            ->selectRaw("COALESCE(SUM(COALESCE(NULLIF(s.payload->>'合計', ''), '0')::numeric), 0) as amount")
            ->value('amount');
        $ledger = DB::table('access_migration_staging_rows as s')
            ->join('access_migration_mappings as m', function ($join) use ($batch): void {
                $join->where('m.batch_id', $batch->id)
                    ->where('m.source_table', '取引先マスター')
                    ->where('m.target_table', 'customers')
                    ->whereColumn('m.source_key', DB::raw("s.payload->>'取引先ID'"));
            })
            ->join('customers as c', DB::raw('CAST(c.id AS text)'), '=', 'm.target_id')
            ->join('settlement_receivable_categories as r', 'r.id', '=', 'c.settlement_receivable_category_id')
            ->where('s.batch_id', $batch->id)
            ->where('s.source_table', '入金')
            ->where('r.receivable_method', 'accounts_receivable')
            ->whereRaw("CAST(s.payload->>'年月日' AS date) <= ?", [$asOfDate])
            ->selectRaw("COALESCE(SUM(COALESCE(NULLIF(s.payload->>'金額', ''), '0')::numeric), 0) as amount")
            ->value('amount');

        return [bcadd((string) $sales, '0', 2), bcadd((string) $ledger, '0', 2)];
    }

    private function writeWorkbook(AccessMigrationBatch $batch, array $rows, array $tableRows, string $path, string $status, bool $passed): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('照合結果');
        $sheet->fromArray([
            ['Access移行前後照合表'],
            ['バッチID', $batch->id, null, '状態', $status],
            ['原本ファイル', $batch->source_file_name, null, '原本SHA-256', $batch->source_sha256],
            ['原本更新日時', $batch->source_last_modified_at?->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'), null, '照合日時', now()->format('Y-m-d H:i:s')],
            ['総合判定', $passed ? '一致' : '不一致', null, '既知警告件数', $batch->warning_count],
            [],
            ['区分', '項目', '単位', 'Access原値', '移行後', '差額', '判定', '許容差', '備考'],
        ], null, 'A1');

        $startRow = 8;
        foreach ($rows as $index => $row) {
            $excelRow = $startRow + $index;
            $sheet->fromArray([
                $row['section'], $row['metric'], $row['unit'], (float) $row['source'], (float) $row['target'],
                "=E{$excelRow}-D{$excelRow}", "=IF(ABS(F{$excelRow})<=H{$excelRow},\"一致\",\"不一致\")",
                (float) $row['tolerance'], $row['note'],
            ], null, 'A'.$excelRow);
        }
        $lastRow = $startRow + count($rows) - 1;
        $this->styleSheet($sheet, 'A7:I'.$lastRow, [13, 25, 9, 18, 18, 14, 11, 12, 48]);
        foreach ($rows as $index => $row) {
            $format = $row['unit'] === '件' ? '#,##0' : '#,##0.00####';
            $sheet->getStyle('D'.($startRow + $index).':H'.($startRow + $index))->getNumberFormat()->setFormatCode($format);
        }
        $sheet->getStyle('A1:I1')->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('B5')->getFont()->setBold(true)->getColor()->setARGB($passed ? 'FF1B5E20' : 'FFB71C1C');
        $this->fitForPrint($sheet, PageSetup::PAPERSIZE_A3);

        $tables = $book->createSheet();
        $tables->setTitle('テーブル件数');
        $tables->fromArray([['Accessテーブル件数照合'], [], ['テーブル', '原本件数', 'ステージ件数', '差額', '判定', '状態']], null, 'A1');
        foreach ($tableRows as $index => $row) {
            $excelRow = 4 + $index;
            $tables->fromArray([
                $row['source_table'], (int) $row['source_count'], (int) $row['staged_count'],
                "=C{$excelRow}-B{$excelRow}", "=IF(D{$excelRow}=0,\"一致\",\"不一致\")", $row['status'],
            ], null, 'A'.$excelRow);
        }
        $tableLastRow = 3 + count($tableRows);
        $this->styleSheet($tables, 'A3:F'.$tableLastRow, [30, 15, 15, 12, 11, 14]);
        $tables->getStyle('A1:F1')->getFont()->setBold(true)->setSize(16);
        $tables->mergeCells('A1:F1');
        $this->fitForPrint($tables, PageSetup::PAPERSIZE_A4);

        $issues = $book->createSheet();
        $issues->setTitle('既知警告');
        $issues->fromArray([['移行時の既知警告'], [], ['重要度', 'コード', '対象テーブル', '件数', '内容', '解決日時']], null, 'A1');
        foreach ($batch->issues()->orderBy('severity')->orderBy('issue_code')->get() as $index => $issue) {
            $issues->fromArray([
                $issue->severity, $issue->issue_code, $issue->source_table,
                (int) ($issue->context['count'] ?? 0), $issue->message, $issue->resolved_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.(4 + $index));
        }
        $issueLastRow = max(4, 3 + $batch->issues()->count());
        $this->styleSheet($issues, 'A3:F'.$issueLastRow, [12, 38, 28, 12, 70, 20]);
        $issues->getStyle('A1:F1')->getFont()->setBold(true)->setSize(16);
        $issues->mergeCells('A1:F1');
        $this->fitForPrint($issues, PageSetup::PAPERSIZE_A3);

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }

    private function styleSheet($sheet, string $range, array $widths): void
    {
        [$headerRange] = explode(':', $range);
        $headerRow = preg_replace('/[^0-9]/', '', $headerRange);
        $lastColumn = chr(64 + count($widths));
        $sheet->freezePane('A'.((int) $headerRow + 1));
        $sheet->setAutoFilter($range);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD0D7DE');
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF315B74');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle($range)->getAlignment()->setWrapText(true);
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(chr(65 + $index))->setWidth($width);
        }
    }

    private function fitForPrint($sheet, int $paperSize): void
    {
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize($paperSize)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.35)
            ->setRight(0.25)
            ->setBottom(0.35)
            ->setLeft(0.25);
    }
}
