<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class ApiController extends Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function ok(array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function created(array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
        ], 201);
    }
}
