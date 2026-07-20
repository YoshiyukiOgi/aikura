<?php

namespace App\Services\Reports;

use App\Exceptions\Reports\ReportExportRetentionException;
use App\Models\ReportExport;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class ReportExportRetentionService
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    public function verifyFile(ReportExport $export): ReportExportFileVerification
    {
        if ($export->disk !== 'local') {
            throw ReportExportRetentionException::unsupportedDisk($export->disk);
        }

        $path = storage_path('app/'.$export->file_path);

        if (! $this->filesystem->exists($path)) {
            throw ReportExportRetentionException::missingFile($export->file_path);
        }

        $content = $this->filesystem->get($path);
        $fileSize = strlen($content);

        if ($fileSize !== $export->file_size) {
            throw ReportExportRetentionException::fileSizeMismatch($export->file_path);
        }

        $checksum = hash('sha256', $content);

        if ($checksum !== $export->checksum_sha256) {
            throw ReportExportRetentionException::checksumMismatch($export->file_path);
        }

        return new ReportExportFileVerification(
            verified: true,
            fileSize: $fileSize,
            checksumSha256: $checksum,
        );
    }

    /**
     * @return Collection<int, ReportExport>
     */
    public function history(
        string $reportType,
        ?string $exportableType = null,
        ?int $exportableId = null,
    ): Collection {
        return ReportExport::query()
            ->where('report_type', $reportType)
            ->when($exportableType !== null, fn ($query) => $query->where('exportable_type', $exportableType))
            ->when($exportableId !== null, fn ($query) => $query->where('exportable_id', $exportableId))
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->get();
    }
}
