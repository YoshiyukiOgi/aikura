<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireWebPermission;
use App\Http\Middleware\InjectSidebar;
use App\Http\Middleware\DisableBrowserCache;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AssignRequestId::class,
            InjectSidebar::class,
            DisableBrowserCache::class,
        ]);
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
        ]);
        $middleware->api(append: [
            AssignRequestId::class,
        ]);
        $middleware->alias([
            'permission' => RequirePermission::class,
            'web.permission' => RequireWebPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\DomainException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'business_rule_violation',
                    'message' => $exception->getMessage(),
                ],
            ], 409);
        });
    })
    ->create();
