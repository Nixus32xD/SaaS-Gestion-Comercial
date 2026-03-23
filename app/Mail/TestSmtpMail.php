<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestSmtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $fromAddress,
        public string $fromName,
        public string $host,
        public int $port,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        $html = implode('', [
            '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Prueba SMTP</title></head><body>',
            '<h1>Prueba SMTP de '.e((string) config('app.name')).'</h1>',
            '<p>Este correo confirma que la configuracion SMTP esta respondiendo.</p>',
            '<ul>',
            '<li>Remitente: '.e($this->fromName).' &lt;'.e($this->fromAddress).'&gt;</li>',
            '<li>Servidor: '.e($this->host).':'.e((string) $this->port).'</li>',
            '<li>Fecha: '.e(now()->format('Y-m-d H:i:s')).'</li>',
            '</ul>',
            '<p>Si recibiste este mail, la salida de correo de la aplicacion ya puede usarse para alertas y recordatorios.</p>',
            '</body></html>',
        ]);

        return new Content(
            htmlString: $html,
        );
    }
}
