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
            'fiscal_identity_id' => ['nullable', 'integer'],
            'fiscal_identity' => ['nullable', 'array'],
            'fiscal_identity.external_fiscal_id' => ['nullable', 'string', 'max:120'],
            'fiscal_identity.cuit' => ['nullable', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'fiscal_identity.environment' => ['nullable', Rule::in(['testing', 'production'])],
            'fiscal_identity.fiscal_condition' => ['nullable', Rule::in(['monotributo', 'responsable_inscripto', 'exento'])],
            'fiscal_identity.legal_name' => ['nullable', 'string', 'max:255'],
            'fiscal_identity.fiscal_activities' => ['nullable', 'array'],
            'fiscal_identity.fiscal_activities.*' => ['integer', 'min:1'],
            // Deprecated input aliases are accepted only to transition existing integrations.
            'fiscal_external_business_id' => ['nullable', 'string', 'max:120'],
            'fiscal_environment' => ['nullable', Rule::in(['testing', 'production'])],
            'fiscal_cuit' => ['nullable', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'fiscal_condition' => ['nullable', Rule::in(['monotributo', 'responsable_inscripto', 'exento'])],
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

        if (! $this->filled('fiscal_identity_id') && blank(data_get($this->input('fiscal_identity'), 'external_fiscal_id'))) {
            $this->merge(['fiscal_identity' => [
                'external_fiscal_id' => $this->input('fiscal_external_business_id'),
                'cuit' => $this->input('fiscal_cuit'),
                'environment' => $this->input('fiscal_environment'),
                'fiscal_condition' => $this->input('fiscal_condition'),
                'fiscal_activities' => $this->input('fiscal_activities'),
            ]]);
        }

        $identity = (array) $this->input('fiscal_identity', []);
        $this->merge(['fiscal_identity' => [
            'external_fiscal_id' => trim((string) ($identity['external_fiscal_id'] ?? '')) ?: null,
            'cuit' => preg_replace('/\D+/', '', (string) ($identity['cuit'] ?? '')) ?: null,
            'environment' => strtolower(trim((string) ($identity['environment'] ?? ''))) ?: null,
            'fiscal_condition' => strtolower(trim((string) ($identity['fiscal_condition'] ?? ''))) ?: null,
            'legal_name' => trim((string) ($identity['legal_name'] ?? '')) ?: null,
            'fiscal_activities' => collect(is_array($identity['fiscal_activities'] ?? null)
                ? $identity['fiscal_activities']
                : explode(',', (string) ($identity['fiscal_activities'] ?? '')))
                ->map(fn (mixed $activity): int => (int) trim((string) $activity))
                ->filter(fn (int $activity): bool => $activity > 0)->values()->all(),
        ]]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_enabled')) {
                return;
            }

            if (! $this->filled('fiscal_identity_id') && blank(data_get($this->input('fiscal_identity'), 'external_fiscal_id'))) {
                $validator->errors()->add('fiscal_identity_id', 'Selecciona o crea una identidad fiscal.');

                return;
            }

            if ($this->filled('fiscal_identity_id')) {
                return;
            }

            $identity = (array) $this->input('fiscal_identity');
            $cuit = (string) ($identity['cuit'] ?? '');
            foreach (['external_fiscal_id', 'cuit', 'environment', 'fiscal_condition'] as $field) {
                if (blank($identity[$field] ?? null)) {
                    $validator->errors()->add("fiscal_identity.{$field}", 'Este dato es obligatorio para crear la identidad fiscal.');
                }
            }

            if ($cuit === '' || $validator->errors()->has('fiscal_identity.cuit')) {
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
