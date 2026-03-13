<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', 'in:cash,transfer'],
            'amount_received' => ['nullable', 'numeric', 'gte:0'],
            'discount' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sold_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_method' => $this->filled('payment_method')
                ? (string) $this->input('payment_method')
                : null,
            'amount_received' => $this->filled('amount_received')
                ? $this->input('amount_received')
                : null,
        ]);
    }
}
