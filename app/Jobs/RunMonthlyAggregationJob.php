<?php

namespace App\Jobs;

use App\Exceptions\Operations\OperationJobException;
use App\Services\Billing\CreateReceivableMonthlyBalanceDraftService;
use App\Services\Inventory\CreateStockMonthlyBalanceDraftService;
use App\Services\Operations\OperationJobService;
use App\Services\Tax\CreateConsumptionTaxMonthlyFilingDraftService;
use App\Services\Tax\CreateLiquorTaxMonthlyFilingDraftService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunMonthlyAggregationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $aggregationType,
        private readonly int $year,
        private readonly int $month,
        private readonly ?string $reason = null,
    ) {}

    public function handle(
        OperationJobService $operationJobService,
        CreateLiquorTaxMonthlyFilingDraftService $liquorTaxDraftService,
        CreateConsumptionTaxMonthlyFilingDraftService $consumptionTaxDraftService,
        CreateReceivableMonthlyBalanceDraftService $receivableBalanceDraftService,
        CreateStockMonthlyBalanceDraftService $stockBalanceDraftService,
    ): mixed {
        return $operationJobService->run(
            jobType: 'monthly_aggregation.create',
            targetType: $this->aggregationType,
            targetId: $this->period(),
            payload: [
                'aggregation_type' => $this->aggregationType,
                'year' => $this->year,
                'month' => $this->month,
            ],
            reason: $this->reason,
            callback: fn (): mixed => match ($this->aggregationType) {
                'liquor_tax_filing' => $liquorTaxDraftService->create($this->year, $this->month, $this->reason),
                'consumption_tax_filing' => $consumptionTaxDraftService->create($this->year, $this->month, $this->reason),
                'receivable_monthly_balance' => $receivableBalanceDraftService->create($this->year, $this->month, $this->reason),
                'stock_lot_monthly_balance' => $stockBalanceDraftService->create($this->year, $this->month, $this->reason),
                default => throw OperationJobException::unsupportedAggregationType($this->aggregationType),
            },
        );
    }

    private function period(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
