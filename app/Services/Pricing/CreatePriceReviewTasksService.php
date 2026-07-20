<?php

namespace App\Services\Pricing;

use App\Models\PriceReviewTask;
use App\Models\PriceRule;
use Illuminate\Database\Eloquent\Builder;

class CreatePriceReviewTasksService
{
    public function createForChangedRule(
        PriceRule $changedRule,
        string $oldUnitPrice,
        string $newUnitPrice,
        ?int $createdByUserId = null,
        ?string $reason = null,
    ): int {
        if (bccomp($oldUnitPrice, $newUnitPrice, 4) === 0) {
            return 0;
        }

        if ($changedRule->customer_id !== null) {
            return 0;
        }

        $changedRule->loadMissing(['product', 'transactionCategory', 'priceList']);

        $query = PriceRule::query()
            ->with(['customer', 'transactionCategory'])
            ->where('id', '!=', $changedRule->id)
            ->where('product_id', $changedRule->product_id)
            ->where('unit_id', $changedRule->unit_id)
            ->whereNotNull('customer_id')
            ->where('is_active', true);

        if ($changedRule->transaction_category_id !== null) {
            $transactionCategoryId = $changedRule->transaction_category_id;
            $query->whereHas('customer', function (Builder $query) use ($transactionCategoryId): void {
                $query->where('transaction_category_id', $transactionCategoryId);
            });
        }

        $created = 0;

        $query->chunkById(100, function ($rules) use ($changedRule, $oldUnitPrice, $newUnitPrice, $createdByUserId, $reason, &$created): void {
            foreach ($rules as $affectedRule) {
                $task = PriceReviewTask::updateOrCreate(
                    [
                        'changed_price_rule_id' => $changedRule->id,
                        'affected_price_rule_id' => $affectedRule->id,
                    ],
                    [
                        'product_id' => $changedRule->product_id,
                        'customer_id' => $affectedRule->customer_id,
                        'transaction_category_id' => $changedRule->transaction_category_id,
                        'old_reference_price' => $oldUnitPrice,
                        'new_reference_price' => $newUnitPrice,
                        'current_individual_price' => $affectedRule->unit_price,
                        'status' => PriceReviewTask::STATUS_PENDING,
                        'message' => $this->message($changedRule, $affectedRule, $oldUnitPrice, $newUnitPrice),
                        'reason' => $reason,
                        'created_by_user_id' => $createdByUserId,
                        'reviewed_by_user_id' => null,
                        'reviewed_at' => null,
                    ],
                );

                if ($task->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        return $created;
    }

    private function message(PriceRule $changedRule, PriceRule $affectedRule, string $oldUnitPrice, string $newUnitPrice): string
    {
        $productName = $changedRule->product?->display_name ?: $changedRule->product?->name ?: "商品ID {$changedRule->product_id}";
        $customerName = $affectedRule->customer?->name ?: "取引先ID {$affectedRule->customer_id}";
        $categoryName = $changedRule->transactionCategory?->name ?: $changedRule->priceList?->name ?: '価格';

        return "{$productName} の {$categoryName} が {$oldUnitPrice} から {$newUnitPrice} に変更されました。{$customerName} の個別価格を見直してください。";
    }
}
