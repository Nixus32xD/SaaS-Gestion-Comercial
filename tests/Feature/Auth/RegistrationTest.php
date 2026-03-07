<?php

use App\Models\Business;
use App\Models\User;

test('registration screen is not available', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('users can not register through public endpoint', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
    expect(Business::query()->count())->toBe(0);
});
