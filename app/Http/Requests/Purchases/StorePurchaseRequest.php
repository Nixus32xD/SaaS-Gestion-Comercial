<?php

namespace App\Http\Requests\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => trim((string) $this->input('notes')),
            'items' => $this->input('items', []),
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
            'purchased_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'gte:0'],
            'items.*.expires_at' => ['nullable', 'date'],
            'items.*.product' => ['nullable', 'array'],
            'items.*.product.name' => ['nullable', 'string', 'max:150'],
            'items.*.product.barcode' => ['nullable', 'string', 'max:120'],
            'items.*.product.sku' => ['nullable', 'string', 'max:120'],
            'items.*.product.unit_type' => ['nullable', 'in:unit,weight'],
            'items.*.product.weight_unit' => ['nullable', 'in:kg,g'],
            'items.*.product.sale_price' => ['nullable', 'numeric', 'gte:0'],
            'items.*.product.min_stock' => ['nullable', 'numeric', 'gte:0'],
            'items.*.product.shelf_life_days' => ['nullable', 'integer', 'gte:1', 'lte:3650'],
            'items.*.product.expiry_alert_days' => ['nullable', 'integer', 'gte:1', 'lte:3650'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $productId = data_get($item, 'product_id');
                $productName = trim((string) data_get($item, 'product.name'));

                if ($productId === null && $productName === '') {
                    $validator->errors()->add(
                        "items.$index.product.name",
                        'Debes seleccionar un producto existente o cargar un producto nuevo.'
                    );
                }
            }
        });
    }
}
