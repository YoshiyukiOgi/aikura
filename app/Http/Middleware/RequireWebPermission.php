<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireWebPermission
{
    public function __construct(private readonly AuthorizationService $authorizationService)
    {
    }

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $permissionCode): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->to('/login');
        }

        if (! $this->authorizationService->can($user, $permissionCode)) {
            abort(403, 'この操作を行う権限がありません。');
        }

        return $next($request);
    }
}
