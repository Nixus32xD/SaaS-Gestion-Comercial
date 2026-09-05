<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBusinessUser() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => trim((string) $this->input('notes')) ?: null,
            'idempotency_key' => trim((string) $this->input('idempotency_key')),
        ]);
    }

    public function rules(): array
    {
        return [
            'to_branch_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'expected_from_branch_id' => ['nullable', 'integer'],
        ];
    }
}
