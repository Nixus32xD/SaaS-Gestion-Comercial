<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\StoreStaffMemberRequest;
use App\Models\Appointments\StaffMember;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StaffMemberController extends Controller
{
    public function index(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        return Inertia::render('Appointments/StaffMembers/Index', [
            'staff_members' => StaffMember::query()->forBusiness($business->id)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreStaffMemberRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        StaffMember::query()->create([
            'business_id' => $business->id,
            ...$request->validated(),
            'is_active' => (bool) $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Profesional registrado.');
    }

    public function update(StoreStaffMemberRequest $request, CurrentBusiness $currentBusiness, StaffMember $staffMember): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($staffMember->business_id !== $business->id, 403);

        $staffMember->update([
            ...$request->validated(),
            'is_active' => (bool) $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Profesional actualizado.');
    }

    public function destroy(CurrentBusiness $currentBusiness, StaffMember $staffMember): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($staffMember->business_id !== $business->id, 403);

        $staffMember->delete();

        return back()->with('success', 'Profesional eliminado.');
    }
}
