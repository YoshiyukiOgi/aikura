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
            $filing = LiquorTaxMonthlyFiling::query()->with(['lines.sources', 'adjustments.category', 'reportExports'])->lockForUpdate()->findOrFail($filing->id);
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
        $sheet->setCellValue('A1', '酒税納税申告 e-Tax転記確認表')->mergeCells('A1:H1');
        $sheet->setCellValue('A3', '対象期間')->setCellValue('B3', $filing->period_start->format('Y/m/d').' - '.$filing->period_end->format('Y/m/d'));
        $sheet->setCellValue('E3', '製造場')->setCellValue('F3', $filing->manufacturing_site_code);
        $sheet->setCellValue('A5', '申告項目')->setCellValue('D5', 'システム確定値')->mergeCells('A5:C5')->mergeCells('D5:H5');
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
            $sheet->setCellValue("D{$row}", $value)->mergeCells("D{$row}:H{$row}");
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($index === 0 ? '0.000000' : '#,##0');
        }

        $row = 14;
        $headers = ['酒類区分', '取扱区分', '課税数量(kl)', '税率(円/kl)', '軽減前税額', '軽減税額', '控除税額', '計算後税額'];
        foreach ($headers as $column => $header) {
            $sheet->setCellValue([$column + 1, $row], $header);
        }
        foreach ($filing->lines as $line) {
            $row++;
            $sheet->fromArray([$line->liquor_tax_category_name, $line->tax_treatment, (float) $line->taxable_kl, (float) ($line->tax_per_kl ?? 0), (float) $line->gross_tax_amount, (float) $line->relief_amount, (float) $line->deduction_amount, (float) $line->net_tax_amount], null, "A{$row}");
        }
        $row += 3;
        $sheet->setCellValue("A{$row}", '調整明細')->mergeCells("A{$row}:H{$row}");
        $row++;
        $adjustmentHeaders = ['No.', '区分', '酒類区分', '数量調整(kl)', '税額調整', '内容', '承認要否', '状態'];
        foreach ($adjustmentHeaders as $column => $header) {
            $sheet->setCellValue([$column + 1, $row], $header);
        }
        foreach ($filing->adjustments->where('status', 'active') as $adjustment) {
            $row++;
            $sheet->fromArray([$adjustment->line_no, $adjustment->adjustment_type, $adjustment->category?->name, (float) $adjustment->taxable_kl_adjustment, (float) $adjustment->tax_amount_adjustment, $adjustment->description, $adjustment->approval_required ? '必要' : '不要', $adjustment->status], null, "A{$row}");
        }

        $sourceSheet = $book->createSheet();
        $sourceSheet->setTitle('根拠明細');
        $sourceHeaders = ['日付', '伝票番号', '元データ', '酒類区分', '取扱区分', '数量', '課税数量(kl)', '軽減前税額', '証明状態', '証明番号', '要確認理由'];
        foreach ($sourceHeaders as $column => $header) {
            $sourceSheet->setCellValue([$column + 1, 1], $header);
        }
        $sourceRow = 1;
        foreach ($filing->lines as $line) {
            foreach ($line->sources as $source) {
                $sourceRow++;
                $sourceSheet->fromArray([$source->source_date?->format('Y/m/d'), $source->source_document_number, $source->source_type, $line->liquor_tax_category_name, $source->tax_treatment, (float) $source->quantity, (float) $source->taxable_kl, (float) $source->gross_tax_amount, $source->evidence_status, $source->evidence_reference, $source->review_reason], null, "A{$sourceRow}");
            }
        }

        $basisSheet = $book->createSheet();
        $basisSheet->setTitle('軽減計算根拠');
        $basisHeaders = ['No.', '方式', '酒類区分', '取扱区分', '累計前', '累計後', '率区分', '税額帯', '帯内税額', '軽減率', '帯別軽減額(丸め前)', '行軽減額', '逆算軽減額', '控除額', '丸め'];
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
                    $line->line_no, $basis['scheme'] ?? null, $line->liquor_tax_category_name, $line->tax_treatment,
                    $line->cumulative_gross_before === null ? null : (float) $line->cumulative_gross_before,
                    $line->cumulative_gross_after === null ? null : (float) $line->cumulative_gross_after,
                    $basis['rate_column'] ?? null, $segment['band'] ?? null, (float) ($segment['tax_base_amount'] ?? 0),
                    (float) ($segment['rate'] ?? 0), (float) ($segment['relief_amount_unrounded'] ?? 0),
                    (float) $line->relief_amount, (float) ($basis['reversed_relief_amount'] ?? 0),
                    (float) $line->deduction_amount, $basis['rounding'] ?? null,
                ], null, "A{$basisRow}");
            }
        }

        foreach ($book->getAllSheets() as $workSheet) {
            $maxColumn = match ($workSheet->getTitle()) {
                'e-Tax転記確認表' => 'H',
                '根拠明細' => 'K',
                default => 'O',
            };
            $workSheet->getStyle("A1:{$maxColumn}".$workSheet->getHighestRow())->getFont()->setName('Noto Sans CJK JP')->setSize(10);
            $workSheet->getStyle("A1:{$maxColumn}1")->getFont()->setBold(true);
            $workSheet->getStyle("A1:{$maxColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F1');
            $workSheet->getStyle("A1:{$maxColumn}".$workSheet->getHighestRow())->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $workSheet->getStyle("A1:{$maxColumn}".$workSheet->getHighestRow())->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('B8C2CC');
            foreach (range('A', $maxColumn) as $column) {
                $workSheet->getColumnDimension($column)->setWidth(in_array($column, ['A', 'F', 'I'], true) ? 24 : 15);
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

    private function relativePath(LiquorTaxMonthlyFiling $filing, string $format): string
    {
        return 'reports/liquor-tax-filings/'.$filing->year.'/liquor-tax-filing-'.$filing->period_start->format('Ym').'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(8)).'.'.$format;
    }
}
