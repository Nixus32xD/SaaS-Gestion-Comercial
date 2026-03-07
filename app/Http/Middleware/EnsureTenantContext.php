<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
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

        $activeMemberships = $user->memberships()
            ->with(['tenant', 'defaultBranch'])
            ->where('status', 'active');

        $tenantId = $request->session()->get('tenant_id');

        $membership = $tenantId
            ? (clone $activeMemberships)->where('tenant_id', $tenantId)->first()
            : null;

        if ($membership === null) {
            $membership = (clone $activeMemberships)
                ->orderByDesc('is_owner')
                ->oldest('id')
                ->first();
        }

        if ($membership === null || $membership->tenant === null) {
            abort(403, 'No tienes un comercio activo asociado.');
        }

        $tenant = $membership->tenant;
        $branchId = $request->session()->get('branch_id');

        $branch = $branchId
            ? $tenant->branches()->whereKey($branchId)->first()
            : null;

        if ($branch === null) {
            $branch = $membership->defaultBranch
                ?? $tenant->branches()->where('is_main', true)->first()
                ?? $tenant->branches()->oldest('id')->first();
        }

        $request->session()->put('tenant_id', $tenant->id);
        $request->session()->put('branch_id', $branch?->id);

        $currentTenant = app(CurrentTenant::class);
        $currentTenant->setTenant($tenant);
        $currentTenant->setBranch($branch);
        $currentTenant->setMembership($membership);

        return $next($request);
    }
}
