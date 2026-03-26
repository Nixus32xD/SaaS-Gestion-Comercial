<?php

namespace App\Http\Requests\Appointments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'min_notice_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'cancellation_notice_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'allow_online_booking' => ['required', 'boolean'],
            'allow_staff_selection' => ['required', 'boolean'],
            'default_slot_interval_minutes' => ['required', 'integer', 'min:5', 'max:240'],
        ];
    }
}
