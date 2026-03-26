<?php

namespace App\Http\Requests\Admin\Businesses;

use App\Models\BusinessPayment;
use App\Support\CommercialPlanCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBusinessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->normalizeNullableString('type'),
            'plan_code' => $this->normalizeNullableString('plan_code'),
            'amount' => $this->normalizeNullableNumber('amount'),
            'paid_at' => $this->normalizeNullableString('paid_at'),
            'coverage_ends_at' => $this->normalizeNullableString('coverage_ends_at'),
            'notes' => $this->normalizeNullableString('notes'),
        ]);
    }

    public function rules(): array
    {
        $catalog = app(CommercialPlanCatalog::class);

        return [
            'type' => ['required', Rule::in([BusinessPayment::TYPE_IMPLEMENTATION, BusinessPayment::TYPE_MAINTENANCE])],
            'plan_code' => ['nullable', 'string', Rule::in($catalog->allBillingCodes())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'coverage_ends_at' => ['nullable', 'date', 'after_or_equal:paid_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $catalog = app(CommercialPlanCatalog::class);
            $type = (string) $this->input('type');
            $planCode = $this->input('plan_code');

            if ($type === BusinessPayment::TYPE_MAINTENANCE && ! $this->filled('coverage_ends_at')) {
                $validator->errors()->add('coverage_ends_at', 'Debes indicar hasta que fecha queda cubierto el mantenimiento.');
            }

            if ($planCode === null || $planCode === '') {
                return;
            }

            if ($type === BusinessPayment::TYPE_IMPLEMENTATION && ! in_array($planCode, $catalog->implementationCodes(), true)) {
                $validator->errors()->add('plan_code', 'El plan seleccionado no corresponde a una implementacion inicial.');
            }

            if ($type === BusinessPayment::TYPE_MAINTENANCE && ! in_array($planCode, $catalog->maintenanceCodes(), true)) {
                $validator->errors()->add('plan_code', 'El plan seleccionado no corresponde a un mantenimiento mensual.');
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
