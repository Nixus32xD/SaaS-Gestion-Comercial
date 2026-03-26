<?php

namespace App\Http\Requests\Admin\Businesses;

use App\Support\CommercialPlanCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBusinessSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'implementation_plan_code' => $this->normalizeNullableString('implementation_plan_code'),
            'implementation_amount' => $this->normalizeNullableNumber('implementation_amount'),
            'maintenance_plan_code' => $this->normalizeNullableString('maintenance_plan_code'),
            'maintenance_amount' => $this->normalizeNullableNumber('maintenance_amount'),
            'maintenance_started_at' => $this->normalizeNullableString('maintenance_started_at'),
            'maintenance_ends_at' => $this->normalizeNullableString('maintenance_ends_at'),
            'subscription_grace_days' => $this->input('subscription_grace_days', 7),
            'subscription_notes' => $this->normalizeNullableString('subscription_notes'),
        ]);
    }

    public function rules(): array
    {
        $catalog = app(CommercialPlanCatalog::class);

        return [
            'implementation_plan_code' => ['nullable', 'string', Rule::in($catalog->implementationCodes())],
            'implementation_amount' => ['nullable', 'numeric', 'min:0'],
            'maintenance_plan_code' => ['nullable', 'string', Rule::in($catalog->maintenanceCodes())],
            'maintenance_amount' => ['nullable', 'numeric', 'min:0'],
            'maintenance_started_at' => ['nullable', 'date'],
            'maintenance_ends_at' => ['nullable', 'date', 'after_or_equal:maintenance_started_at'],
            'subscription_grace_days' => ['required', 'integer', 'between:0,30'],
            'subscription_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('implementation_amount') && ! $this->filled('implementation_plan_code')) {
                $validator->errors()->add('implementation_plan_code', 'Debes elegir un plan inicial para guardar el monto de implementacion.');
            }

            if (
                ($this->filled('maintenance_amount')
                    || $this->filled('maintenance_started_at')
                    || $this->filled('maintenance_ends_at'))
                && ! $this->filled('maintenance_plan_code')
            ) {
                $validator->errors()->add('maintenance_plan_code', 'Debes elegir un plan de mantenimiento antes de cargar monto o vencimiento.');
            }
        });
    }

    private function normalizeNullableNumber(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value !== '' ? $value : null;
    }

    private function normalizeNullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value !== '' ? $value : null;
    }
}
