<?php

namespace App\Http\Requests\Purchases;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $items = collect((array) $this->input('items', []))
            ->map(function ($item): array {
                $row = (array) $item;
                $row['batch_code'] = trim((string) ($row['batch_code'] ?? '')) ?: null;

                return $row;
            })
            ->all();

        $this->merge([
            'notes' => trim((string) $this->input('notes')),
            'items' => $items,
            'fiscal' => [
                ...((array) $this->input('fiscal', [])),
                'supplier_cuit' => preg_replace('/\D+/', '', (string) data_get($this->input('fiscal', []), 'supplier_cuit')) ?: null,
            ],
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fiscalDocumentTypes = [
            ...array_column(config('fiscal.document_types', []), 'value'),
            'invoice_m',
            'ticket',
        ];

        return [
            'supplier_id' => ['nullable', 'integer'],
            'purchased_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'gte:0'],
            'items.*.batch_code' => ['nullable', 'string', 'max:80'],
            'items.*.expires_at' => ['nullable', 'date'],
            'items.*.product' => ['nullable', 'array'],
            'items.*.product.global_product_id' => ['nullable', 'integer', 'exists:global_products,id'],
            'items.*.product.category_id' => ['nullable', 'integer'],
            'items.*.product.name' => ['nullable', 'string', 'max:150'],
            'items.*.product.barcode' => ['nullable', 'string', 'max:120'],
            'items.*.product.sku' => ['nullable', 'string', 'max:120'],
            'items.*.product.unit_type' => ['nullable', 'in:unit,weight'],
            'items.*.product.weight_unit' => ['nullable', 'in:kg,g'],
            'items.*.product.sale_price' => ['nullable', 'numeric', 'gte:0'],
            'items.*.product.vat_treatment' => ['nullable', 'in:gravado,exento,no_gravado'],
            'items.*.product.vat_rate' => ['nullable', 'numeric', 'in:0,2.5,5,10.5,21,27'],
            'items.*.product.min_stock' => ['nullable', 'numeric', 'gte:0'],
            'items.*.product.shelf_life_days' => ['nullable', 'integer', 'gte:1', 'lte:3650'],
            'items.*.product.expiry_alert_days' => ['nullable', 'integer', 'gte:1', 'lte:3650'],
            'fiscal' => ['nullable', 'array'],
            'fiscal.enabled' => ['nullable', 'boolean'],
            'fiscal.supplier_cuit' => ['required_if:fiscal.enabled,true', 'nullable', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'fiscal.document_type' => ['required_if:fiscal.enabled,true', 'nullable', 'string', Rule::in($fiscalDocumentTypes)],
            'fiscal.point_of_sale' => ['required_if:fiscal.enabled,true', 'nullable', 'integer', 'min:1', 'max:99999'],
            'fiscal.number' => ['required_if:fiscal.enabled,true', 'nullable', 'integer', 'min:1', 'max:99999999'],
            'fiscal.voucher_date' => ['required_if:fiscal.enabled,true', 'nullable', 'date', 'before_or_equal:today'],
            'fiscal.other_taxes_amount' => ['nullable', 'numeric', 'gte:0'],
            'fiscal.total_amount' => ['required_if:fiscal.enabled,true', 'nullable', 'numeric', 'gte:0'],
            'fiscal.items' => ['required_if:fiscal.enabled,true', 'array', 'min:1'],
            'fiscal.items.*.vat_treatment' => ['required_if:fiscal.enabled,true', 'string', Rule::in(['gravado', 'exento', 'no_gravado'])],
            'fiscal.items.*.vat_rate' => ['nullable', 'numeric', 'in:0,2.5,5,10.5,21,27'],
            'fiscal.items.*.net_amount' => ['required_if:fiscal.enabled,true', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(Validator $validator): void
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

            if (! $this->boolean('fiscal.enabled') || $validator->errors()->has('fiscal.supplier_cuit')) {
                return;
            }

            $cuit = (string) $this->input('fiscal.supplier_cuit');
            if (! in_array(substr($cuit, 0, 2), ['20', '23', '24', '27', '30', '33', '34'], true)) {
                $validator->errors()->add('fiscal.supplier_cuit', 'El CUIT tiene un prefijo invalido.');

                return;
            }

            $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            foreach ($weights as $index => $weight) {
                $sum += ((int) $cuit[$index]) * $weight;
            }

            $remainder = $sum % 11;
            $checkDigit = 11 - $remainder;
            $checkDigit = $checkDigit === 11 ? 0 : ($checkDigit === 10 ? 9 : $checkDigit);

            if ($checkDigit !== (int) $cuit[10]) {
                $validator->errors()->add('fiscal.supplier_cuit', 'El CUIT no es valido.');
            }
        });
    }
}
