<?php

namespace App\Http\Middleware;

use App\Exceptions\Authorization\PermissionDeniedException;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string $permissionCode): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Authentication is required.',
            ], 401);
        }

        try {
            $this->authorizationService->assertCan($user, $permissionCode);
        } catch (PermissionDeniedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'permission' => $permissionCode,
            ], 403);
        }

        return $next($request);
    }
}
