<?php

namespace App\Jobs;

use App\Exceptions\Operations\OperationJobException;
use App\Services\Billing\CloseReceivableMonthlyBalanceService;
use App\Services\Billing\CloseInternalMonthlyBalanceService;
use App\Services\Billing\ConfirmReceivableMonthlyBalanceService;
use App\Services\Billing\ConfirmInternalMonthlyBalanceService;
use App\Services\Inventory\ConfirmStockMonthlyBalanceService;
use App\Services\Operations\OperationJobService;
use App\Services\Tax\ConfirmConsumptionTaxMonthlyFilingService;
use App\Services\Tax\ConfirmLiquorTaxMonthlyFilingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunMonthlyClosingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $closingType,
        private readonly int $year,
        private readonly int $month,
        private readonly string $reason,
    ) {}

    public function handle(
        OperationJobService $operationJobService,
        ConfirmStockMonthlyBalanceService $stockConfirmService,
        ConfirmReceivableMonthlyBalanceService $receivableConfirmService,
        CloseReceivableMonthlyBalanceService $receivableCloseService,
        ConfirmInternalMonthlyBalanceService $internalConfirmService,
        CloseInternalMonthlyBalanceService $internalCloseService,
        ConfirmLiquorTaxMonthlyFilingService $liquorTaxConfirmService,
        ConfirmConsumptionTaxMonthlyFilingService $consumptionTaxConfirmService,
    ): mixed {
        return $operationJobService->run(
            jobType: 'monthly_closing.execute',
            targetType: $this->closingType,
            targetId: $this->period(),
            payload: [
                'closing_type' => $this->closingType,
                'year' => $this->year,
                'month' => $this->month,
            ],
            reason: $this->reason,
            callback: fn (): mixed => match ($this->closingType) {
                'stock_lot_monthly_balance_confirm' => $stockConfirmService->confirm($this->year, $this->month, $this->reason),
                'receivable_monthly_balance_confirm' => $receivableConfirmService->confirm($this->year, $this->month, $this->reason),
                'receivable_monthly_balance_close' => $receivableCloseService->close($this->year, $this->month, $this->reason),
                'internal_monthly_balance_confirm' => $internalConfirmService->confirm($this->year, $this->month, $this->reason),
                'internal_monthly_balance_close' => $internalCloseService->close($this->year, $this->month, $this->reason),
                'liquor_tax_filing_confirm' => $liquorTaxConfirmService->confirm($this->year, $this->month, $this->reason),
                'consumption_tax_filing_confirm' => $consumptionTaxConfirmService->confirm($this->year, $this->month, $this->reason),
                default => throw OperationJobException::unsupportedClosingType($this->closingType),
            },
        );
    }

    private function period(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
