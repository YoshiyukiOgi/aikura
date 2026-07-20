<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Services\Operations\OperationJobService;
use App\Services\Reports\ReportExportRetentionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunReportExportRetentionCheckJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly ?string $reportType = null,
        private readonly ?string $reason = null,
    ) {
    }

    public function handle(
        OperationJobService $operationJobService,
        ReportExportRetentionService $retentionService,
    ): array {
        return $operationJobService->run(
            jobType: 'report_exports.retention_check',
            targetType: $this->reportType ?? 'all',
            targetId: $this->reportType ?? 'all',
            payload: [
                'report_type' => $this->reportType,
            ],
            reason: $this->reason,
            callback: function () use ($retentionService): array {
                $exports = ReportExport::query()
                    ->when($this->reportType !== null, fn ($query) => $query->where('report_type', $this->reportType))
                    ->orderBy('id')
                    ->get();

                foreach ($exports as $export) {
                    $retentionService->verifyFile($export);
                }

                return [
                    'checked_count' => $exports->count(),
                ];
            },
        );
    }
}
