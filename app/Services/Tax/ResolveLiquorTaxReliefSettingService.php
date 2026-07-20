<?php

namespace App\Services\Tax;

use App\Models\LiquorTaxReliefSetting;
use DomainException;

class ResolveLiquorTaxReliefSettingService
{
    public function resolve(string $siteCode, string $date): LiquorTaxReliefSetting
    {
        $setting = LiquorTaxReliefSetting::query()
            ->where('manufacturing_site_code', $siteCode)->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->first();

        if ($setting === null) {
            throw new DomainException("酒税軽減方式が設定されていません: {$siteCode} / {$date}");
        }

        return $setting;
    }
}
