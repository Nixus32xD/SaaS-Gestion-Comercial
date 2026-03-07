<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Tenancy\Services\TenantOnboardingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantOwnerRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly TenantOnboardingService $tenantOnboardingService)
    {
    }

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'defaultCurrency' => 'ARS',
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(RegisterTenantOwnerRequest $request): RedirectResponse
    {
        $result = $this->tenantOnboardingService->onboardOwner($request->validated());
        $user = $result['user'];

        $request->session()->put('tenant_id', $result['tenant']->id);
        $request->session()->put('branch_id', $result['branch']->id);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
