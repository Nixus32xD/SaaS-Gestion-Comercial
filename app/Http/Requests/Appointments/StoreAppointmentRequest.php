<?php

namespace App\Http\Requests\Appointments;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer'],
            'staff_member_id' => ['nullable', 'integer'],
            'appointment_customer_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'status' => ['nullable', 'string', 'in:scheduled,confirmed,completed,cancelled'],
            'notes' => ['nullable', 'string'],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
