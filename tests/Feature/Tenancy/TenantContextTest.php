<?php

use App\Models\Business;
use App\Models\User;

test('authenticated admin without business can not access dashboard', function () {
    $user = User::factory()->businessAdmin()->create();

    $response = $this
        ->actingAs($user)
        ->get('/dashboard');

    $response->assertForbidden();
});

test('authenticated admin with active business can access dashboard', function () {
    $business = Business::factory()->create([
        'is_active' => true,
    ]);

    $user = User::factory()->businessAdmin($business->id)->create();

    $response = $this
        ->actingAs($user)
        ->get('/dashboard');

    $response->assertOk();
});

test('superadmin can not access business dashboard', function () {
    $user = User::factory()->superadmin()->create();

    $response = $this
        ->actingAs($user)
        ->get('/dashboard');

    $response->assertForbidden();
});

test('staff with active business can access dashboard', function () {
    $business = Business::factory()->create([
        'is_active' => true,
    ]);

    $user = User::factory()->staff($business->id)->create();

    $response = $this
        ->actingAs($user)
        ->get('/dashboard');

    $response->assertOk();
});
