<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable)
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        $resetUrl = $this->resetUrl($notifiable);
        $expiration = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        $recipientName = trim((string) data_get($notifiable, 'name'));
        $supportLabel = parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.name', 'ComerStock');

        return (new MailMessage)
            ->subject('Recupera tu acceso a ComerStock')
            ->view('emails.auth.password-reset', [
                'recipientName' => $recipientName,
                'recipientEmail' => (string) $notifiable->getEmailForPasswordReset(),
                'resetUrl' => $resetUrl,
                'expirationMinutes' => $expiration,
                'loginUrl' => route('login'),
                'supportLabel' => (string) $supportLabel,
            ])
            ->text('emails.auth.password-reset-text', [
                'recipientName' => $recipientName,
                'recipientEmail' => (string) $notifiable->getEmailForPasswordReset(),
                'resetUrl' => $resetUrl,
                'expirationMinutes' => $expiration,
                'loginUrl' => route('login'),
                'supportLabel' => (string) $supportLabel,
            ]);
    }
}
