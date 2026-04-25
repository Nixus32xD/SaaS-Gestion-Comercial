<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class UploadFiscalCertificateRequest extends FormRequest
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
            'certificate' => ['required', 'string'],
            'certificate_file' => ['nullable', 'file', 'max:128'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $certificate = trim((string) $this->input('certificate'));

        if ($this->hasFile('certificate_file') && $this->file('certificate_file')?->isValid()) {
            $certificate = trim((string) $this->file('certificate_file')->get());
        }

        $this->merge([
            'certificate' => $certificate,
        ]);
    }
}
