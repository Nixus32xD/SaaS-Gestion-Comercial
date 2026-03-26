<?php

use App\Models\Business;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('business users see a notice when maintenance is close to due date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-04-28 10:00:00'));

    $business = Business::factory()->create([
        'maintenance_plan_code' => 'basico',
        'maintenance_amount' => 25000,
        'maintenance_started_at' => '2026-03-28',
        'maintenance_ends_at' => '2026-04-30',
        'subscription_grace_days' => 7,
    ]);
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('business_subscription.status', 'due_soon')
            ->where('business_subscription.plan_title', 'Plan Basico')
        );
});

test('business users can keep operating during grace period', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-03 10:00:00'));

    $business = Business::factory()->create([
        'maintenance_plan_code' => 'basico',
        'maintenance_amount' => 25000,
        'maintenance_started_at' => '2026-03-28',
        'maintenance_ends_at' => '2026-04-30',
        'subscription_grace_days' => 7,
    ]);
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('business_subscription.status', 'grace')
            ->where('business_subscription.grace_ends_at', '2026-05-07')
        );
});

test('business users can not login or access once grace period is over', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-08 10:00:00'));

    $business = Business::factory()->create([
        'maintenance_plan_code' => 'basico',
        'maintenance_amount' => 25000,
        'maintenance_started_at' => '2026-03-28',
        'maintenance_ends_at' => '2026-04-30',
        'subscription_grace_days' => 7,
    ]);
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertForbidden();

    auth()->logout();

    $response = $this->from('/login')->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});
