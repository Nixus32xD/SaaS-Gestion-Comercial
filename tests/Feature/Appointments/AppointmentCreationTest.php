<?php

use App\Models\Appointments\Appointment;
use App\Models\Appointments\AppointmentCustomer;
use App\Models\Appointments\Service;
use App\Models\Appointments\StaffMember;
use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\User;

it('creates appointment scoped by business_id', function () {
    $business = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();

    BusinessFeature::query()->updateOrCreate([
        'business_id' => $business->id,
        'feature' => BusinessFeature::APPOINTMENTS,
    ], ['is_enabled' => true]);

    $service = Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 30]);
    $staff = StaffMember::factory()->create(['business_id' => $business->id]);
    $customer = AppointmentCustomer::factory()->create(['business_id' => $business->id]);

    $this->actingAs($user)
        ->post(route('appointments.appointments.store'), [
            'service_id' => $service->id,
            'staff_member_id' => $staff->id,
            'appointment_customer_id' => $customer->id,
            'starts_at' => now()->addDays(1)->startOfHour()->toDateTimeString(),
            'status' => Appointment::STATUS_SCHEDULED,
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->first();
    expect($appointment)->not->toBeNull();
    expect($appointment->business_id)->toBe($business->id);
});
