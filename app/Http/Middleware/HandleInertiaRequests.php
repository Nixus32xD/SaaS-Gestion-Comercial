<?php

namespace App\Http\Middleware;

use App\Services\ProductExpirationAlertService;
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
        $user = $request->user();
        $business = app(CurrentBusiness::class)->get();
        $expirationAlerts = [];

        if ($business !== null) {
            $expirationAlerts = app(ProductExpirationAlertService::class)
                ->listForBusiness($business->id, 8)
                ->values()
                ->all();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'is_super_admin' => $user?->isSuperAdmin() ?? false,
                'role' => $user?->role,
            ],
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'is_active' => $business->is_active,
            ] : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'expiration_alerts' => $expirationAlerts,
        ];
    }
}
