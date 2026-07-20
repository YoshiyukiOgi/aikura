<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PriceReviewTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceReviewTaskController extends ApiController
{
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
