<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends ApiController
{
    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->ok([
            'search' => $this->searchService->crossSearch(
                $request->query('q'),
                (int) $request->query('limit', 20),
            ),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        return $this->ok([
            'search' => $this->searchService->transactions($request->query()),
        ]);
    }

    public function statuses(Request $request): JsonResponse
    {
        return $this->ok([
            'search' => $this->searchService->statuses($request->query()),
        ]);
    }

    public function relations(Request $request): JsonResponse
    {
        return $this->ok([
            'search' => $this->searchService->relations($request->query()),
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        return $this->ok([
            'search' => $this->searchService->pending($request->query()),
        ]);
    }

    public function reviewRequired(Request $request): JsonResponse
    {
        return $this->ok([
            'search' => $this->searchService->reviewRequired($request->query()),
        ]);
    }

    public function closingTargets(Request $request): JsonResponse
    {
        return $this->ok([
            'search' => $this->searchService->closingTargets($request->query()),
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return $this->ok([
            'search' => $this->searchService->auditLogs($request->query()),
        ]);
    }
}
