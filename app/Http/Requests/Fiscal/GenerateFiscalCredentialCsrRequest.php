<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class GenerateFiscalCredentialCsrRequest extends FormRequest
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
            'key_name' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9._-]+\.key$/'],
            'common_name' => ['required', 'string', 'max:120'],
            'organization_name' => ['required', 'string', 'max:255'],
            'country_name' => ['required', 'string', 'size:2'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key_name' => trim((string) $this->input('key_name')),
            'common_name' => trim((string) $this->input('common_name')),
            'organization_name' => trim((string) $this->input('organization_name')),
            'country_name' => mb_strtoupper(trim((string) $this->input('country_name'))),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key_name.regex' => 'El nombre de la key debe terminar en .key y usar solo letras, numeros, puntos, guiones o guiones bajos.',
        ];
    }
}
