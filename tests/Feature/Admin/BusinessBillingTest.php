<?php

use App\Models\Business;
use App\Models\BusinessPayment;
use App\Models\User;

test('superadmin can update business billing settings', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.billing.update', $business), [
            'implementation_plan_code' => 'esencial',
            'implementation_amount' => 150000,
            'maintenance_plan_code' => 'basico',
            'maintenance_amount' => 25000,
            'maintenance_started_at' => '2026-03-25',
            'maintenance_ends_at' => '2026-04-25',
            'subscription_grace_days' => 7,
            'subscription_notes' => 'Cliente referido.',
        ])
        ->assertRedirect(route('admin.businesses.edit', $business));

    expect($business->fresh())
        ->implementation_plan_code->toBe('esencial')
        ->implementation_amount->toBe(150000.0)
        ->maintenance_plan_code->toBe('basico')
        ->maintenance_amount->toBe(25000.0)
        ->subscription_grace_days->toBe(7)
        ->subscription_notes->toBe('Cliente referido.');

    expect($business->fresh()->maintenance_started_at?->toDateString())->toBe('2026-03-25');
    expect($business->fresh()->maintenance_ends_at?->toDateString())->toBe('2026-04-25');
});

test('superadmin can record manual maintenance payments for a business', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create([
        'maintenance_plan_code' => 'basico',
        'maintenance_amount' => 25000,
        'subscription_grace_days' => 7,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('admin.businesses.payments.store', $business), [
            'type' => 'maintenance',
            'plan_code' => 'basico',
            'amount' => 25000,
            'paid_at' => '2026-03-25',
            'coverage_ends_at' => '2026-04-25',
            'notes' => 'Pago por transferencia.',
        ])
        ->assertRedirect(route('admin.businesses.edit', $business));

    $payment = BusinessPayment::query()->first();

    expect($payment)->not()->toBeNull();
    expect($payment->business_id)->toBe($business->id);
    expect($payment->type)->toBe('maintenance');
    expect($payment->plan_code)->toBe('basico');
    expect($payment->amount)->toBe(25000.0);
    expect($payment->paid_at?->toDateString())->toBe('2026-03-25');
    expect($payment->coverage_ends_at?->toDateString())->toBe('2026-04-25');

    expect($business->fresh()->maintenance_ends_at?->toDateString())->toBe('2026-04-25');
});
