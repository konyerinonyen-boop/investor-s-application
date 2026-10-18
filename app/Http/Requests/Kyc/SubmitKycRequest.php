<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Investor chooses one identity verification method.
            'identity_type' => [
                'required',
                Rule::in(['bvn', 'nin']),
            ],

            // Exactly 11 digits for either BVN or NIN.
            'identity_number' => [
                'required',
                'string',
                'regex:/^[0-9]{11}$/',
            ],

            // Existing identity document requirements.
            'id_document_type' => [
                'required',
                Rule::in([
                    'national_id',
                    'passport',
                    'drivers_license',
                    'voters_card',
                ]),
            ],

            'id_document_number' => [
                'required',
                'string',
                'max:100',
            ],

            'id_document_front' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'id_document_back' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'selfie' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            // Bank account details.
            'bank_name' => [
                'required',
                'string',
                'max:150',
            ],

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
            'identity_type.required' =>
                'Choose either BVN or NIN.',

            'identity_type.in' =>
                'Identity type must be BVN or NIN.',

            'identity_number.regex' =>
                'Your BVN or NIN must contain exactly 11 digits.',

            'account_number.regex' =>
                'Your bank account number must contain exactly 10 digits.',

            'id_document_front.required' =>
                'Upload the front of your identity document.',

            'id_document_back.required' =>
                'Upload the back of your identity document.',

            'selfie.required' =>
                'Upload your selfie for verification.',
        ];
    }
}
