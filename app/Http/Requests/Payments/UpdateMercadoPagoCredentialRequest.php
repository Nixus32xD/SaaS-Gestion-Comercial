<?php

namespace App\Http\Requests\Payments;

use App\Models\BusinessMercadoPagoCredential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMercadoPagoCredentialRequest extends FormRequest
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
            'is_enabled' => ['required', 'boolean'],
            'environment' => ['required', Rule::in(['testing', 'production'])],
            'public_key' => ['nullable', 'string', 'max:2000'],
            'access_token' => ['nullable', 'string', 'max:2000'],
            'webhook_secret' => ['nullable', 'string', 'max:2000'],
            'point_terminal_id' => ['nullable', 'string', 'max:160'],
            'point_store_id' => ['nullable', 'string', 'max:80'],
            'point_pos_id' => ['nullable', 'string', 'max:80'],
            'point_external_store_id' => ['nullable', 'string', 'max:80'],
            'point_external_pos_id' => ['nullable', 'string', 'max:80'],
            'point_expiration_time' => ['nullable', 'string', 'max:20', 'regex:/^PT(?=\\d)(?:\\d+H)?(?:\\d+M)?(?:\\d+S)?$/'],
            'point_print_on_terminal' => ['required', Rule::in(['no_ticket', 'seller_ticket'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $environment = strtolower(trim((string) $this->input('environment', 'testing')));

        $this->merge([
            'is_enabled' => $this->boolean('is_enabled'),
            'environment' => in_array($environment, ['testing', 'production'], true) ? $environment : 'testing',
            'public_key' => trim((string) $this->input('public_key', '')),
            'access_token' => trim((string) $this->input('access_token', '')),
            'webhook_secret' => trim((string) $this->input('webhook_secret', '')),
            'point_terminal_id' => trim((string) $this->input('point_terminal_id', '')),
            'point_store_id' => trim((string) $this->input('point_store_id', '')),
            'point_pos_id' => trim((string) $this->input('point_pos_id', '')),
            'point_external_store_id' => trim((string) $this->input('point_external_store_id', '')),
            'point_external_pos_id' => trim((string) $this->input('point_external_pos_id', '')),
            'point_expiration_time' => trim((string) $this->input('point_expiration_time', 'PT15M')) ?: 'PT15M',
            'point_print_on_terminal' => $this->input('point_print_on_terminal') === 'seller_ticket'
                ? 'seller_ticket'
                : 'no_ticket',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_enabled')) {
                return;
            }

            $credential = BusinessMercadoPagoCredential::query()
                ->where('business_id', (int) ($this->user()?->business_id ?? 0))
                ->first();

            if (! $this->filled('access_token') && blank($credential?->access_token)) {
                $validator->errors()->add('access_token', 'El access token es obligatorio para habilitar Point.');
            }

            if (! $this->filled('point_terminal_id') && blank($credential?->point_terminal_id)) {
                $validator->errors()->add('point_terminal_id', 'La terminal Point es obligatoria para habilitar Point.');
            }
        });
    }
}
