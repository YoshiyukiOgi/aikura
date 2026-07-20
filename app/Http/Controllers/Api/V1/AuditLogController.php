<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;

class AuditLogController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->ok([
            'total' => AuditLog::query()->count(),
        ]);
    }
}
