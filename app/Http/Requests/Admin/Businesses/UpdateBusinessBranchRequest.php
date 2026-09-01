<?php

namespace App\Http\Requests\Admin\Businesses;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateBusinessBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = (int) $this->route('business')->id;
        /** @var Branch $branch */
        $branch = $this->route('branch');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('branches', 'code')
                    ->where('business_id', $businessId)
                    ->ignore($branch->id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $code = Str::slug((string) $this->input('code', $name));

        $this->merge([
            'name' => $name,
            'code' => $code,
            'address' => trim((string) $this->input('address')) ?: null,
            'phone' => trim((string) $this->input('phone')) ?: null,
            'email' => mb_strtolower(trim((string) $this->input('email'))) ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
