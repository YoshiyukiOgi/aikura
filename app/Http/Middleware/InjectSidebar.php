<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectSidebar
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if ($request->routeIs('retail.*', 'shipment-instructions.work-slip', 'shipments.print', 'billing.invoices.print', 'billing.invoices.print-batch', 'inventory.lot-stock-as-of.print', 'inventory.movements.print')) {
            return $response;
        }

        if ($request->is('concept/retail*')) {
            return $response;
        }

        if (! $user instanceof User || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || str_contains($content, 'app-sidebar')) {
            return $response;
        }

        $sidebar = view('components.sidebar')->render();
        $response->setContent(str_ireplace('</body>', $sidebar.'</body>', $content));

        return $response;
    }
}
