<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBusinessUser() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $vatTreatment = $this->filled('vat_treatment')
            ? trim((string) $this->input('vat_treatment'))
            : (string) config('fiscal.defaults.vat_treatment', 'gravado');

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => trim((string) $this->input('slug')) ?: null,
            'description' => trim((string) $this->input('description')),
            'barcode' => trim((string) $this->input('barcode')) ?: null,
            'sku' => trim((string) $this->input('sku')) ?: null,
            'weight_unit' => $this->filled('weight_unit') ? trim((string) $this->input('weight_unit')) : null,
            'vat_treatment' => $vatTreatment,
            'vat_rate' => $vatTreatment === 'gravado' ? $this->input('vat_rate', config('fiscal.defaults.vat_rate', 21)) : 0,
        ]);
    }

    public function rules(): array
    {
        $businessId = (int) $this->user()->business_id;
        $productId = (int) $this->route('product')->id;

        return [
            'category_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:170'],
            'description' => ['nullable', 'string', 'max:1000'],
            'barcode' => ['nullable', 'string', 'max:120', Rule::unique('products', 'barcode')->ignore($productId)->where(fn ($query) => $query->where('business_id', $businessId))],
            'sku' => ['nullable', 'string', 'max:120', Rule::unique('products', 'sku')->ignore($productId)->where(fn ($query) => $query->where('business_id', $businessId))],
            'unit_type' => ['required', Rule::in(['unit', 'weight'])],
            'weight_unit' => [Rule::requiredIf(fn () => $this->input('unit_type') === 'weight'), 'nullable', Rule::in(['kg', 'g'])],
            'sale_price' => ['required', 'numeric', 'gte:0'],
            'cost_price' => ['required', 'numeric', 'gte:0'],
            'vat_treatment' => ['required', Rule::in(['gravado', 'exento', 'no_gravado'])],
            'vat_rate' => [Rule::requiredIf(fn () => $this->input('vat_treatment') === 'gravado'), 'numeric', 'in:0,2.5,5,10.5,21,27'],
            'min_stock' => ['nullable', 'numeric', 'gte:0'],
            'shelf_life_days' => ['nullable', 'integer', 'gte:1', 'lte:3650'],
            'expiry_alert_days' => ['nullable', 'integer', 'gte:1', 'lte:3650'],
            'is_active' => ['sometimes', 'boolean'],
            'edit_version' => ['required', 'integer', 'min:1'],
            'branch_stock_edit_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
