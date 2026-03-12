<?php

use App\Models\Business;
use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('superadmin can authenticate and is redirected to admin panel', function () {
    $user = User::factory()->superadmin()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.businesses.index', absolute: false));
});

test('business admin can authenticate and is redirected to dashboard', function () {
    $business = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('business staff can authenticate and is redirected to dashboard', function () {
    $business = Business::factory()->create();
    $user = User::factory()->staff($business->id)->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('inactive users can not authenticate', function () {
    $user = User::factory()->create([
        'is_active' => false,
    ]);

    $response = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('users from inactive business can not authenticate', function () {
    $business = Business::factory()->create([
        'is_active' => false,
    ]);
    $user = User::factory()->businessAdmin($business->id)->create();

    $response = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});
