<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessCreatedWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param list<string> $applicationFunctions
     * @param list<string> $adminPermissions
     */
    public function __construct(
        public string $businessName,
        public string $recipientName,
        public bool $recipientIsAdmin,
        public string $adminName,
        public string $adminEmail,
        public string $plainPassword,
        public array $applicationFunctions,
        public array $adminPermissions,
        public string $loginUrl,
        public string $passwordResetUrl,
        string $queueName = 'notifications',
    ) {
        $this->afterCommit();
        $this->onQueue($queueName);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ComerStock | Acceso inicial para '.$this->businessName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.business-created-welcome',
        );
    }
}
