<?php

namespace App\Services\Billing;

use App\Models\InternalMonthlyBalance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CloseInternalMonthlyBalanceService
{
    /** @return Collection<int, InternalMonthlyBalance> */
    public function close(int $year, int $month, string $reason): Collection
    {
        if (trim($reason) === '') { throw new \RuntimeException('社内残高の締め理由を入力してください。'); }
        return DB::transaction(function () use ($year, $month, $reason): Collection {
            $rows = InternalMonthlyBalance::query()->where('year', $year)->where('month', $month)->lockForUpdate()->get();
            if ($rows->isEmpty() || $rows->contains(fn (InternalMonthlyBalance $row): bool => $row->status !== 'confirmed')) {
                throw new \RuntimeException("{$year}年{$month}月の社内残高を締められません。");
            }
            foreach ($rows as $row) { $row->forceFill(['status' => 'closed', 'closed_at' => now(), 'reason' => $reason])->save(); }
            return $rows->fresh()->sortBy('customer_code')->values();
        });
    }
}
