<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'batch_code' => trim((string) $this->input('batch_code')),
            'reason' => trim((string) $this->input('reason')) ?: null,
            'unit_cost' => $this->filled('unit_cost')
                ? $this->input('unit_cost')
                : null,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = (int) $this->user()->business_id;
        $productId = (int) $this->route('product')->id;
        $batchId = $this->route('batch')->id;

        return [
            'batch_code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('product_batches', 'batch_code')
                    ->ignore($batchId)
                    ->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->where('product_id', $productId)),
            ],
            'expires_at' => ['nullable', 'date'],
            'unit_cost' => ['nullable', 'numeric', 'gte:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
