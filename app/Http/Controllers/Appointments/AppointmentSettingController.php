<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\UpdateAppointmentSettingsRequest;
use App\Models\Appointments\AppointmentSetting;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentSettingController extends Controller
{
    public function edit(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $settings = AppointmentSetting::query()->firstOrCreate(['business_id' => $business->id]);

        return Inertia::render('Appointments/Settings/Edit', [
            'settings' => $settings,
        ]);
    }

    public function update(UpdateAppointmentSettingsRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        AppointmentSetting::query()->updateOrCreate(
            ['business_id' => $business->id],
            $request->validated(),
        );

        return back()->with('success', 'Configuracion de turnos actualizada.');
    }
}
