<?php

namespace App\Http\Requests\Admin\Businesses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBranchFiscalSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'fiscal_external_business_id' => ['nullable', 'string', 'max:120'],
            'fiscal_environment' => [Rule::requiredIf(fn (): bool => $this->boolean('is_enabled')), 'nullable', Rule::in(['testing', 'production'])],
            'fiscal_cuit' => [Rule::requiredIf(fn (): bool => $this->boolean('is_enabled')), 'nullable', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'fiscal_condition' => [Rule::requiredIf(fn (): bool => $this->boolean('is_enabled')), 'nullable', Rule::in(['monotributo', 'responsable_inscripto', 'exento'])],
            'fiscal_point_of_sale' => [Rule::requiredIf(fn (): bool => $this->boolean('is_enabled')), 'nullable', 'integer', 'min:1', 'max:99999'],
            'fiscal_document_type' => ['nullable', 'string', 'max:40'],
            'fiscal_cbte_type' => ['nullable', 'integer', 'min:1', 'max:999'],
            'fiscal_concept' => ['nullable', 'integer', 'in:1,2,3'],
            'fiscal_authorization_mode' => ['nullable', Rule::in(['cae', 'caea', 'auto'])],
            'fiscal_caea_code' => ['nullable', 'string', 'digits:14'],
            'fiscal_caea_period' => ['nullable', 'string', 'digits:6'],
            'fiscal_caea_order' => ['nullable', 'integer', 'in:1,2'],
            'fiscal_caea_from' => ['nullable', 'date'],
            'fiscal_caea_to' => ['nullable', 'date'],
            'fiscal_caea_due_date' => ['nullable', 'date'],
            'fiscal_caea_report_deadline' => ['nullable', 'date'],
            'fiscal_activities' => ['nullable', 'array'],
            'fiscal_activities.*' => ['integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_enabled' => $this->boolean('is_enabled'),
            'fiscal_external_business_id' => trim((string) $this->input('fiscal_external_business_id')) ?: null,
            'fiscal_environment' => strtolower(trim((string) $this->input('fiscal_environment'))) ?: null,
            'fiscal_cuit' => preg_replace('/\D+/', '', (string) $this->input('fiscal_cuit')) ?: null,
            'fiscal_condition' => strtolower(trim((string) $this->input('fiscal_condition'))) ?: null,
            'fiscal_point_of_sale' => $this->filled('fiscal_point_of_sale') ? (int) $this->input('fiscal_point_of_sale') : null,
            'fiscal_document_type' => trim((string) $this->input('fiscal_document_type')) ?: null,
            'fiscal_cbte_type' => $this->filled('fiscal_cbte_type') ? (int) $this->input('fiscal_cbte_type') : null,
            'fiscal_concept' => $this->filled('fiscal_concept') ? (int) $this->input('fiscal_concept') : null,
            'fiscal_authorization_mode' => strtolower(trim((string) $this->input('fiscal_authorization_mode'))) ?: null,
            'fiscal_caea_code' => preg_replace('/\D+/', '', (string) $this->input('fiscal_caea_code')) ?: null,
            'fiscal_caea_period' => preg_replace('/\D+/', '', (string) $this->input('fiscal_caea_period')) ?: null,
            'fiscal_caea_order' => $this->filled('fiscal_caea_order') ? (int) $this->input('fiscal_caea_order') : null,
            'fiscal_caea_from' => $this->input('fiscal_caea_from') ?: null,
            'fiscal_caea_to' => $this->input('fiscal_caea_to') ?: null,
            'fiscal_caea_due_date' => $this->input('fiscal_caea_due_date') ?: null,
            'fiscal_caea_report_deadline' => $this->input('fiscal_caea_report_deadline') ?: null,
            'fiscal_activities' => collect(explode(',', (string) $this->input('fiscal_activities', '')))
                ->map(fn (string $activity): int => (int) trim($activity))
                ->filter(fn (int $activity): bool => $activity > 0)
                ->values()
                ->all(),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_enabled')) {
                return;
            }

            $cuit = (string) $this->input('fiscal_cuit');
            if ($cuit === '' || $validator->errors()->has('fiscal_cuit')) {
                return;
            }

            $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
            $sum = 0;

            foreach ($weights as $index => $weight) {
                $sum += ((int) $cuit[$index]) * $weight;
            }

            $checkDigit = 11 - ($sum % 11);
            $checkDigit = $checkDigit === 11 ? 0 : ($checkDigit === 10 ? 9 : $checkDigit);

            if ($checkDigit !== (int) $cuit[10]) {
                $validator->errors()->add('fiscal_cuit', 'El CUIT fiscal no es valido.');
            }
        });
    }
}
