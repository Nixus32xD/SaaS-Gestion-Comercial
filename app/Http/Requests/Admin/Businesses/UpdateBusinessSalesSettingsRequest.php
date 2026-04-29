<?php

namespace App\Http\Requests\Admin\Businesses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBusinessSalesSettingsRequest extends FormRequest
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
        $businessId = (int) $this->route('business')->id;
        $documentTypes = collect((array) config('fiscal.document_types', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();
        $voucherTypes = collect((array) config('fiscal.voucher_types', []))
            ->pluck('value')
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();
        $authorizationModes = collect((array) config('fiscal.authorization_modes', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();
        $fiscalEnvironments = collect((array) config('fiscal.environments', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        return [
            'advanced_sale_settings_enabled' => ['required', 'boolean'],
            'global_product_catalog_enabled' => ['required', 'boolean'],
            'fiscal_enabled' => ['required', 'boolean'],
            'fiscal_external_business_id' => ['nullable', 'string', 'max:120'],
            'fiscal_environment' => [Rule::requiredIf(fn (): bool => $this->boolean('fiscal_enabled')), 'nullable', 'string', Rule::in($fiscalEnvironments)],
            'fiscal_cuit' => [Rule::requiredIf(fn (): bool => $this->boolean('fiscal_enabled')), 'nullable', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'fiscal_point_of_sale' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'fiscal_document_type' => ['nullable', 'string', 'max:40', Rule::in($documentTypes)],
            'fiscal_cbte_type' => ['nullable', 'integer', 'min:1', 'max:999', Rule::in($voucherTypes)],
            'fiscal_concept' => ['nullable', 'integer', 'in:1,2,3'],
            'fiscal_authorization_mode' => ['nullable', 'string', Rule::in($authorizationModes)],
            'fiscal_activities' => ['nullable', 'array'],
            'fiscal_activities.*' => ['integer', 'min:1'],
            'sale_sectors' => ['nullable', 'array'],
            'sale_sectors.*.id' => [
                'nullable',
                'integer',
                Rule::exists('business_sale_sectors', 'id')->where(
                    fn ($query) => $query->where('business_id', $businessId)
                ),
            ],
            'sale_sectors.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'sale_sectors.*.description' => ['nullable', 'string', 'max:255'],
            'sale_sectors.*.is_active' => ['required', 'boolean'],
            'payment_destinations' => ['nullable', 'array'],
            'payment_destinations.*.id' => [
                'nullable',
                'integer',
                Rule::exists('business_payment_destinations', 'id')->where(
                    fn ($query) => $query->where('business_id', $businessId)
                ),
            ],
            'payment_destinations.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'payment_destinations.*.account_holder' => ['nullable', 'string', 'max:255'],
            'payment_destinations.*.reference' => ['nullable', 'string', 'max:255'],
            'payment_destinations.*.account_number' => ['nullable', 'string', 'max:255'],
            'payment_destinations.*.is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $fiscalEnvironment = strtolower(trim((string) $this->input(
            'fiscal_environment',
            config('fiscal.environment', 'testing')
        )));
        $fiscalEnvironment = in_array($fiscalEnvironment, ['testing', 'production'], true)
            ? $fiscalEnvironment
            : 'testing';

        $this->merge([
            'advanced_sale_settings_enabled' => $this->boolean('advanced_sale_settings_enabled'),
            'global_product_catalog_enabled' => $this->boolean('global_product_catalog_enabled'),
            'fiscal_enabled' => $this->boolean('fiscal_enabled'),
            'fiscal_external_business_id' => trim((string) $this->input('fiscal_external_business_id')),
            'fiscal_environment' => $fiscalEnvironment,
            'fiscal_cuit' => preg_replace('/\D+/', '', (string) $this->input('fiscal_cuit', '')) ?: null,
            'fiscal_point_of_sale' => $this->filled('fiscal_point_of_sale')
                ? (int) $this->input('fiscal_point_of_sale')
                : null,
            'fiscal_document_type' => trim((string) $this->input('fiscal_document_type')),
            'fiscal_cbte_type' => $this->filled('fiscal_cbte_type')
                ? (int) $this->input('fiscal_cbte_type')
                : null,
            'fiscal_concept' => $this->filled('fiscal_concept')
                ? (int) $this->input('fiscal_concept')
                : null,
            'fiscal_authorization_mode' => trim((string) $this->input(
                'fiscal_authorization_mode',
                config('fiscal.defaults.authorization_mode', 'cae')
            )),
            'fiscal_activities' => collect(explode(',', (string) $this->input('fiscal_activities', '')))
                ->map(fn (string $activity): string => trim($activity))
                ->filter()
                ->map(fn (string $activity): int => (int) $activity)
                ->values()
                ->all(),
            'sale_sectors' => collect((array) $this->input('sale_sectors', []))
                ->map(fn (array $sector): array => [
                    'id' => $sector['id'] ?? null,
                    'name' => trim((string) ($sector['name'] ?? '')),
                    'description' => trim((string) ($sector['description'] ?? '')),
                    'is_active' => filter_var($sector['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                ])
                ->filter(fn (array $sector): bool => $sector['id'] !== null || $sector['name'] !== '' || $sector['description'] !== '')
                ->values()
                ->all(),
            'payment_destinations' => collect((array) $this->input('payment_destinations', []))
                ->map(fn (array $destination): array => [
                    'id' => $destination['id'] ?? null,
                    'name' => trim((string) ($destination['name'] ?? '')),
                    'account_holder' => trim((string) ($destination['account_holder'] ?? '')),
                    'reference' => trim((string) ($destination['reference'] ?? '')),
                    'account_number' => trim((string) ($destination['account_number'] ?? '')),
                    'is_active' => filter_var($destination['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                ])
                ->filter(fn (array $destination): bool => $destination['id'] !== null
                    || $destination['name'] !== ''
                    || $destination['account_holder'] !== ''
                    || $destination['reference'] !== ''
                    || $destination['account_number'] !== '')
                ->values()
                ->all(),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('advanced_sale_settings_enabled')) {
                $this->validateFiscalCuit($validator);

                return;
            }

            $activeSectors = collect((array) $this->input('sale_sectors', []))
                ->filter(fn (array $sector): bool => (bool) ($sector['is_active'] ?? false));

            $activeDestinations = collect((array) $this->input('payment_destinations', []))
                ->filter(fn (array $destination): bool => (bool) ($destination['is_active'] ?? false));

            if ($activeSectors->isEmpty()) {
                $validator->errors()->add('sale_sectors', 'Debes configurar al menos un sector activo para habilitar esta funcion.');
            }

            if ($activeDestinations->isEmpty()) {
                $validator->errors()->add('payment_destinations', 'Debes configurar al menos una cuenta activa para habilitar esta funcion.');
            }

            $this->validateFiscalCuit($validator);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fiscal_cuit.required' => 'El CUIT fiscal es obligatorio para habilitar facturacion electronica.',
            'fiscal_cuit.size' => 'El CUIT fiscal debe tener 11 digitos.',
            'fiscal_cuit.regex' => 'El CUIT fiscal debe contener solo numeros.',
        ];
    }

    private function validateFiscalCuit(Validator $validator): void
    {
        $cuit = (string) $this->input('fiscal_cuit');

        if ($cuit === '' || $validator->errors()->has('fiscal_cuit')) {
            return;
        }

        if (! in_array(substr($cuit, 0, 2), ['20', '23', '24', '27', '30', '33', '34'], true)) {
            $validator->errors()->add('fiscal_cuit', 'El CUIT fiscal tiene un prefijo invalido.');

            return;
        }

        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += ((int) $cuit[$index]) * $weight;
        }

        $checkDigit = 11 - ($sum % 11);
        if ($checkDigit === 11) {
            $checkDigit = 0;
        } elseif ($checkDigit === 10) {
            $checkDigit = 9;
        }

        if ($checkDigit !== (int) $cuit[10]) {
            $validator->errors()->add('fiscal_cuit', 'El CUIT fiscal no es valido.');
        }
    }
}
