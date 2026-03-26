<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\BusinessBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, BusinessBillingService $billingService): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        if ($user !== null) {
            $user->forceFill([
                'last_login_at' => now(),
            ])->save();

            if ($user->isBusinessUser()) {
                $business = $user->business;

                if ($business === null || ! $business->is_active) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    throw ValidationException::withMessages([
                        'email' => 'Tu comercio esta inactivo o no esta asignado.',
                    ]);
                }

                if ($billingService->shouldBlockBusinessAccess($business)) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    throw ValidationException::withMessages([
                        'email' => 'El abono del comercio vencio y supero la gracia de 7 dias. Contactanos para reactivarlo.',
                    ]);
                }

                $request->session()->put('business_id', $business->id);
            }
        }

        if ($user?->isSuperAdmin()) {
            return redirect()->route('admin.businesses.index');
        }

        return redirect()->route('dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
