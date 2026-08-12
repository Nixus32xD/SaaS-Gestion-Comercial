<?php

namespace App\Http\Requests\Sales;

use App\Models\BusinessQuickSaleOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuickSaleOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBusinessAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:160'],
            'default_amount' => ['nullable', 'numeric', 'gte:0'],
            'vat_treatment' => ['required', Rule::in(['gravado', 'exento', 'no_gravado'])],
            'vat_rate' => ['required', 'numeric', 'in:0,2.5,5,10.5,21,27'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $vatTreatment = strtolower(trim((string) $this->input('vat_treatment', 'gravado')));

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'description' => trim((string) $this->input('description', '')) ?: null,
            'default_amount' => $this->filled('default_amount')
                ? $this->input('default_amount')
                : null,
            'vat_treatment' => $vatTreatment,
            'vat_rate' => $vatTreatment === 'gravado'
                ? ($this->filled('vat_rate') ? $this->input('vat_rate') : config('fiscal.defaults.vat_rate', 21))
                : 0,
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $businessId = (int) ($this->user()?->business_id ?? 0);

            if ($businessId <= 0 || $validator->errors()->isNotEmpty()) {
                return;
            }

            $activeOptions = BusinessQuickSaleOption::query()
                ->forBusiness($businessId)
                ->active()
                ->count();

            if ((bool) $this->boolean('is_active') && $activeOptions >= 16) {
                $validator->errors()->add(
                    'name',
                    'El comercio ya tiene el maximo de 16 opciones rapidas activas.'
                );
            }
        });
    }
}
