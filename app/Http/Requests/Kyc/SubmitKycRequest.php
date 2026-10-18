<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Identity Document
            |--------------------------------------------------------------------------
            */

            'id_document_type' => [
                'required',
                'string',
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

            'id_document_path_front' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'id_document_path_back' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            /*
            |--------------------------------------------------------------------------
            | Selfie
            |--------------------------------------------------------------------------
            */

            'selfie_path' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            /*
            |--------------------------------------------------------------------------
            | Bank Account
            |--------------------------------------------------------------------------
            */

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
                'max:50',
                'regex:/^[0-9]+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_document_type.required' =>
                'Please select your identity document type.',

            'id_document_type.in' =>
                'The selected identity document type is not supported.',

            'id_document_number.required' =>
                'Your identity document number is required.',

            'id_document_path_front.required' =>
                'Please upload the front of your identity document.',

            'id_document_path_front.image' =>
                'The front document must be an image.',

            'id_document_path_front.mimes' =>
                'The front document must be JPG, JPEG, PNG, or WEBP.',

            'id_document_path_front.max' =>
                'The front document cannot exceed 5MB.',

            'id_document_path_back.required' =>
                'Please upload the back of your identity document.',

            'id_document_path_back.image' =>
                'The back document must be an image.',

            'id_document_path_back.mimes' =>
                'The back document must be JPG, JPEG, PNG, or WEBP.',

            'id_document_path_back.max' =>
                'The back document cannot exceed 5MB.',

            'selfie_path.required' =>
                'Please upload your selfie.',

            'selfie_path.image' =>
                'Your selfie must be an image.',

            'selfie_path.mimes' =>
                'Your selfie must be JPG, JPEG, PNG, or WEBP.',

            'selfie_path.max' =>
                'Your selfie cannot exceed 5MB.',

            'bank_name.required' =>
                'Please select your bank.',

            'bank_code.required' =>
                'The selected bank code is required.',

            'account_number.required' =>
                'Your bank account number is required.',

            'account_number.regex' =>
                'Your account number must contain digits only.',
        ];
    }
}