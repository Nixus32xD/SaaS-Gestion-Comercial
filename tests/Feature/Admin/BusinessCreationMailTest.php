<?php

use App\Mail\BusinessCreatedWelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('superadmin creating a business queues onboarding mails for business and admin', function () {
    Mail::fake();

    $superAdmin = User::factory()->superadmin()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.businesses.store'), [
            'name' => 'Almacen Central',
            'owner_name' => 'Nicolas',
            'email' => 'comercio@almacen.test',
            'phone' => '2610000000',
            'address' => 'San Martin 123',
            'is_active' => true,
            'admin' => [
                'name' => 'Admin Comercio',
                'email' => 'admin@almacen.test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ],
        ])
        ->assertRedirect(route('admin.businesses.index'));

    Mail::assertQueued(BusinessCreatedWelcomeMail::class, 2);
    Mail::assertQueued(BusinessCreatedWelcomeMail::class, fn (BusinessCreatedWelcomeMail $mail) => $mail->hasTo('comercio@almacen.test'));
    Mail::assertQueued(BusinessCreatedWelcomeMail::class, fn (BusinessCreatedWelcomeMail $mail) => $mail->hasTo('admin@almacen.test') && $mail->recipientIsAdmin === true);
});
