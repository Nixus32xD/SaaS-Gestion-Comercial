<?php

use App\Models\Business;
use App\Models\User;

test('business admin can create staff users inside their business', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $response = $this
        ->actingAs($admin)
        ->post('/users', [
            'name' => 'Caja Uno',
            'email' => 'caja1@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'staff',
            'is_active' => true,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $user = User::query()->where('email', 'caja1@example.com')->first();

    expect($user)->not()->toBeNull();
    expect($user->business_id)->toBe($business->id);
    expect($user->role)->toBe('staff');
    expect($user->is_active)->toBeTrue();
});

test('staff can not access business user management', function () {
    $business = Business::factory()->create();
    $staff = User::factory()->staff($business->id)->create();

    $response = $this
        ->actingAs($staff)
        ->get('/users');

    $response->assertForbidden();
});

test('admin can not change status of users from another business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    $admin = User::factory()->businessAdmin($businessA->id)->create();
    $foreignUser = User::factory()->staff($businessB->id)->create();

    $response = $this
        ->actingAs($admin)
        ->patch("/users/{$foreignUser->id}/status", [
            'is_active' => false,
        ]);

    $response->assertForbidden();
    expect($foreignUser->fresh()->is_active)->toBeTrue();
});

test('admin can not change their own status', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $response = $this
        ->actingAs($admin)
        ->patch("/users/{$admin->id}/status", [
            'is_active' => false,
        ]);

    $response->assertRedirect();
    expect($admin->fresh()->is_active)->toBeTrue();
});
