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
            'slug' => trim((string) $this->input('slug')),
            'description' => trim((string) $this->input('description')),
            'barcode' => trim((string) $this->input('barcode')),
            'sku' => trim((string) $this->input('sku')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:170'],
            'description' => ['nullable', 'string', 'max:1000'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:120'],
            'unit_type' => ['required', Rule::in(['unit', 'weight'])],
            'sale_price' => ['required', 'numeric', 'gte:0'],
            'cost_price' => ['required', 'numeric', 'gte:0'],
            'stock' => ['nullable', 'numeric', 'gte:0'],
            'min_stock' => ['nullable', 'numeric', 'gte:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

