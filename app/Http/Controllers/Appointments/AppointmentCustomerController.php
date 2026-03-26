<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\StoreAppointmentCustomerRequest;
use App\Models\Appointments\AppointmentCustomer;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentCustomerController extends Controller
{
    public function index(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        return Inertia::render('Appointments/Customers/Index', [
            'customers' => AppointmentCustomer::query()->forBusiness($business->id)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreAppointmentCustomerRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        AppointmentCustomer::query()->create([
            'business_id' => $business->id,
            ...$request->validated(),
        ]);

        return back()->with('success', 'Cliente guardado.');
    }

    public function update(StoreAppointmentCustomerRequest $request, CurrentBusiness $currentBusiness, AppointmentCustomer $customer): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($customer->business_id !== $business->id, 403);

        $customer->update($request->validated());

        return back()->with('success', 'Cliente actualizado.');
    }

    public function destroy(CurrentBusiness $currentBusiness, AppointmentCustomer $customer): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($customer->business_id !== $business->id, 403);

        $customer->delete();

        return back()->with('success', 'Cliente eliminado.');
    }
}
