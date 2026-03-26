<?php

namespace App\Http\Middleware;

use App\Support\CurrentBusiness;
use Illuminate\Http\Request;
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
            ] : null,

            'features' => $business ? [
                'stock' => $business->hasStockModule(),
                'appointments' => $business->hasAppointmentsModule(),
                'pos' => $business->hasFeature(\App\Models\BusinessFeature::POS),
                'reports' => $business->hasFeature(\App\Models\BusinessFeature::REPORTS),
                'public_booking' => $business->hasFeature(\App\Models\BusinessFeature::PUBLIC_BOOKING),
            ] : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
