<?php

namespace App\Http\Requests\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMercadoPagoPointOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_method' => $this->filled('payment_method')
                ? (string) $this->input('payment_method')
                : Payment::METHOD_DEBIT_CARD,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in([
                Payment::METHOD_DEBIT_CARD,
                Payment::METHOD_CREDIT_CARD,
                Payment::METHOD_QR,
            ])],
        ];
    }
}
