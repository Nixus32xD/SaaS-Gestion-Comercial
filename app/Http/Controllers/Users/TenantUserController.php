<?php

namespace App\Http\Controllers\Users;

use App\Domain\Tenancy\Models\TenantMembership;
use App\Domain\Tenancy\Support\CurrentTenant;
use App\Domain\Users\Services\TenantUserManagementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreTenantUserRequest;
use App\Http\Requests\Users\UpdateTenantUserStatusRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TenantUserController extends Controller
{
    public function __construct(private readonly TenantUserManagementService $tenantUserManagementService)
    {
    }

    public function index(CurrentTenant $currentTenant): Response
    {
        Gate::authorize('super.admin');

        $tenant = $currentTenant->tenant();
        abort_if($tenant === null, 404);

        $memberships = TenantMembership::query()
            ->with([
                'user.roles' => fn ($query) => $query->wherePivot('tenant_id', $tenant->id),
                'defaultBranch',
            ])
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('is_owner')
            ->orderBy('id')
            ->get();

        return Inertia::render('Users/Index', [
            'users' => $memberships->map(fn (TenantMembership $membership) => [
                'membership_id' => $membership->id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'status' => $membership->status,
                'is_owner' => $membership->is_owner,
                'branch' => $membership->defaultBranch?->name,
                'roles' => $membership->user?->roles
                    ->filter(fn ($role) => $role->pivot?->tenant_id === $tenant->id)
                    ->map(fn ($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ])
                    ->values(),
            ]),
            'roles' => $tenant->roles()->orderBy('name')->get(['id', 'name', 'slug']),
            'branches' => $tenant->branches()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(StoreTenantUserRequest $request, CurrentTenant $currentTenant): RedirectResponse
    {
        Gate::authorize('super.admin');

        $tenant = $currentTenant->tenant();
        abort_if($tenant === null, 404);

        $this->tenantUserManagementService->createTenantUser(
            tenant: $tenant,
            data: $request->validated(),
            invitedByUserId: $request->user()->id,
        );

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function updateStatus(
        UpdateTenantUserStatusRequest $request,
        CurrentTenant $currentTenant,
        TenantMembership $membership
    ): RedirectResponse {
        Gate::authorize('super.admin');

        $tenant = $currentTenant->tenant();
        abort_if($tenant === null, 404);
        abort_if($membership->tenant_id !== $tenant->id, 403);

        if ($membership->is_owner) {
            return back()->with('error', 'No se puede desactivar al usuario owner.');
        }

        $this->tenantUserManagementService->updateMembershipStatus(
            membership: $membership,
            status: $request->validated('status'),
        );

        return back()->with('success', 'Estado del usuario actualizado.');
    }
}
