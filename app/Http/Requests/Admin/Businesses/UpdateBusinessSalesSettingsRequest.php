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
        $fiscalConditions = collect((array) config('fiscal.fiscal_conditions', []))
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
            'fiscal_condition' => [Rule::requiredIf(fn (): bool => $this->boolean('fiscal_enabled')), 'nullable', 'string', Rule::in($fiscalConditions)],
            'fiscal_point_of_sale' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'fiscal_document_type' => ['nullable', 'string', 'max:40', Rule::in($documentTypes)],
            'fiscal_cbte_type' => ['nullable', 'integer', 'min:1', 'max:999', Rule::in($voucherTypes)],
            'fiscal_concept' => ['nullable', 'integer', 'in:1,2,3'],
            'fiscal_authorization_mode' => ['nullable', 'string', Rule::in($authorizationModes)],
            'fiscal_caea_code' => ['nullable', 'string', 'digits:14'],
            'fiscal_caea_period' => ['nullable', 'string', 'digits:6'],
            'fiscal_caea_order' => ['nullable', 'integer', 'in:1,2'],
            'fiscal_caea_from' => ['nullable', 'date'],
            'fiscal_caea_to' => ['nullable', 'date'],
            'fiscal_caea_due_date' => ['nullable', 'date'],
            'fiscal_caea_report_deadline' => ['nullable', 'date'],
            'fiscal_activities' => ['nullable', 'array'],
            'fiscal_activities.*' => ['integer', 'min:1'],
            'mercadopago_enabled' => ['required', 'boolean'],
            'mercadopago_environment' => ['required', Rule::in(['testing', 'production'])],
            'mercadopago_public_key' => ['nullable', 'string', 'max:2000'],
            'mercadopago_access_token' => ['nullable', 'string', 'max:2000'],
            'mercadopago_webhook_secret' => ['nullable', 'string', 'max:2000'],
            'mercadopago_point_terminal_id' => ['nullable', 'string', 'max:160'],
            'mercadopago_point_store_id' => ['nullable', 'string', 'max:80'],
            'mercadopago_point_pos_id' => ['nullable', 'string', 'max:80'],
            'mercadopago_point_external_store_id' => ['nullable', 'string', 'max:80'],
            'mercadopago_point_external_pos_id' => ['nullable', 'string', 'max:80'],
            'mercadopago_point_expiration_time' => ['nullable', 'string', 'max:20', 'regex:/^PT(?=\\d)(?:\\d+H)?(?:\\d+M)?(?:\\d+S)?$/'],
            'mercadopago_point_print_on_terminal' => ['required', Rule::in(['no_ticket', 'seller_ticket'])],
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
        $mercadoPagoEnvironment = strtolower(trim((string) $this->input('mercadopago_environment', 'testing')));
        $mercadoPagoEnvironment = in_array($mercadoPagoEnvironment, ['testing', 'production'], true)
            ? $mercadoPagoEnvironment
            : 'testing';

        $this->merge([
            'advanced_sale_settings_enabled' => $this->boolean('advanced_sale_settings_enabled'),
            'global_product_catalog_enabled' => $this->boolean('global_product_catalog_enabled'),
            'fiscal_enabled' => $this->boolean('fiscal_enabled'),
            'fiscal_external_business_id' => trim((string) $this->input('fiscal_external_business_id')),
            'fiscal_environment' => $fiscalEnvironment,
            'fiscal_cuit' => preg_replace('/\D+/', '', (string) $this->input('fiscal_cuit', '')) ?: null,
            'fiscal_condition' => $this->normalizeFiscalCondition($this->input(
                'fiscal_condition',
                config('fiscal.defaults.fiscal_condition', 'monotributo')
            )),
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
            'fiscal_caea_code' => preg_replace('/\D+/', '', (string) $this->input('fiscal_caea_code', '')) ?: null,
            'fiscal_caea_period' => preg_replace('/\D+/', '', (string) $this->input('fiscal_caea_period', '')) ?: null,
            'fiscal_caea_order' => $this->filled('fiscal_caea_order') ? (int) $this->input('fiscal_caea_order') : null,
            'fiscal_caea_from' => $this->input('fiscal_caea_from') ?: null,
            'fiscal_caea_to' => $this->input('fiscal_caea_to') ?: null,
            'fiscal_caea_due_date' => $this->input('fiscal_caea_due_date') ?: null,
            'fiscal_caea_report_deadline' => $this->input('fiscal_caea_report_deadline') ?: null,
            'fiscal_activities' => collect(explode(',', (string) $this->input('fiscal_activities', '')))
                ->map(fn (string $activity): string => trim($activity))
                ->filter()
                ->map(fn (string $activity): int => (int) $activity)
                ->values()
                ->all(),
            'mercadopago_enabled' => $this->boolean('mercadopago_enabled'),
            'mercadopago_environment' => $mercadoPagoEnvironment,
            'mercadopago_public_key' => trim((string) $this->input('mercadopago_public_key', '')),
            'mercadopago_access_token' => trim((string) $this->input('mercadopago_access_token', '')),
            'mercadopago_webhook_secret' => trim((string) $this->input('mercadopago_webhook_secret', '')),
            'mercadopago_point_terminal_id' => trim((string) $this->input('mercadopago_point_terminal_id', '')),
            'mercadopago_point_store_id' => trim((string) $this->input('mercadopago_point_store_id', '')),
            'mercadopago_point_pos_id' => trim((string) $this->input('mercadopago_point_pos_id', '')),
            'mercadopago_point_external_store_id' => trim((string) $this->input('mercadopago_point_external_store_id', '')),
            'mercadopago_point_external_pos_id' => trim((string) $this->input('mercadopago_point_external_pos_id', '')),
            'mercadopago_point_expiration_time' => trim((string) $this->input('mercadopago_point_expiration_time', 'PT15M')) ?: 'PT15M',
            'mercadopago_point_print_on_terminal' => $this->input('mercadopago_point_print_on_terminal') === 'seller_ticket'
                ? 'seller_ticket'
                : 'no_ticket',
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
                $this->validateMercadoPagoPoint($validator);

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
            $this->validateMercadoPagoPoint($validator);
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
            'fiscal_condition.required' => 'La condicion fiscal del comercio es obligatoria para habilitar facturacion electronica.',
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

    private function validateMercadoPagoPoint(Validator $validator): void
    {
        if (! $this->boolean('mercadopago_enabled')) {
            return;
        }

        $credential = $this->route('business')?->mercadoPagoCredential()->first();

        if (! $this->filled('mercadopago_access_token') && blank($credential?->access_token)) {
            $validator->errors()->add('mercadopago_access_token', 'El access token es obligatorio para habilitar Point.');
        }

        if (! $this->filled('mercadopago_point_terminal_id') && blank($credential?->point_terminal_id)) {
            $validator->errors()->add('mercadopago_point_terminal_id', 'La terminal Point es obligatoria para habilitar Point.');
        }
    }

    private function normalizeFiscalCondition(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['monotributo', 'responsable_inscripto', 'exento'], true)
            ? $value
            : (string) config('fiscal.defaults.fiscal_condition', 'monotributo');
    }
}
