<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Services\BusinessBillingService;
use App\Services\Fiscal\BranchFiscalSettingsResolver;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $business = app(CurrentBusiness::class)->get();
        $user = $request->user();

        if ($business === null && $user?->isBusinessUser()) {
            $business = $user->business;
            if ($business?->is_active) {
                app(CurrentBusiness::class)->set($business);
            } else {
                $business = null;
            }
        }

        $branch = app(CurrentBranch::class)->get();

        if ($branch === null && $business !== null) {
            $branch = app(CurrentBranch::class)->resolve(
                $business,
                $request->session()->get('branch_id')
            );
            $request->session()->put('branch_id', $branch->id);
        }

        /** @var Collection<int, Branch> $branches */
        $branches = $business
            ? $business->branches()
                ->active()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_default'])
            : collect();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'is_super_admin' => $user?->isSuperAdmin() ?? false,
                'role' => $user?->role,
            ],
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'is_active' => $business->is_active,
                'has_multiple_branches' => $branches->count() > 1,
            ] : null,
            'branch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'is_default' => $branch->is_default,
            ] : null,
            'branches' => $branches
                ->map(fn (Branch $businessBranch): array => [
                    'id' => $businessBranch->id,
                    'name' => $businessBranch->name,
                    'code' => $businessBranch->code,
                    'is_default' => $businessBranch->is_default,
                ])
                ->values()
                ->all(),
            'modules' => [
                'electronic_billing' => [
                    'enabled' => $business !== null
                        && $branch !== null
                        && (bool) config('fiscal.enabled')
                        && app(BranchFiscalSettingsResolver::class)->isEnabledForBranch($business, $branch),
                ],
            ],
            'business_subscription' => $business ? app(BusinessBillingService::class)->maintenanceSummary($business) : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
