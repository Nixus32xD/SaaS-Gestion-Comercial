<?php

namespace App\Http\Middleware;

use App\Services\BusinessBillingService;
use App\Support\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->isBusinessUser()) {
            abort(403, 'No tienes permisos para acceder a este modulo.');
        }

        if (! $user->is_active) {
            abort(403, 'Tu usuario esta inactivo.');
        }

        $business = $user->business;

        if ($business === null) {
            abort(403, 'No tienes un comercio asignado.');
        }

        if (! $business->is_active) {
            abort(403, 'El comercio esta inactivo.');
        }

        if (app(BusinessBillingService::class)->shouldBlockBusinessAccess($business)) {
            abort(403, 'El abono del comercio vencio y supero la gracia disponible. Contacta al administrador para reactivarlo.');
        }

        $request->session()->put('business_id', $business->id);
        app(CurrentBusiness::class)->set($business);

        return $next($request);
    }
}
