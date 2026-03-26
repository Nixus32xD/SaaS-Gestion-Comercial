<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\StoreBlockedSlotRequest;
use App\Models\Appointments\BlockedSlot;
use App\Models\Appointments\StaffMember;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BlockedSlotController extends Controller
{
    public function index(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        return Inertia::render('Appointments/BlockedSlots/Index', [
            'staff_members' => StaffMember::query()->forBusiness($business->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'blocked_slots' => BlockedSlot::query()->forBusiness($business->id)->with('staffMember:id,name')->orderByDesc('starts_at')->limit(60)->get(),
        ]);
    }

    public function store(StoreBlockedSlotRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        BlockedSlot::query()->create([
            'business_id' => $business->id,
            'staff_member_id' => StaffMember::query()->forBusiness($business->id)->whereKey($request->integer('staff_member_id'))->value('id'),
            'starts_at' => $request->date('starts_at'),
            'ends_at' => $request->date('ends_at'),
            'reason' => $request->string('reason')->value() ?: null,
        ]);

        return back()->with('success', 'Bloqueo creado.');
    }

    public function destroy(CurrentBusiness $currentBusiness, BlockedSlot $blockedSlot): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($blockedSlot->business_id !== $business->id, 403);

        $blockedSlot->delete();

        return back()->with('success', 'Bloqueo eliminado.');
    }
}
