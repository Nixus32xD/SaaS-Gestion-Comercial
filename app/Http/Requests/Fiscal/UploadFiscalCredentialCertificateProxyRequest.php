<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UploadFiscalCredentialCertificateProxyRequest extends FormRequest
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
            'credential_id' => ['required', 'integer', 'min:1'],
            'certificate' => ['required', 'string', 'max:20000'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator, back()->withErrors($validator));
    }
}
