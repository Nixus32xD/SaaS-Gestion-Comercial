<?php

namespace App\Http\Requests\Admin\Businesses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBranchCommercialSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $businessId = (int) $this->route('business')->id;
        $branchId = (int) $this->route('branch')->id;

        return [
            'advanced_sale_settings_enabled' => ['required', 'boolean'],
            'sale_sectors' => ['nullable', 'array'],
            'sale_sectors.*.id' => ['nullable', 'integer', Rule::exists('business_sale_sectors', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('branch_id', $branchId))],
            'sale_sectors.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'sale_sectors.*.description' => ['nullable', 'string', 'max:255'],
            'sale_sectors.*.is_active' => ['required', 'boolean'],
            'payment_destinations' => ['nullable', 'array'],
            'payment_destinations.*.id' => ['nullable', 'integer', Rule::exists('business_payment_destinations', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('branch_id', $branchId))],
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
            'sale_sectors' => $this->normalized('sale_sectors', ['description']),
            'payment_destinations' => $this->normalized('payment_destinations', ['account_holder', 'reference', 'account_number']),
        ]);
    }

    /** @param list<string> $optionalFields @return list<array<string, mixed>> */
    private function normalized(string $field, array $optionalFields): array
    {
        return collect((array) $this->input($field, []))
            ->map(function (array $item) use ($optionalFields): array {
                $normalized = [
                    'id' => $item['id'] ?? null,
                    'name' => trim((string) ($item['name'] ?? '')),
                    'is_active' => filter_var($item['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                ];

                foreach ($optionalFields as $optionalField) {
                    $normalized[$optionalField] = trim((string) ($item[$optionalField] ?? ''));
                }

                return $normalized;
            })
            ->filter(fn (array $item): bool => $item['id'] !== null || $item['name'] !== '' || collect($optionalFields)->contains(fn (string $key): bool => $item[$key] !== ''))
            ->values()
            ->all();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('advanced_sale_settings_enabled')) {
                return;
            }

            if (collect($this->input('sale_sectors', []))->where('is_active', true)->isEmpty()) {
                $validator->errors()->add('sale_sectors', 'Debes configurar al menos un sector activo para habilitar esta función.');
            }

            if (collect($this->input('payment_destinations', []))->where('is_active', true)->isEmpty()) {
                $validator->errors()->add('payment_destinations', 'Debes configurar al menos un destino de cobro activo para habilitar esta función.');
            }
        });
    }
}
