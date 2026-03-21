<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => trim((string) $this->input('slug')) ?: null,
            'description' => trim((string) $this->input('description')),
            'barcode' => trim((string) $this->input('barcode')) ?: null,
            'sku' => trim((string) $this->input('sku')) ?: null,
            'weight_unit' => $this->filled('weight_unit')
                ? trim((string) $this->input('weight_unit'))
                : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = (int) $this->user()->business_id;
        $productId = $this->route('product')?->id;

        return [
            'global_product_id' => ['nullable', 'integer', 'exists:global_products,id'],
            'category_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:170'],
            'description' => ['nullable', 'string', 'max:1000'],
            'barcode' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('products', 'barcode')
                    ->ignore($productId)
                    ->where(fn ($query) => $query->where('business_id', $businessId)),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('products', 'sku')
                    ->ignore($productId)
                    ->where(fn ($query) => $query->where('business_id', $businessId)),
            ],
            'unit_type' => ['required', Rule::in(['unit', 'weight'])],
            'weight_unit' => [
                Rule::requiredIf(fn () => $this->input('unit_type') === 'weight'),
                'nullable',
                Rule::in(['kg', 'g']),
            ],
            'sale_price' => ['required', 'numeric', 'gte:0'],
            'cost_price' => ['required', 'numeric', 'gte:0'],
            'stock' => ['nullable', 'numeric', 'gte:0'],
            'min_stock' => ['nullable', 'numeric', 'gte:0'],
            'shelf_life_days' => ['nullable', 'integer', 'gte:1', 'lte:3650'],
            'expiry_alert_days' => ['nullable', 'integer', 'gte:1', 'lte:3650'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
