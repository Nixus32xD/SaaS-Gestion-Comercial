<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use App\Support\ProductMeasurement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBusinessUser() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason')),
            'notes' => trim((string) $this->input('notes')) ?: null,
            'batch_code' => trim((string) $this->input('batch_code')) ?: null,
            'unit_cost' => $this->filled('unit_cost') ? $this->input('unit_cost') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'delta' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:80', 'in:physical_count,breakage,expiration,shrinkage,data_entry_error,found_stock,administrative_correction,other'],
            'notes' => ['nullable', 'string', 'max:2000', 'required_if:reason,other'],
            'batch_code' => ['nullable', 'string', 'max:80'],
            'expires_at' => ['nullable', 'date'],
            'unit_cost' => ['nullable', 'numeric', 'gte:0'],
            'expected_branch_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Product|null $product */
            $product = $this->route('product');
            if (! $product instanceof Product || $validator->errors()->has('delta')) {
                return;
            }

            $valid = ProductMeasurement::respectsQuantityPrecision(
                $product->unit_type,
                $product->weight_unit,
                (float) $this->input('delta'),
            );

            if (! $valid) {
                $validator->errors()->add('delta', 'La cantidad no respeta la unidad de medida del producto.');
            }
        });
    }
}
