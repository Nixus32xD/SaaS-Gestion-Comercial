<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('seeded demo admin receives RBAC access required by browser tests', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@demo.test')->firstOrFail();

    expect($admin->roles()->where('code', 'administrator')->exists())->toBeTrue()
        ->and($admin->branches()->count())->toBe(2);

    $this->post('/login', [
        'email' => 'admin@demo.test',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($admin);
});
