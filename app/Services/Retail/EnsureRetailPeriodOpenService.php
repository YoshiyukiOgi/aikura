<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailMonthlyClosing;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class EnsureRetailPeriodOpenService
{
    public function handle(int $companyId, CarbonInterface $businessDate): void
    {
        $period = $businessDate->copy()->startOfMonth()->toDateString();

        $closed = RetailMonthlyClosing::query()
            ->where('retail_company_id', $companyId)
            ->whereDate('period', $period)
            ->where('status', RetailMonthlyClosing::STATUS_CLOSED)
            ->exists();

        if ($closed) {
            throw ValidationException::withMessages([
                'business_date' => [sprintf('%s は月次締め済みのため、取引を追加・変更できません。', $period)],
            ]);
        }
    }
}
