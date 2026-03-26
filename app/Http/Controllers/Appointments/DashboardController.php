<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Service;
use App\Models\Appointments\StaffMember;
use App\Support\CurrentBusiness;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        return Inertia::render('Appointments/Dashboard/Index', [
            'summary' => [
                'services_count' => Service::query()->forBusiness($business->id)->count(),
                'staff_count' => StaffMember::query()->forBusiness($business->id)->count(),
                'today_appointments' => Appointment::query()
                    ->forBusiness($business->id)
                    ->whereDate('starts_at', now()->toDateString())
                    ->count(),
            ],
        ]);
    }
}
