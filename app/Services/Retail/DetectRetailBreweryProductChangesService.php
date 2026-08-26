<?php

namespace App\Services\Retail;

use App\Models\Product;
use App\Models\Retail\RetailPriceSyncSetting;
use App\Models\Retail\RetailProduct;
use Throwable;

class DetectRetailBreweryProductChangesService
{
    public function __construct(
        private readonly DetectRetailBreweryPriceChangesService $priceChanges,
        private readonly RetailBreweryProductPriceResolver $priceResolver,
    ) {}

    /**
     * @return array{new:int,changed:int,price:int,inactive:int,deleted:int}
     */
    public function detect(): array
    {
        $setting = RetailPriceSyncSetting::query()->firstOrCreate([], [
            'detection_mode' => 'manual',
            'interval_minutes' => 60,
        ]);

        try {
            $retailProducts = RetailProduct::query()
                ->where('procurement_source', 'brewery')
                ->whereNotNull('brewery_product_id')
                ->get();
            $sourceProducts = Product::query()
                ->with(['salesUnit:id,name,symbol', 'consumptionTaxCategory'])
                ->whereIn('id', $retailProducts->pluck('brewery_product_id')->all())
                ->get()
                ->keyBy('id');

            $summary = [
                'new' => Product::query()
                    ->where('is_active', true)
                    ->where('is_sales_available', true)
                    ->whereNotIn('id', $retailProducts->pluck('brewery_product_id')->all() ?: [0])
                    ->count(),
                'changed' => 0,
                'price' => 0,
                'inactive' => 0,
                'deleted' => 0,
            ];

            foreach ($retailProducts as $retailProduct) {
                $source = $sourceProducts->get($retailProduct->brewery_product_id);
                $status = 'current';

                if (! $source) {
                    $status = 'deleted';
                    $summary['deleted']++;
                } elseif (! $source->is_active || ! $source->is_sales_available) {
                    $status = 'inactive';
                    $summary['inactive']++;
                } elseif ($this->basicInformationChanged($retailProduct, $source)) {
                    $status = 'changed';
                    $summary['changed']++;
                }

                $retailProduct->forceFill([
                    'brewery_source_status' => $status,
                    'brewery_source_checked_at' => now(),
                ])->save();
            }

            $summary['price'] = $this->priceChanges->detect()->count();
            $setting->refresh()->forceFill([
                'last_detection_summary' => $summary,
                'last_detection_error' => null,
            ])->save();

            return $summary;
        } catch (Throwable $exception) {
            $setting->forceFill([
                'last_detected_at' => now(),
                'last_detection_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function basicInformationChanged(RetailProduct $retailProduct, Product $source): bool
    {
        $prices = $this->priceResolver->resolve($source);
        $unit = $source->salesUnit?->symbol ?: $source->salesUnit?->name ?: '本';

        return $retailProduct->product_code !== 'BR-'.$source->product_code
            || $retailProduct->name !== ($source->display_name ?: $source->name)
            || ($retailProduct->name_kana ?? '') !== ($source->name_kana ?? '')
            || $retailProduct->stock_unit !== $unit
            || abs((float) $retailProduct->tax_rate - $prices['tax_rate']) >= 0.0001;
    }
}
