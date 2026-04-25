<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => trim((string) $this->input('phone')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'address' => trim((string) $this->input('address')),
            'notes' => trim((string) $this->input('notes')),
            'reminder_notes' => trim((string) $this->input('reminder_notes')),
            'allow_reminders' => $this->boolean('allow_reminders'),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'preferred_reminder_channel' => ['required', Rule::in(['whatsapp', 'email', 'none'])],
            'allow_reminders' => ['required', 'boolean'],
            'reminder_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
