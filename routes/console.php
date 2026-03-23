<?php

use App\Mail\TestSmtpMail;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:test
    {to : Destinatario del correo de prueba}
    {--from-address= : Correo remitente}
    {--from-name=ComerStock : Nombre visible del remitente}
    {--subject=Prueba de correo ComerStock : Asunto del correo}
    {--host=smtp.hostinger.com : Servidor SMTP}
    {--port=465 : Puerto SMTP}
    {--scheme=smtps : Esquema SMTP (smtp o smtps)}
    {--verify-peer=1 : Verificar certificado TLS (1 o 0)}
    {--auto-tls=1 : Permitir STARTTLS automatico (1 o 0)}
    {--username= : Usuario SMTP}
    {--password= : Password SMTP}
', function () {
    $to = (string) $this->argument('to');
    $username = (string) ($this->option('username') ?: config('mail.mailers.smtp.username'));
    $password = (string) ($this->option('password') ?: config('mail.mailers.smtp.password'));

    if ($username === '' || $password === '') {
        $this->error('Faltan las credenciales SMTP. Indicá --username y --password o configurá MAIL_USERNAME / MAIL_PASSWORD.');

        return 1;
    }

    $fromAddress = (string) ($this->option('from-address') ?: config('mail.from.address') ?: $username);
    $fromName = (string) ($this->option('from-name') ?: config('mail.from.name') ?: config('app.name'));
    $subject = (string) $this->option('subject');

    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.transport' => 'smtp',
        'mail.mailers.smtp.host' => (string) $this->option('host'),
        'mail.mailers.smtp.port' => (int) $this->option('port'),
        'mail.mailers.smtp.scheme' => (string) $this->option('scheme'),
        'mail.mailers.smtp.verify_peer' => filter_var($this->option('verify-peer'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        'mail.mailers.smtp.auto_tls' => filter_var($this->option('auto-tls'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        'mail.mailers.smtp.username' => $username,
        'mail.mailers.smtp.password' => $password,
        'mail.from.address' => $fromAddress,
        'mail.from.name' => $fromName,
    ]);

    app('mail.manager')->forgetMailers();

    try {
        Mail::to($to)->send(new TestSmtpMail(
            mailSubject: $subject,
            fromAddress: $fromAddress,
            fromName: $fromName,
            host: (string) $this->option('host'),
            port: (int) $this->option('port'),
        ));
    } catch (\Throwable $exception) {
        report($exception);

        $this->error('No se pudo enviar el correo: '.$exception->getMessage());

        return 1;
    }

    $this->info("Correo enviado a {$to} desde {$fromAddress}.");

    return 0;
})->purpose('Envía un correo de prueba por SMTP');
