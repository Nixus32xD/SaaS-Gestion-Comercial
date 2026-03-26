<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\AppointmentCustomer;
use App\Models\Appointments\Service;
use App\Models\Appointments\StaffMember;
use App\Services\Appointments\AppointmentService;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request, CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $from = $request->date('from', now()->startOfWeek())->startOfDay();
        $to = $request->date('to', now()->endOfWeek())->endOfDay();

        return Inertia::render('Appointments/Appointments/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'services' => Service::query()->forBusiness($business->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'duration_minutes']),
            'staff_members' => StaffMember::query()->forBusiness($business->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => AppointmentCustomer::query()->forBusiness($business->id)->orderBy('name')->get(['id', 'name']),
            'appointments' => Appointment::query()
                ->forBusiness($business->id)
                ->with(['service:id,name', 'staffMember:id,name', 'customer:id,name'])
                ->whereBetween('starts_at', [$from, $to])
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Appointment $appointment) => [
                    'id' => $appointment->id,
                    'service' => $appointment->service?->name,
                    'staff_member' => $appointment->staffMember?->name,
                    'customer' => $appointment->customer?->name,
                    'service_id' => $appointment->service_id,
                    'staff_member_id' => $appointment->staff_member_id,
                    'appointment_customer_id' => $appointment->appointment_customer_id,
                    'starts_at' => $appointment->starts_at?->format('Y-m-d\TH:i'),
                    'status' => $appointment->status,
                    'notes' => $appointment->notes,
                ]),
        ]);
    }

    public function store(StoreAppointmentRequest $request, CurrentBusiness $currentBusiness, AppointmentService $appointmentService): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $appointmentService->create($business, $request->validated(), $request->user()?->id);

        return back()->with('success', 'Turno creado correctamente.');
    }

    public function update(StoreAppointmentRequest $request, CurrentBusiness $currentBusiness, Appointment $appointment, AppointmentService $appointmentService): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($appointment->business_id !== $business->id, 403);

        $appointmentService->update($appointment, $request->validated(), $request->user()?->id);

        return back()->with('success', 'Turno actualizado correctamente.');
    }

    public function destroy(CurrentBusiness $currentBusiness, Appointment $appointment): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($appointment->business_id !== $business->id, 403);

        $appointment->delete();

        return back()->with('success', 'Turno eliminado correctamente.');
    }
}
