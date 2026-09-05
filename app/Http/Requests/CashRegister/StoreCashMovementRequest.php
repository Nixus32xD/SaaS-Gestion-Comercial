<?php

namespace App\Http\Requests\CashRegister;

use App\Models\CashMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBusinessUser() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['description' => trim((string) $this->input('description')) ?: null]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([
                CashMovement::TYPE_MANUAL_INCOME,
                CashMovement::TYPE_MANUAL_EXPENSE,
                CashMovement::TYPE_ADJUSTMENT_IN,
                CashMovement::TYPE_ADJUSTMENT_OUT,
            ])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
