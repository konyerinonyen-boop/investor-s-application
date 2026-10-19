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
            // Identity document details
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

            // Front of the document
            'id_document_path_front' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            // Back of the document
            'id_document_path_back' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
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
        ];
    }
}