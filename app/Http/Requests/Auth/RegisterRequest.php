<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'max:100',
 ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'phone' => [
                'required',
                'max:30',
                'unique:users,phone',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in(['active', 'inactive', 'suspended']),
            ],

            'country' => [
                'required',
                'string',
                'max:100',
            ],

            'account_type' => [
                'required',
                'string',
                Rule::in(['individual', 'entity']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'first_name.string' => 'First name must be a valid text.',
            'first_name.max' => 'First name cannot exceed 100 characters.',

            'last_name.required' => 'Last name is required.',
            'last_name.string' => 'Last name must be a valid text.',
            'last_name.max' => 'Last name cannot exceed 100 characters.',

            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email address cannot exceed 255 characters.',
            'email.unique' => 'This email address is already registered.',

            'phone.required' => 'Phone number is required.',
            'phone.string' => 'Phone number must be valid.',
            'phone.max' => 'Phone number cannot exceed 30 characters.',
            'phone.unique' => 'This phone number is already registered.',

            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a valid string.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',

            'country.required' => 'Country is required.',
            'country.string' => 'Country must be a valid text.',
            'country.max' => 'Country cannot exceed 100 characters.',

            'account_type.required' => 'Account type is required.',
            'account_type.in' => 'The selected account type is invalid.',

        ];
    }


}
