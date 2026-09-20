<?php

namespace App\Services\Billing;

use App\Models\InternalMonthlyBalance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConfirmInternalMonthlyBalanceService
{
    /** @return Collection<int, InternalMonthlyBalance> */
    public function confirm(int $year, int $month, string $reason): Collection
    {
        return $this->transition($year, $month, $reason, 'draft', 'confirmed', 'confirmed_at');
    }

    /** @return Collection<int, InternalMonthlyBalance> */
    private function transition(int $year, int $month, string $reason, string $from, string $to, string $timestamp): Collection
    {
        if (trim($reason) === '') {
            throw new \RuntimeException('社内残高の確定理由を入力してください。');
        }
        return DB::transaction(function () use ($year, $month, $reason, $from, $to, $timestamp): Collection {
            $rows = InternalMonthlyBalance::query()->where('year', $year)->where('month', $month)->lockForUpdate()->get();
            if ($rows->isEmpty() || $rows->contains(fn (InternalMonthlyBalance $row): bool => $row->status !== $from)) {
                throw new \RuntimeException("{$year}年{$month}月の社内残高を{$to}できません。");
            }
            foreach ($rows as $row) { $row->forceFill(['status' => $to, $timestamp => now(), 'reason' => $reason])->save(); }
            return $rows->fresh()->sortBy('customer_code')->values();
        });
    }
}
