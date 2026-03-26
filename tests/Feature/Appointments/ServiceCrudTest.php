<?php

use App\Models\Appointments\Service;
use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\User;

test('business can create service with appointments feature enabled', function () {
    $business = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();

    BusinessFeature::query()->updateOrCreate([
        'business_id' => $business->id,
        'feature' => BusinessFeature::APPOINTMENTS,
    ], ['is_enabled' => true]);

    $this->actingAs($user)
        ->post(route('appointments.services.store'), [
            'name' => 'Corte premium',
            'duration_minutes' => 45,
            'price' => 15000,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(Service::query()->forBusiness($business->id)->where('name', 'Corte premium')->exists())->toBeTrue();
});
