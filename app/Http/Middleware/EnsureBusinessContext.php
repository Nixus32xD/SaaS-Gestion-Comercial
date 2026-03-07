<?php

namespace App\Http\Middleware;

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

        if (! $user->isBusinessAdmin()) {
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

        $request->session()->put('business_id', $business->id);
        app(CurrentBusiness::class)->set($business);

        return $next($request);
    }
}

