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
            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    'pending',
                    'under_review',
                    'approved',
                    'rejected',
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | ID Document
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
                'max:5120', // 5MB
            ],

            'id_document_path_back' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120', // 5MB
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
                'max:5120', // 5MB
            ],

            /*
            |--------------------------------------------------------------------------
            | Bank Information
            |--------------------------------------------------------------------------
            */

            'bank_name' => [
                'required',
                'string',
                'max:150',
            ],

            'account_number' => [
                'required',
                'string',
                'max:50',
            ],

            /*
            |--------------------------------------------------------------------------
            | Submission Information
            |--------------------------------------------------------------------------
            */

            'trigger_source' => [
                'sometimes',
                'string',
                Rule::in([
                    'user',
                    'admin',
                    'system',
                    'api',
                ]),
            ],

            'rejection_reason' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'submitted_ip' => [
                'nullable',
                'ip',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User is required.',
            'user_id.integer' => 'User ID must be a valid number.',
            'user_id.exists' => 'The selected user does not exist.',

            'status.in' => 'The selected KYC status is invalid.',

            /*
            |--------------------------------------------------------------------------
            | ID Document
            |--------------------------------------------------------------------------
            */

            'id_document_type.required' => 'ID document type is required.',
            'id_document_type.in' => 'The selected ID document type is invalid.',

            'id_document_number.required' => 'ID document number is required.',
            'id_document_number.string' => 'ID document number must be valid.',
            'id_document_number.max' => 'ID document number cannot exceed 100 characters.',

            'id_document_path_front.required' =>
                'Front image of the ID document is required.',

            'id_document_path_front.file' =>
                'Front ID document must be a valid uploaded file.',

            'id_document_path_front.image' =>
                'Front ID document must be an image.',

            'id_document_path_front.mimes' =>
                'Front ID document must be a JPG, JPEG, PNG, or WEBP image.',

            'id_document_path_front.max' =>
                'Front ID document cannot exceed 5MB.',

            'id_document_path_back.required' =>
                'Back image of the ID document is required.',

            'id_document_path_back.file' =>
                'Back ID document must be a valid uploaded file.',

            'id_document_path_back.image' =>
                'Back ID document must be an image.',

            'id_document_path_back.mimes' =>
                'Back ID document must be a JPG, JPEG, PNG, or WEBP image.',

            'id_document_path_back.max' =>
                'Back ID document cannot exceed 5MB.',

            /*
            |--------------------------------------------------------------------------
            | Selfie
            |--------------------------------------------------------------------------
            */

            'selfie_path.required' =>
                'A selfie is required.',

            'selfie_path.file' =>
                'Selfie must be a valid uploaded file.',

            'selfie_path.image' =>
                'Selfie must be an image.',

            'selfie_path.mimes' =>
                'Selfie must be a JPG, JPEG, PNG, or WEBP image.',

            'selfie_path.max' =>
                'Selfie cannot exceed 5MB.',

            /*
            |--------------------------------------------------------------------------
            | Bank Information
            |--------------------------------------------------------------------------
            */

            'bank_name.required' => 'Bank name is required.',
            'bank_name.string' => 'Bank name must be valid.',
            'bank_name.max' => 'Bank name cannot exceed 150 characters.',

            'account_number.required' => 'Bank account number is required.',
            'account_number.string' => 'Bank account number must be valid.',
            'account_number.max' => 'Bank account number cannot exceed 50 characters.',

            /*
            |--------------------------------------------------------------------------
            | Other
            |--------------------------------------------------------------------------
            */

            'trigger_source.in' => 'The selected trigger source is invalid.',

            'rejection_reason.string' => 'Rejection reason must be valid text.',
            'rejection_reason.max' =>
                'Rejection reason cannot exceed 1000 characters.',

            'submitted_ip.ip' =>
                'The submitted IP address is invalid.',
        ];
    }
}