<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Businesses\StoreBusinessPaymentRequest;
use App\Http\Requests\Admin\Businesses\UpdateBusinessSubscriptionRequest;
use App\Models\Business;
use App\Services\BusinessBillingService;
use Illuminate\Http\RedirectResponse;

class BusinessBillingController extends Controller
{
    public function __construct(private readonly BusinessBillingService $billingService)
    {
    }

    public function update(UpdateBusinessSubscriptionRequest $request, Business $business): RedirectResponse
    {
        $this->billingService->updateSubscription($business, $request->validated());

        return redirect()
            ->route('admin.businesses.edit', $business)
            ->with('success', 'Planes y abonos actualizados correctamente.');
    }

    public function storePayment(StoreBusinessPaymentRequest $request, Business $business): RedirectResponse
    {
        $this->billingService->recordPayment($business, $request->validated(), $request->user());

        return redirect()
            ->route('admin.businesses.edit', $business)
            ->with('success', 'Pago registrado correctamente.');
    }
}
