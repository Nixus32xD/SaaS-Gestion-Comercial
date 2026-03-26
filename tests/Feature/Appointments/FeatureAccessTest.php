<?php

use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\User;

test('appointments routes are blocked when feature is disabled', function () {
    $business = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();

    BusinessFeature::query()->updateOrCreate([
        'business_id' => $business->id,
        'feature' => BusinessFeature::APPOINTMENTS,
    ], ['is_enabled' => false]);

    $this->actingAs($user)
        ->get(route('appointments.dashboard'))
        ->assertForbidden();
});

test('appointments routes are accessible when feature is enabled', function () {
    $business = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();

    BusinessFeature::query()->updateOrCreate([
        'business_id' => $business->id,
        'feature' => BusinessFeature::APPOINTMENTS,
    ], ['is_enabled' => true]);

    $this->actingAs($user)
        ->get(route('appointments.dashboard'))
        ->assertOk();
});
