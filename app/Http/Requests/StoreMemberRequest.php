<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('members.create');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['individual', 'group', 'institution'])],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'required_if:type,individual', 'string', 'max:100'],
            'nin' => ['nullable', 'string', 'max:20', Rule::unique('members', 'nin')],
            'date_of_birth' => ['nullable', 'required_if:type,individual', 'date', 'before:-18 years'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{9,15}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'district' => ['nullable', 'string', 'max:100'],
            'subcounty' => ['nullable', 'string', 'max:100'],
            'village' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'signature' => ['nullable', 'image', 'max:2048'],

            'next_of_kin' => ['array'],
            'next_of_kin.*.name' => ['required', 'string', 'max:150'],
            'next_of_kin.*.relationship' => ['required', 'string', 'max:50'],
            'next_of_kin.*.phone' => ['required', 'string', 'max:20'],
            'next_of_kin.*.nin' => ['nullable', 'string', 'max:20'],
            'next_of_kin.*.address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Member must be at least 18 years old.',
        ];
    }
}
