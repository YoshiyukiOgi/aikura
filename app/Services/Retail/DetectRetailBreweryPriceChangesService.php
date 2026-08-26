<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailPriceChangeCandidate;
use App\Models\Retail\RetailPriceSyncSetting;
use App\Models\Retail\RetailProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DetectRetailBreweryPriceChangesService
{
    public function __construct(private readonly RetailBreweryProductPriceResolver $priceResolver) {}

    public function detect(): Collection
    {
        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function (): Collection {
            $created = collect();

            $products = RetailProduct::query()
                ->where('procurement_source', 'brewery')
                ->whereNotNull('brewery_product_id')
                ->where('is_active', true)
                ->whereIn('brewery_source_status', ['current', 'changed'])
                ->get();

            foreach ($products as $retailProduct) {
                $prices = $this->priceResolver->resolve((int) $retailProduct->brewery_product_id);
                $costPrice = $prices['has_cost_price'] ? $prices['cost_price'] : null;
                $sellingPrice = $prices['has_selling_price'] ? $prices['selling_price'] : null;

                if ($costPrice === null && $sellingPrice === null) {
                    continue;
                }

                $sourceCost = $costPrice ?? (float) $retailProduct->cost_price;
                $sourceSelling = $sellingPrice ?? (float) $retailProduct->selling_price;

                if ($this->samePrice((float) $retailProduct->cost_price, $sourceCost)
                    && $this->samePrice((float) $retailProduct->selling_price, $sourceSelling)) {
                    continue;
                }

                $candidate = RetailPriceChangeCandidate::query()->updateOrCreate(
                    [
                        'retail_product_id' => $retailProduct->id,
                        'status' => 'open',
                    ],
                    [
                        'brewery_product_id' => $retailProduct->brewery_product_id,
                        'current_cost_price' => $retailProduct->cost_price,
                        'source_cost_price' => $sourceCost,
                        'current_selling_price' => $retailProduct->selling_price,
                        'source_selling_price' => $sourceSelling,
                        'detected_at' => now(),
                    ],
                );

                $created->push($candidate);
            }

            RetailPriceSyncSetting::query()->firstOrCreate([], ['detection_mode' => 'manual'])
                ->forceFill(['last_detected_at' => now()])
                ->save();

            return $created;
        });
    }

    private function samePrice(float $left, float $right): bool
    {
        return abs(round($left, 2) - round($right, 2)) < 0.01;
    }
}
