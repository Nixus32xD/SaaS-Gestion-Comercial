<?php

namespace App\Http\Middleware;

use App\Support\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $business = app(CurrentBusiness::class)->get();

        if ($business === null || ! $business->hasFeature($feature)) {
            abort(403, 'La funcionalidad no esta habilitada para este comercio.');
        }

        return $next($request);
    }
}
