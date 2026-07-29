<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PriceReviewTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceReviewTaskController extends ApiController
{
    public function notifyForSelection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $priceReviewTask = PriceReviewTask::query()
            ->where('status', PriceReviewTask::STATUS_PENDING)
            ->where('customer_id', $validated['customer_id'])
            ->where('product_id', $validated['product_id'])
            ->whereNull('notified_at')
            ->orderByDesc('id')
            ->first();

        if (! $priceReviewTask) {
            return $this->ok([
                'price_review_task' => null,
            ]);
        }

        $priceReviewTask->update([
            'notified_by_user_id' => $request->user()?->id,
            'notified_at' => now(),
        ]);

        return $this->ok([
            'price_review_task' => [
                'id' => $priceReviewTask->id,
                'message' => $priceReviewTask->message,
                'old_reference_price' => $priceReviewTask->old_reference_price,
                'new_reference_price' => $priceReviewTask->new_reference_price,
                'current_individual_price' => $priceReviewTask->current_individual_price,
                'status' => $priceReviewTask->status,
                'notified_at' => $priceReviewTask->notified_at?->toISOString(),
            ],
        ]);
    }

    public function keep(Request $request, PriceReviewTask $priceReviewTask): JsonResponse
    {
        return $this->mark($request, $priceReviewTask, PriceReviewTask::STATUS_KEPT);
    }

    public function updated(Request $request, PriceReviewTask $priceReviewTask): JsonResponse
    {
        return $this->mark($request, $priceReviewTask, PriceReviewTask::STATUS_UPDATED);
    }

    private function mark(Request $request, PriceReviewTask $priceReviewTask, string $status): JsonResponse
    {
        $priceReviewTask->update([
            'status' => $status,
            'reason' => $request->string('reason')->toString() ?: $priceReviewTask->reason,
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return $this->ok([
            'price_review_task' => [
                'id' => $priceReviewTask->id,
                'status' => $priceReviewTask->status,
                'reviewed_at' => $priceReviewTask->reviewed_at?->toISOString(),
            ],
        ]);
    }
}
