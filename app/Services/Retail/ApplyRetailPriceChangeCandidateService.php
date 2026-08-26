<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailPriceChangeCandidate;
use App\Models\Retail\RetailPriceHistory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplyRetailPriceChangeCandidateService
{
    public function apply(RetailPriceChangeCandidate $candidate, string $mode): void
    {
        if (! in_array($mode, ['cost_only', 'cost_and_selling'], true)) {
            throw new InvalidArgumentException('価格反映モードが不正です。');
        }

        DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($candidate, $mode): void {
            $candidate = RetailPriceChangeCandidate::query()
                ->whereKey($candidate->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->firstOrFail();

            $product = $candidate->product()->lockForUpdate()->firstOrFail();

            $oldCost = $product->cost_price;
            $oldSelling = $product->selling_price;
            $newCost = $candidate->source_cost_price;
            $newSelling = $mode === 'cost_and_selling'
                ? $candidate->source_selling_price
                : $product->selling_price;

            $product->forceFill([
                'cost_price' => $newCost,
                'selling_price' => $newSelling,
            ])->save();

            RetailPriceHistory::query()->create([
                'retail_product_id' => $product->id,
                'retail_price_change_candidate_id' => $candidate->id,
                'old_cost_price' => $oldCost,
                'new_cost_price' => $newCost,
                'old_selling_price' => $oldSelling,
                'new_selling_price' => $newSelling,
                'apply_mode' => $mode,
                'applied_at' => now(),
            ]);

            $candidate->forceFill([
                'status' => 'applied',
                'applied_at' => now(),
                'applied_mode' => $mode,
            ])->save();
        });
    }
}
