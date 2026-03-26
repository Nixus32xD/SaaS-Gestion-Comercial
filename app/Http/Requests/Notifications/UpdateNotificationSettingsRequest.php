<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBusinessAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notifications_enabled' => ['required', 'boolean'],
            'send_to_business_email' => ['required', 'boolean'],
            'send_to_admin_users' => ['required', 'boolean'],
            'low_stock_enabled' => ['required', 'boolean'],
            'expiration_enabled' => ['required', 'boolean'],
            'maintenance_due_enabled' => ['required', 'boolean'],
            'minimum_hours_between_alerts' => ['required', 'integer', 'min:1', 'max:168'],
            'notification_window_start_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'notification_window_end_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'extra_recipients' => ['nullable', 'array', 'max:20'],
            'extra_recipients.*' => ['email', 'distinct:ignore_case'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rawRecipients = (string) $this->input('extra_recipients_text', '');

        $recipients = collect(preg_split('/[\r\n,;]+/', $rawRecipients) ?: [])
            ->map(fn (string $email): string => trim($email))
            ->filter()
            ->values()
            ->all();

        $this->merge([
            'notifications_enabled' => $this->boolean('notifications_enabled'),
            'send_to_business_email' => $this->boolean('send_to_business_email'),
            'send_to_admin_users' => $this->boolean('send_to_admin_users'),
            'low_stock_enabled' => $this->boolean('low_stock_enabled'),
            'expiration_enabled' => $this->boolean('expiration_enabled'),
            'maintenance_due_enabled' => $this->boolean('maintenance_due_enabled'),
            'extra_recipients' => $recipients,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $startHour = (int) $this->input('notification_window_start_hour');
            $endHour = (int) $this->input('notification_window_end_hour');

            if ($endHour === $startHour) {
                $validator->errors()->add(
                    'notification_window_end_hour',
                    'La hora de cierre no puede ser igual a la hora de apertura.'
                );
            }
        });
    }
}
