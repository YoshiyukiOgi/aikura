<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UpdatePriceRuleRequest;
use App\Models\PriceRule;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Pricing\CreatePriceReviewTasksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PriceRuleController extends ApiController
{
    public function update(
        UpdatePriceRuleRequest $request,
        PriceRule $priceRule,
        AuditLogService $auditLogService,
        CreatePriceReviewTasksService $createPriceReviewTasksService,
    ): JsonResponse
    {
        $validated = $request->validated();
        $createdReviewTaskCount = 0;

        $priceRule = DB::transaction(function () use ($request, $priceRule, $validated, $auditLogService, $createPriceReviewTasksService, &$createdReviewTaskCount): PriceRule {
            $priceRule = PriceRule::query()->lockForUpdate()->findOrFail($priceRule->id);
            $before = $priceRule->only(['unit_price', 'effective_from', 'effective_to', 'priority', 'is_active']);
            $oldUnitPrice = (string) $priceRule->unit_price;

            $priceRule->update([
                'unit_price' => $validated['unit_price'],
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
                'priority' => $validated['priority'] ?? $priceRule->priority,
                'is_active' => $validated['is_active'] ?? $priceRule->is_active,
                'reason' => $validated['reason'],
            ]);

            $auditLogService->record(new AuditLogData(
                event: 'price_rule.updated',
                auditable: $priceRule,
                beforeValues: $before,
                afterValues: $priceRule->only(['unit_price', 'effective_from', 'effective_to', 'priority', 'is_active']),
                reason: $validated['reason'],
            ));

            $createdReviewTaskCount = $createPriceReviewTasksService->createForChangedRule(
                changedRule: $priceRule,
                oldUnitPrice: $oldUnitPrice,
                newUnitPrice: (string) $priceRule->unit_price,
                createdByUserId: $request->user()?->id,
                reason: $validated['reason'],
            );

            return $priceRule;
        });

        return $this->ok([
            'price_rule' => $this->serialize($priceRule),
            'created_price_review_task_count' => $createdReviewTaskCount,
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(PriceRule $priceRule): array
    {
        return [
            'id' => $priceRule->id,
            'price_list_id' => $priceRule->price_list_id,
            'product_id' => $priceRule->product_id,
            'customer_id' => $priceRule->customer_id,
            'unit_id' => $priceRule->unit_id,
            'unit_price' => $priceRule->unit_price,
            'priority' => $priceRule->priority,
            'effective_from' => $priceRule->effective_from?->toDateString(),
            'effective_to' => $priceRule->effective_to?->toDateString(),
            'is_active' => $priceRule->is_active,
            'reason' => $priceRule->reason,
        ];
    }
}
