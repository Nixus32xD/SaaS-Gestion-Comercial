<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessUserAccessMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public string $businessName,
        public string $userName,
        public string $roleLabel,
        public array $permissions,
        public string $plainPassword,
        public bool $isActive,
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
            subject: 'ComerStock | Nuevo acceso para '.$this->businessName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.business-user-access',
        );
    }
}
