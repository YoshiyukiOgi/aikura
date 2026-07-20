<?php

namespace App\Services\Inventory;

use App\Models\AppSetting;

class LotVisibilityPolicy
{
    public function hideZeroStockLots(): bool
    {
        $settings = AppSetting::values(['hide_zero_stock_lots' => '1']);

        return $settings['hide_zero_stock_lots'] === '1';
    }

    public function includeZeroStock(bool $requested = false): bool
    {
        return $requested || ! $this->hideZeroStockLots();
    }

    public function shouldDisplayBalance(LotStockBalance $balance, bool $includeZeroStock): bool
    {
        if ($includeZeroStock) {
            return true;
        }

        return bccomp($balance->physicalQuantity, '0.0000', 4) !== 0
            || bccomp($balance->availableQuantity, '0.0000', 4) < 0;
    }
}
