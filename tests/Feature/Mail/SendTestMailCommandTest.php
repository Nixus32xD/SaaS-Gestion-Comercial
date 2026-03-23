<?php

use App\Mail\TestSmtpMail;
use Illuminate\Support\Facades\Mail;

test('mail test command sends the smtp test mailable', function () {
    Mail::fake();

    $this->artisan('mail:test', [
        'to' => 'nicolasmoron15@gmail.com',
        '--from-address' => 'notificaciones@comerstock.com',
        '--from-name' => 'ComerStock',
        '--username' => 'notificaciones@comerstock.com',
        '--password' => 'secret',
        '--host' => 'smtp.hostinger.com',
        '--port' => 465,
        '--scheme' => 'smtps',
        '--subject' => 'Prueba SMTP ComerStock',
    ])->assertExitCode(0);

    Mail::assertSent(TestSmtpMail::class, function (TestSmtpMail $mail) {
        return $mail->hasTo('nicolasmoron15@gmail.com')
            && $mail->mailSubject === 'Prueba SMTP ComerStock';
    });

    expect(config('mail.default'))->toBe('smtp');
    expect(config('mail.from.address'))->toBe('notificaciones@comerstock.com');
});
