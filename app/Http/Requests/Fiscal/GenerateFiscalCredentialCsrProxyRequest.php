<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class GenerateFiscalCredentialCsrProxyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBusinessAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key_name' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'common_name' => ['nullable', 'string', 'max:64'],
            'organization_name' => ['nullable', 'string', 'max:180'],
            'country_name' => ['nullable', 'string', 'size:2'],
        ];
    }
}
