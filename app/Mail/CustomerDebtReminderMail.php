<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerDebtReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $businessName,
        public string $customerName,
        public string $subjectLine,
        public string $balanceLabel,
        public int $pendingSalesCount,
        public string $reminderMessage,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-debt-reminder',
        );
    }
}
