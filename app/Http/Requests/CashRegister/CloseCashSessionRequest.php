<?php

namespace App\Http\Requests\CashRegister;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBusinessUser() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['closing_notes' => trim((string) $this->input('closing_notes')) ?: null]);
    }

    public function rules(): array
    {
        return [
            'counted_amount' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
