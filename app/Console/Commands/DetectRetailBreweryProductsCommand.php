<?php

namespace App\Console\Commands;

use App\Models\Retail\RetailPriceSyncSetting;
use App\Services\Retail\DetectRetailBreweryProductChangesService;
use Illuminate\Console\Command;

class DetectRetailBreweryProductsCommand extends Command
{
    protected $signature = 'retail:detect-brewery-products {--force}';

    protected $description = '設定時刻に蔵商品情報と価格の変更を検知する';

    public function handle(DetectRetailBreweryProductChangesService $service): int
    {
        $setting = RetailPriceSyncSetting::query()->firstOrCreate([], [
            'detection_mode' => 'manual',
            'interval_minutes' => 60,
        ]);

        if (! $this->option('force') && ! $setting->isDue()) {
            return self::SUCCESS;
        }

        $summary = $service->detect();
        $this->info(json_encode($summary, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
