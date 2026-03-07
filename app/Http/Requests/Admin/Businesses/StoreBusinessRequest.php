<?php

namespace App\Http\Requests\Admin\Businesses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => trim((string) $this->input('slug')),
            'owner_name' => trim((string) $this->input('owner_name')),
            'email' => trim((string) $this->input('email')),
            'phone' => trim((string) $this->input('phone')),
            'address' => trim((string) $this->input('address')),
            'admin' => [
                'name' => trim((string) data_get($this->input('admin'), 'name')),
                'email' => mb_strtolower(trim((string) data_get($this->input('admin'), 'email'))),
                'password' => (string) data_get($this->input('admin'), 'password'),
                'password_confirmation' => (string) data_get($this->input('admin'), 'password_confirmation'),
            ],
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:170', Rule::unique('businesses', 'slug')],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'admin.name' => ['required', 'string', 'max:150'],
            'admin.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin.password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}

