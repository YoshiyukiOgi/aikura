<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Api\ApiRouteCatalogService;
use Illuminate\Http\JsonResponse;

class SystemController extends ApiController
{
    public function __construct(
        private readonly ApiRouteCatalogService $apiRouteCatalogService,
    ) {
    }

    public function index(): JsonResponse
    {
        return $this->ok([
            'name' => 'aikura',
            'status' => 'ok',
            'version' => 'v1',
        ]);
    }

    public function routes(): JsonResponse
    {
        return $this->ok([
            'routes' => $this->apiRouteCatalogService->catalog(),
        ]);
    }
}
