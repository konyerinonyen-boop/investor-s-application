<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;

class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // This is the bank selected from the dropdown.
            // It is not a value the investor has to know.
            'bank_code' => [
                'required',
                'string',
                'max:20',
            ],

            'account_number' => [
                'required',
                'string',
                'regex:/^[0-9]{10}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_code.required' =>
                'Please select your bank.',

            'account_number.required' =>
                'Please enter your account number.',

            'account_number.regex' =>
                'Your account number must contain exactly 10 digits.',
        ];
    }
}
