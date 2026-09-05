<?php

namespace App\Http\Requests\CashRegister;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBusinessUser() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['opening_notes' => trim((string) $this->input('opening_notes')) ?: null]);
    }

    public function rules(): array
    {
        return [
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
