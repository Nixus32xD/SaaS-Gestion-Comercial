<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBranchMercadoPagoPointSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBusinessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'point_terminal_id' => ['nullable', 'string', 'max:160'],
            'point_store_id' => ['nullable', 'string', 'max:80'],
            'point_pos_id' => ['nullable', 'string', 'max:80'],
            'point_external_store_id' => ['nullable', 'string', 'max:80'],
            'point_external_pos_id' => ['nullable', 'string', 'max:80'],
            'point_expiration_time' => ['nullable', 'string', 'max:20', 'regex:/^PT(?=\\d)(?:\\d+H)?(?:\\d+M)?(?:\\d+S)?$/'],
            'point_print_on_terminal' => ['nullable', Rule::in(['no_ticket', 'seller_ticket'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'point_terminal_id', 'point_store_id', 'point_pos_id', 'point_external_store_id',
            'point_external_pos_id', 'point_expiration_time', 'point_print_on_terminal',
        ] as $field) {
            $this->merge([$field => trim((string) $this->input($field, ''))]);
        }

        $this->merge(['is_enabled' => $this->boolean('is_enabled')]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('is_enabled') && ! $this->filled('point_terminal_id')) {
                $validator->errors()->add('point_terminal_id', 'La terminal Point es obligatoria para habilitarla en esta sucursal.');
            }
        });
    }
}
