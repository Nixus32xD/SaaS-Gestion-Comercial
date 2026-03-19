<?php

namespace App\Http\Requests\Sales;

use App\Models\BusinessFeature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = (int) ($this->user()?->business_id ?? 0);
        $advancedSaleSettingsEnabled = $businessId > 0
            && BusinessFeature::query()
                ->where('business_id', $businessId)
                ->where('feature', BusinessFeature::ADVANCED_SALE_SETTINGS)
                ->where('is_enabled', true)
                ->exists();

        return [
            'payment_method' => ['nullable', 'in:cash,transfer'],
            'sale_sector_id' => [
                $advancedSaleSettingsEnabled ? 'required' : 'nullable',
                'integer',
                Rule::exists('business_sale_sectors', 'id')->where(
                    fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->where('is_active', true)
                ),
            ],
            'payment_destination_id' => [
                $advancedSaleSettingsEnabled ? 'required' : 'nullable',
                'integer',
                Rule::exists('business_payment_destinations', 'id')->where(
                    fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->where('is_active', true)
                ),
            ],
            'amount_received' => ['nullable', 'numeric', 'gte:0'],
            'discount' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sold_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_method' => $this->filled('payment_method')
                ? (string) $this->input('payment_method')
                : null,
            'sale_sector_id' => $this->filled('sale_sector_id')
                ? (int) $this->input('sale_sector_id')
                : null,
            'payment_destination_id' => $this->filled('payment_destination_id')
                ? (int) $this->input('payment_destination_id')
                : null,
            'amount_received' => $this->filled('amount_received')
                ? $this->input('amount_received')
                : null,
        ]);
    }
}
