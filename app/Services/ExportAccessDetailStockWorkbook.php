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

class ExportAccessDetailStockWorkbook
{
    public function export(AccessMigrationBatch $batch, string $asOfDate): array
    {
        if (! in_array($batch->status, ['receivables_imported', 'completed'], true)) {
            throw new RuntimeException("商品詳細別在庫を出力できないバッチ状態です: {$batch->status}");
        }

        $asOfDate = CarbonImmutable::parse($asOfDate)->toDateString();
        $rows = $this->calculateRows($batch, $asOfDate);
        $relativePath = "access-migrations/reports/access-detail-stock-batch-{$batch->id}-{$asOfDate}.xlsx";
        $absolutePath = storage_path('app/'.$relativePath);
        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $this->writeWorkbook($batch, $asOfDate, $rows, $absolutePath);

        return [
            'path' => $relativePath,
            'row_count' => count($rows),
            'positive_count' => collect($rows)->where('calculated_stock', '>', 0)->count(),
            'zero_count' => collect($rows)->where('calculated_stock', 0)->count(),
            'negative_count' => collect($rows)->where('calculated_stock', '<', 0)->count(),
            'calculated_stock_total' => collect($rows)->sum('calculated_stock'),
        ];
    }

    /** @return array<int, object> */
    public function calculateRows(AccessMigrationBatch $batch, string $asOfDate): array
    {
        return DB::select(<<<'SQL'
            WITH stock_legs AS (
                SELECT
                    payload->>'増商品ID' AS product_id,
                    payload->>'増商品詳細ID' AS detail_id,
                    ABS(COALESCE(NULLIF(payload->>'増個数', ''), '0')::numeric) AS increase_quantity,
                    0::numeric AS decrease_quantity,
                    CAST(payload->>'年月日' AS date) AS activity_date
                FROM access_migration_staging_rows
                WHERE batch_id = ? AND source_table = '伝票外在庫出入'
                  AND CAST(payload->>'年月日' AS date) <= ?
                  AND NULLIF(payload->>'増商品ID', '') IS NOT NULL
                  AND NULLIF(payload->>'増商品詳細ID', '') IS NOT NULL
                UNION ALL
                SELECT
                    payload->>'減商品ID',
                    payload->>'減商品詳細ID',
                    0::numeric,
                    ABS(COALESCE(NULLIF(payload->>'減個数', ''), '0')::numeric),
                    CAST(payload->>'年月日' AS date)
                FROM access_migration_staging_rows
                WHERE batch_id = ? AND source_table = '伝票外在庫出入'
                  AND CAST(payload->>'年月日' AS date) <= ?
                  AND NULLIF(payload->>'減商品ID', '') IS NOT NULL
                  AND NULLIF(payload->>'減商品詳細ID', '') IS NOT NULL
            ),
            stock AS (
                SELECT product_id, detail_id,
                       SUM(increase_quantity) AS increase_quantity,
                       SUM(decrease_quantity) AS decrease_quantity,
                       MIN(activity_date) AS first_date,
                       MAX(activity_date) AS last_date
                FROM stock_legs
                GROUP BY product_id, detail_id
            ),
            shipment AS (
                SELECT
                    l.payload->>'商品ID' AS product_id,
                    l.payload->>'商品詳細ID' AS detail_id,
                    SUM(CASE WHEN COALESCE(NULLIF(l.payload->>'個数', ''), '0')::numeric > 0
                             THEN COALESCE(NULLIF(l.payload->>'個数', ''), '0')::numeric ELSE 0 END) AS shipped_quantity,
                    SUM(CASE WHEN COALESCE(NULLIF(l.payload->>'個数', ''), '0')::numeric < 0
                             THEN ABS(COALESCE(NULLIF(l.payload->>'個数', ''), '0')::numeric) ELSE 0 END) AS return_quantity,
                    MIN(CAST(h.payload->>'年月日' AS date)) AS first_date,
                    MAX(CAST(h.payload->>'年月日' AS date)) AS last_date
                FROM access_migration_staging_rows l
                JOIN access_migration_staging_rows h
                  ON h.batch_id = l.batch_id
                 AND h.source_table = '出荷伝票・取引先'
                 AND h.source_key = l.payload->>'伝票番号'
                WHERE l.batch_id = ? AND l.source_table = '出荷伝票・商品'
                  AND CAST(h.payload->>'年月日' AS date) <= ?
                  AND NULLIF(l.payload->>'商品ID', '') IS NOT NULL
                  AND NULLIF(l.payload->>'商品詳細ID', '') IS NOT NULL
                GROUP BY l.payload->>'商品ID', l.payload->>'商品詳細ID'
            ),
            pairs AS (
                SELECT product_id, detail_id FROM stock
                UNION
                SELECT product_id, detail_id FROM shipment
            ),
            detail_names AS (
                SELECT source_key AS detail_id,
                       NULLIF(payload->>'商品詳細名称', '') AS detail_name
                FROM access_migration_staging_rows
                WHERE batch_id = ? AND source_table = '商品詳細名称'
            )
            SELECT
                pairs.product_id AS access_product_id,
                p.id AS product_id,
                p.product_code,
                p.display_name AS product_name,
                p.product_type,
                p.inventory_unit_id,
                p.base_unit_id,
                p.alcohol_percentage,
                p.is_alcohol,
                p.capacity_value,
                cu.code AS capacity_unit,
                pairs.detail_id AS access_detail_id,
                COALESCE(dn.detail_name, pl.legacy_lot_text, '名称未登録') AS detail_name,
                pl.id AS production_lot_id,
                COALESCE(stock.increase_quantity, 0) AS increase_quantity,
                COALESCE(stock.decrease_quantity, 0) AS decrease_quantity,
                COALESCE(shipment.shipped_quantity, 0) AS shipped_quantity,
                COALESCE(shipment.return_quantity, 0) AS return_quantity,
                COALESCE(stock.increase_quantity, 0)
                  - COALESCE(stock.decrease_quantity, 0)
                  - COALESCE(shipment.shipped_quantity, 0)
                  + COALESCE(shipment.return_quantity, 0) AS calculated_stock,
                LEAST(stock.first_date, shipment.first_date) AS first_activity_date,
                GREATEST(stock.last_date, shipment.last_date) AS last_activity_date
            FROM pairs
            LEFT JOIN stock USING (product_id, detail_id)
            LEFT JOIN shipment USING (product_id, detail_id)
            LEFT JOIN detail_names dn ON dn.detail_id = pairs.detail_id
            LEFT JOIN access_migration_mappings m
              ON m.batch_id = ?
             AND m.source_table = '商品マスター'
             AND m.target_table = 'products'
             AND m.source_key = pairs.product_id
            LEFT JOIN products p ON CAST(p.id AS text) = m.target_id
            LEFT JOIN units cu ON cu.id = p.capacity_unit_id
            LEFT JOIN production_lots pl ON pl.external_system_code = 'ITARO-DETAIL-' || pairs.detail_id
            ORDER BY
                CASE p.product_type WHEN 'sake' THEN 1 WHEN 'kasu' THEN 2 ELSE 3 END,
                CASE WHEN pairs.product_id ~ '^[0-9]+$' THEN pairs.product_id::bigint END,
                pairs.product_id,
                CASE WHEN pairs.detail_id ~ '^[0-9]+$' THEN pairs.detail_id::bigint END,
                pairs.detail_id
            SQL, [
            $batch->id, $asOfDate,
            $batch->id, $asOfDate,
            $batch->id, $asOfDate,
            $batch->id,
            $batch->id,
        ]);
    }

    private function writeWorkbook(AccessMigrationBatch $batch, string $asOfDate, array $rows, string $path): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('商品詳細別在庫');
        $sheet->fromArray([
            ['商品・詳細ID別 計算在庫'],
            ['基準日', $asOfDate, 'Access移行バッチ', $batch->id, '原本', $batch->source_file_name],
            ['計算式', '在庫増加 - 在庫減少 - 正出荷 + 返品・赤伝'],
            [],
            ['商品ID', '商品コード', '商品名', '商品区分', '容量', '容量単位', '商品詳細ID', '詳細名称', '在庫増加', '在庫減少', '正出荷', '返品・赤伝', '計算在庫', '判定', '初回日', '最終日'],
        ], null, 'A1');

        $startRow = 6;
        foreach ($rows as $index => $row) {
            $excelRow = $startRow + $index;
            $sheet->fromArray([
                (string) $row->access_product_id,
                $row->product_code,
                $row->product_name,
                $this->productTypeLabel($row->product_type),
                $row->capacity_value === null ? null : (float) $row->capacity_value,
                $row->capacity_unit,
                (string) $row->access_detail_id,
                $row->detail_name,
                (float) $row->increase_quantity,
                (float) $row->decrease_quantity,
                (float) $row->shipped_quantity,
                (float) $row->return_quantity,
                "=I{$excelRow}-J{$excelRow}-K{$excelRow}+L{$excelRow}",
                "=IF(M{$excelRow}<0,\"負在庫\",IF(M{$excelRow}=0,\"在庫0\",\"正在庫\"))",
                $row->first_activity_date,
                $row->last_activity_date,
            ], null, 'A'.$excelRow);

            $fill = match (true) {
                (float) $row->calculated_stock < 0 => 'FFFDECEC',
                (float) $row->calculated_stock > 0 => 'FFEAF6EC',
                default => null,
            };
            if ($fill !== null) {
                $sheet->getStyle("M{$excelRow}:N{$excelRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
            }
        }

        $lastRow = $startRow + count($rows) - 1;
        $sheet->mergeCells('A1:P1');
        $sheet->getStyle('A1:P1')->getFont()->setBold(true)->setSize(16);
        $sheet->freezePane('A6');
        $sheet->setAutoFilter("A5:P{$lastRow}");
        $sheet->getStyle("A5:P{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD0D7DE');
        $sheet->getStyle('A5:P5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A5:P5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF315B74');
        $sheet->getStyle("A5:P{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle("E{$startRow}:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.####');
        $sheet->getStyle("I{$startRow}:M{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.####');
        $sheet->getStyle("O{$startRow}:P{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        foreach ([12, 18, 36, 12, 11, 11, 14, 48, 13, 13, 13, 13, 13, 11, 13, 13] as $index => $width) {
            $sheet->getColumnDimension(chr(65 + $index))->setWidth($width);
        }
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);

        $summary = $book->createSheet();
        $summary->setTitle('集計');
        $summary->fromArray([
            ['商品詳細別在庫 集計'],
            ['基準日', $asOfDate],
            [],
            ['商品区分', '明細組合せ数', '正在庫', '在庫0', '負在庫', '計算在庫合計'],
        ], null, 'A1');
        $summaryRows = collect($rows)
            ->groupBy(fn (object $row): string => $row->product_type ?? 'unknown')
            ->map(function ($group, string $type): array {
                return [
                    $this->productTypeLabel($type),
                    $group->count(),
                    $group->filter(fn (object $row): bool => (float) $row->calculated_stock > 0)->count(),
                    $group->filter(fn (object $row): bool => (float) $row->calculated_stock === 0.0)->count(),
                    $group->filter(fn (object $row): bool => (float) $row->calculated_stock < 0)->count(),
                    $group->sum(fn (object $row): float => (float) $row->calculated_stock),
                ];
            })->values()->all();
        $summary->fromArray($summaryRows, null, 'A5');
        $summaryLastRow = 4 + count($summaryRows);
        $summary->mergeCells('A1:F1');
        $summary->getStyle('A1:F1')->getFont()->setBold(true)->setSize(16);
        $summary->getStyle("A4:F{$summaryLastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD0D7DE');
        $summary->getStyle('A4:F4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $summary->getStyle('A4:F4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF315B74');
        $summary->getStyle("B5:F{$summaryLastRow}")->getNumberFormat()->setFormatCode('#,##0.####');
        foreach ([18, 18, 14, 14, 14, 20] as $index => $width) {
            $summary->getColumnDimension(chr(65 + $index))->setWidth($width);
        }
        $summary->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1);

        $book->setActiveSheetIndex(0);
        $book->getCalculationEngine()->setCalculationCacheEnabled(false);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }

    private function productTypeLabel(?string $type): string
    {
        return match ($type) {
            'sake' => '酒類',
            'kasu' => '酒粕',
            'goods' => 'その他',
            default => '未分類',
        };
    }
}
