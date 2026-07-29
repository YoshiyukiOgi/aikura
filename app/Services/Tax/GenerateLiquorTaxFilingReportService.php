<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\LiquorTaxFilingReportExportException;
use App\Models\LiquorTaxMonthlyFiling;
use App\Models\ReportExport;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Process\Process;

class GenerateLiquorTaxFilingReportService
{
    public function __construct(private readonly AuditLogService $auditLogService, private readonly Filesystem $filesystem) {}

    public function generate(LiquorTaxMonthlyFiling $filing, string $format = 'txt', ?string $reason = null): ReportExport
    {
        $format = strtolower($format);
        if (! in_array($format, ['txt', 'xlsx', 'pdf'], true)) {
            throw LiquorTaxFilingReportExportException::unsupportedFormat($format);
        }

        return DB::transaction(function () use ($filing, $format, $reason): ReportExport {
            $filing = LiquorTaxMonthlyFiling::query()->with(['lines.sources', 'adjustments.category', 'reliefSetting', 'reportExports'])->lockForUpdate()->findOrFail($filing->id);
            if ($filing->status !== 'confirmed') {
                throw LiquorTaxFilingReportExportException::notConfirmed($filing->id, $filing->status);
            }

            $relativePath = $this->relativePath($filing, $format);
            $absolutePath = storage_path('app/'.$relativePath);
            $this->filesystem->ensureDirectoryExists(dirname($absolutePath));

            if ($format === 'txt') {
                $this->filesystem->put($absolutePath, $this->renderText($filing));
            } elseif ($format === 'xlsx') {
                $this->writeSpreadsheet($filing, $absolutePath);
            } else {
                $this->writePdf($filing, $absolutePath);
            }
            if (! is_file($absolutePath)) {
                throw LiquorTaxFilingReportExportException::writeFailed($relativePath);
            }

            $mime = ['txt' => 'text/plain', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'][$format];
            $export = ReportExport::create([
                'report_type' => 'liquor_tax_filing', 'format' => $format, 'status' => 'generated',
                'exportable_type' => $filing::class, 'exportable_id' => $filing->id, 'disk' => 'local',
                'file_path' => $relativePath, 'file_name' => basename($relativePath), 'mime_type' => $mime,
                'file_size' => filesize($absolutePath), 'checksum_sha256' => hash_file('sha256', $absolutePath),
                'generated_at' => now(), 'reason' => $reason,
            ]);
            $this->auditLogService->record(new AuditLogData(
                event: 'liquor_tax_filing_report.generated', auditable: $filing,
                afterValues: ['report_export_id' => $export->id, 'format' => $format, 'file_path' => $relativePath, 'checksum_sha256' => $export->checksum_sha256],
                reason: $reason,
            ));

            return $export->refresh();
        });
    }

    private function writeSpreadsheet(LiquorTaxMonthlyFiling $filing, string $path): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('e-Tax転記確認表');
        $sheet->setCellValue('A1', '酒税納税申告 e-Tax転記確認表')->mergeCells('A1:I1');
        $sheet->setCellValue('A3', '対象期間')->setCellValue('B3', $filing->period_start->format('Y/m/d').' - '.$filing->period_end->format('Y/m/d'));
        $sheet->setCellValue('E3', '製造場')->setCellValue('F3', $filing->manufacturing_site_code);
        $sheet->setCellValue('A5', '申告項目')->setCellValue('D5', 'システム確定値')->mergeCells('A5:C5')->mergeCells('D5:I5');
        $summary = [
            ['課税標準数量 (kl)', (float) $filing->total_taxable_kl],
            ['算出税額', (float) $filing->total_gross_tax_amount],
            ['租税特別措置法による軽減税額', (float) $filing->total_relief_amount],
            ['控除税額', (float) $filing->total_deduction_amount],
            ['手動調整額', (float) $filing->total_adjustment_amount],
            ['納付すべき税額', (float) $filing->total_confirmed_amount],
        ];
        foreach ($summary as $index => [$label, $value]) {
            $row = 6 + $index;
            $sheet->setCellValue("A{$row}", $label)->mergeCells("A{$row}:C{$row}");
            $sheet->setCellValue("D{$row}", $value)->mergeCells("D{$row}:I{$row}");
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($index === 0 ? '0.000000' : '#,##0');
        }
        $summaryRow = 12;
        foreach ([
            ['軽減後税額（100円未満切捨前）', $this->preRoundPayableAmount($filing)],
            ['100円未満切捨額', $this->paymentRoundingAmount($filing)],
            ['警告件数', (int) $filing->warning_count],
        ] as $extra) {
            $sheet->setCellValue("A{$summaryRow}", $extra[0])->mergeCells("A{$summaryRow}:C{$summaryRow}");
            $sheet->setCellValue("D{$summaryRow}", $extra[1])->mergeCells("D{$summaryRow}:I{$summaryRow}");
            $sheet->getStyle("D{$summaryRow}")->getNumberFormat()->setFormatCode('#,##0');
            $summaryRow++;
        }

        $row = 17;
        $headers = ['酒類区分', '取扱区分', 'アルコール度数', '課税数量(kl)', '税率(円/kl)', '軽減前税額', '軽減税額', '控除税額', '計算後税額'];
        foreach ($headers as $column => $header) {
            $sheet->setCellValue([$column + 1, $row], $header);
        }
        foreach ($filing->lines as $line) {
            $row++;
            $sheet->fromArray([$this->liquorTaxCategoryLabel($line->liquor_tax_category_code, $line->liquor_tax_category_name), $this->taxTreatmentLabel($line->tax_treatment), $this->alcoholPercentageLabel($line->reporting_alcohol_percentage), (float) $line->taxable_kl, (float) ($line->tax_per_kl ?? 0), (float) $line->gross_tax_amount, (float) $line->relief_amount, (float) $line->deduction_amount, (float) $line->net_tax_amount], null, "A{$row}");
        }
        $row += 3;
        $sheet->setCellValue("A{$row}", '調整明細')->mergeCells("A{$row}:I{$row}");
        $row++;
        $adjustmentHeaders = ['No.', '区分', '酒類区分', '数量調整(kl)', '税額調整', '内容', '承認要否', '状態'];
        foreach ($adjustmentHeaders as $column => $header) {
            $sheet->setCellValue([$column + 1, $row], $header);
        }
        foreach ($filing->adjustments->where('status', 'active') as $adjustment) {
            $row++;
            $sheet->fromArray([$adjustment->line_no, $adjustment->adjustment_type, $this->liquorTaxCategoryLabel($adjustment->category?->code, $adjustment->category?->name), (float) $adjustment->taxable_kl_adjustment, (float) $adjustment->tax_amount_adjustment, $adjustment->description, $adjustment->approval_required ? '必要' : '不要', $adjustment->status], null, "A{$row}");
        }

        $transferSheet = $book->createSheet();
        $transferSheet->setTitle('転記チェック');
        $this->writeTransferCheckSheet($transferSheet, $filing);

        $sourceSheet = $book->createSheet();
        $sourceSheet->setTitle('根拠明細');
        $sourceHeaders = ['日付', '伝票番号', '元データ', '酒類区分', '取扱区分', 'アルコール度数', '数量', '課税数量(kl)', '軽減前税額', '証明状態', '証明番号', '要確認理由'];
        foreach ($sourceHeaders as $column => $header) {
            $sourceSheet->setCellValue([$column + 1, 1], $header);
        }
        $sourceRow = 1;
        foreach ($filing->lines as $line) {
            foreach ($line->sources as $source) {
                $sourceRow++;
                $sourceSheet->fromArray([$source->source_date?->format('Y/m/d'), $source->source_document_number, $source->source_type, $this->liquorTaxCategoryLabel($line->liquor_tax_category_code, $line->liquor_tax_category_name), $this->taxTreatmentLabel($source->tax_treatment), $this->alcoholPercentageLabel($source->reporting_alcohol_percentage), (float) $source->quantity, (float) $source->taxable_kl, (float) $source->gross_tax_amount, $source->evidence_status, $source->evidence_reference, $source->review_reason], null, "A{$sourceRow}");
            }
        }

        $basisSheet = $book->createSheet();
        $basisSheet->setTitle('軽減計算根拠');
        $basisHeaders = ['No.', '方式', '酒類区分', '取扱区分', 'アルコール度数', '累計前', '累計後', '率区分', '税額帯', '帯内税額', '軽減率', '帯別軽減額(丸め前)', '行軽減額', '逆算軽減額', '控除額', '丸め'];
        foreach ($basisHeaders as $column => $header) {
            $basisSheet->setCellValue([$column + 1, 1], $header);
        }
        $basisRow = 1;
        foreach ($filing->lines as $line) {
            $basis = $line->relief_calculation_basis ?? [];
            $segments = $basis['segments'] ?? [];
            if ($segments === []) {
                $segments = [[
                    'band' => $basis['scheme'] === 'legacy_scheme' ? 'legacy_quantity_limit' : null,
                    'tax_base_amount' => $basis['relief_base_amount'] ?? '0',
                    'rate' => $basis['reduction_rate'] ?? '0',
                    'relief_amount_unrounded' => $line->relief_amount,
                ]];
            }
            foreach ($segments as $segment) {
                $basisRow++;
                $basisSheet->fromArray([
                    $line->line_no, $basis['scheme'] ?? null, $this->liquorTaxCategoryLabel($line->liquor_tax_category_code, $line->liquor_tax_category_name), $this->taxTreatmentLabel($line->tax_treatment), $this->alcoholPercentageLabel($line->reporting_alcohol_percentage),
                    $line->cumulative_gross_before === null ? null : (float) $line->cumulative_gross_before,
                    $line->cumulative_gross_after === null ? null : (float) $line->cumulative_gross_after,
                    $basis['rate_column'] ?? null, $segment['band'] ?? null, (float) ($segment['tax_base_amount'] ?? 0),
                    (float) ($segment['rate'] ?? 0), (float) ($segment['relief_amount_unrounded'] ?? 0),
                    (float) $line->relief_amount, (float) ($basis['reversed_relief_amount'] ?? 0),
                    (float) $line->deduction_amount, $basis['rounding'] ?? null,
                ], null, "A{$basisRow}");
            }
        }

        $reviewSheet = $book->createSheet();
        $reviewSheet->setTitle('不足情報');
        $this->writeReviewSheet($reviewSheet, $filing);

        foreach ($book->getAllSheets() as $workSheet) {
            $maxColumn = match ($workSheet->getTitle()) {
                'e-Tax転記確認表' => 'I',
                '転記チェック' => 'E',
                '根拠明細' => 'L',
                '不足情報' => 'D',
                default => 'P',
            };
            $workSheet->getStyle("A1:{$maxColumn}".$workSheet->getHighestRow())->getFont()->setName('Noto Sans CJK JP')->setSize(10);
            $workSheet->getStyle("A1:{$maxColumn}1")->getFont()->setBold(true);
            $workSheet->getStyle("A1:{$maxColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F1');
            $workSheet->getStyle("A1:{$maxColumn}".$workSheet->getHighestRow())->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $workSheet->getStyle("A1:{$maxColumn}".$workSheet->getHighestRow())->getAlignment()->setWrapText(true);
            $workSheet->getStyle("A1:{$maxColumn}".$workSheet->getHighestRow())->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('B8C2CC');
            foreach (range('A', $maxColumn) as $column) {
                $workSheet->getColumnDimension($column)->setWidth(in_array($column, ['A', 'D', 'E', 'F', 'I'], true) ? 24 : 15);
            }
            $workSheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4)->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
            $workSheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
            $workSheet->setShowGridlines(false);
        }
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }

    private function writePdf(LiquorTaxMonthlyFiling $filing, string $path): void
    {
        $tempDir = storage_path('app/tmp/liquor-tax-'.Str::uuid());
        $this->filesystem->ensureDirectoryExists($tempDir);
        $xlsx = $tempDir.'/'.pathinfo($path, PATHINFO_FILENAME).'.xlsx';
        try {
            $this->writeSpreadsheet($filing, $xlsx);
            $profile = 'file:///tmp/lo-'.Str::uuid();
            $process = new Process(['libreoffice', '--headless', '-env:UserInstallation='.$profile, '--convert-to', 'pdf', '--outdir', $tempDir, $xlsx]);
            $process->setTimeout(120)->mustRun();
            $converted = $tempDir.'/'.pathinfo($xlsx, PATHINFO_FILENAME).'.pdf';
            if (! is_file($converted) || ! $this->filesystem->move($converted, $path)) {
                throw LiquorTaxFilingReportExportException::writeFailed($path);
            }
        } finally {
            $this->filesystem->deleteDirectory($tempDir);
        }
    }

    private function renderText(LiquorTaxMonthlyFiling $filing): string
    {
        return implode(PHP_EOL, [
            'Liquor Tax Monthly Filing Report', 'Period: '.$filing->period_start->toDateString().' - '.$filing->period_end->toDateString(),
            'Status: '.$filing->status, 'Total Taxable kL: '.$filing->total_taxable_kl,
            'Gross Tax Amount: '.$filing->total_gross_tax_amount, 'Relief Amount: '.$filing->total_relief_amount,
            'Deduction Amount: '.$filing->total_deduction_amount, 'Adjustment Amount: '.$filing->total_adjustment_amount,
            'Total Confirmed Amount: '.$filing->total_confirmed_amount,
        ]).PHP_EOL;
    }

    private function writeTransferCheckSheet(Worksheet $sheet, LiquorTaxMonthlyFiling $filing): void
    {
        $sheet->fromArray(['様式', '項目', '単位', 'システム値', '転記・確認メモ'], null, 'A1');

        $rows = [
            ['税額算出表', '課税標準数量', 'ml', $this->klToMl($filing->total_taxable_kl), '課税移出数量の合計。酒類コード・度数別内訳は公式様式行の確定が必要'],
            ['税額算出表', '免税・未納税移出数量', 'ml', $this->klToMl($this->totalKlForTreatments($filing, ['export_exempt', 'untaxed_transfer'])), '輸出免税と未納税移出の別は証明情報で確認'],
            ['税額算出表', '算出税額', '円', (float) $filing->total_gross_tax_amount, '申告書と軽減税額算出表の基礎額'],
            ['軽減税額算出表', '軽減方式', '', $filing->relief_scheme, '提出済PDFがA区分の場合は new_scheme 設定と累計税額方式を確認'],
            ['軽減税額算出表', '前月以前累計算出税額', '円', $this->previousCumulativeGrossAmount($filing), '4月以外で空欄の場合は期首または前月までの確定申告データが不足'],
            ['軽減税額算出表', '本月算出税額', '円', (float) $filing->total_gross_tax_amount, '税額算出表から転記'],
            ['軽減税額算出表', '軽減税額', '円', (float) $filing->total_relief_amount, '丸め順により1円差が出るため公式画面の値と照合'],
            ['酒税納税申告書', '軽減後税額（100円未満切捨前）', '円', $this->preRoundPayableAmount($filing), '算出税額から軽減・控除・調整を反映した額'],
            ['酒税納税申告書', '100円未満切捨額', '円', $this->paymentRoundingAmount($filing), '納付すべき税額との差額'],
            ['酒税納税申告書', '納付すべき税額', '円', (float) $filing->total_confirmed_amount, 'e-Tax納付額欄へ転記'],
        ];

        $row = 2;
        foreach ($rows as $values) {
            $sheet->fromArray($values, null, "A{$row}");
            $row++;
        }
        $sheet->getStyle('D2:D'.$sheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0');
    }

    private function writeReviewSheet(Worksheet $sheet, LiquorTaxMonthlyFiling $filing): void
    {
        $sheet->fromArray(['区分', '状態', '件数/値', '確認内容'], null, 'A1');

        $missingEvidenceCount = $filing->lines
            ->flatMap(fn ($line) => $line->sources)
            ->filter(fn ($source) => in_array($source->tax_treatment, ['export_exempt', 'untaxed_transfer'], true) && $source->evidence_status !== 'confirmed')
            ->count();
        $reviewSourceCount = $filing->lines->flatMap(fn ($line) => $line->sources)->where('requires_review', true)->count();
        $reviewLineCount = $filing->lines->where('requires_review', true)->count();

        $rows = [
            ['申告者情報', '未登録', '-', '会社名、所在地、電話番号、代表者、提出先税務署を申告用マスタとして保存する必要があります'],
            ['製造場情報', '一部のみ', $filing->manufacturing_site_code, '現在は製造場コードのみ。正式名称・所在地・整理番号などは未保持です'],
            ['公式様式行', '未登録', '-', '順号、区分、酒類コード、品目名、申告用アルコール度数、行順のスナップショットが必要です'],
            ['軽減方式', $filing->relief_scheme === 'new_scheme' ? '確認済候補' : '要確認', $filing->relief_scheme, '2026年6月提出PDFのA区分と一致する軽減設定か確認してください'],
            ['前月以前累計', $this->previousCumulativeGrossAmount($filing) === null ? '不足' : '確認候補', $this->previousCumulativeGrossAmount($filing), '4月・5月確定申告または期首累計スナップショットが必要です'],
            ['免税・未納税証明', $missingEvidenceCount === 0 ? '確認済' : '不足', $missingEvidenceCount, '証明状態がconfirmedでない免税・未納税移出があります'],
            ['要確認データ', ($reviewSourceCount + $reviewLineCount) === 0 ? 'なし' : '要処理', $reviewSourceCount + $reviewLineCount, '販売外移動や税務判定保留の明細を解決してください'],
            ['軽減税額の丸め', '要照合', $this->paymentRoundingAmount($filing), '算出税額×軽減後率を直接丸める公式画面と、軽減額控除方式で1円差が出る場合があります'],
        ];

        $row = 2;
        foreach ($rows as $values) {
            $sheet->fromArray($values, null, "A{$row}");
            $row++;
        }
    }

    private function totalKlForTreatments(LiquorTaxMonthlyFiling $filing, array $taxTreatments): float
    {
        return (float) $filing->lines
            ->whereIn('tax_treatment', $taxTreatments)
            ->sum(fn ($line) => (float) $line->taxable_kl);
    }

    private function previousCumulativeGrossAmount(LiquorTaxMonthlyFiling $filing): ?float
    {
        $values = $filing->lines
            ->pluck('cumulative_gross_before')
            ->filter(fn ($value) => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return (float) $values->max();
    }

    private function preRoundPayableAmount(LiquorTaxMonthlyFiling $filing): float
    {
        return (float) $filing->lines->sum(fn ($line) => (float) $line->net_tax_amount) + (float) $filing->total_adjustment_amount;
    }

    private function paymentRoundingAmount(LiquorTaxMonthlyFiling $filing): float
    {
        return $this->preRoundPayableAmount($filing) - (float) $filing->total_confirmed_amount;
    }

    private function klToMl(string|float|int|null $kl): int
    {
        return (int) round(((float) ($kl ?? 0)) * 1000000);
    }

    private function alcoholPercentageLabel(null|int|string $reportingAlcoholPercentage): string
    {
        if ($reportingAlcoholPercentage === null || $reportingAlcoholPercentage === '') {
            return '-';
        }

        return ((int) $reportingAlcoholPercentage).'度';
    }

    private function taxTreatmentLabel(?string $taxTreatment): string
    {
        return match ($taxTreatment) {
            'taxable' => '課税移出',
            'export_exempt' => '輸出免税',
            'untaxed_transfer' => '未納税移出',
            'return' => '戻入れ控除',
            'not_applicable' => '対象外',
            'review' => '要確認',
            default => $taxTreatment ?: '-',
        };
    }

    private function liquorTaxCategoryLabel(?string $code, ?string $name): string
    {
        return match ($code) {
            'seishu' => '清酒',
            'liqueur' => 'リキュール',
            'other_brewed_liquor' => 'その他の醸造酒',
            'spirits' => 'スピリッツ',
            'mirin' => 'みりん',
            'beer' => 'ビール',
            'export_exempt_liquor' => '輸出免税酒類',
            'untaxed_transfer_liquor' => '未納税移出酒類',
            'non_liquor' => '酒類対象外',
            'unresolved_liquor' => '酒税区分未解決',
            default => $name ?: ($code ?: '-'),
        };
    }

    private function relativePath(LiquorTaxMonthlyFiling $filing, string $format): string
    {
        return 'reports/liquor-tax-filings/'.$filing->year.'/liquor-tax-filing-'.$filing->period_start->format('Ym').'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(8)).'.'.$format;
    }
}
