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

        return [
            'advanced_sale_settings_enabled' => ['required', 'boolean'],
            'global_product_catalog_enabled' => ['required', 'boolean'],
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
        $this->merge([
            'advanced_sale_settings_enabled' => $this->boolean('advanced_sale_settings_enabled'),
            'global_product_catalog_enabled' => $this->boolean('global_product_catalog_enabled'),
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
        });
    }
}
