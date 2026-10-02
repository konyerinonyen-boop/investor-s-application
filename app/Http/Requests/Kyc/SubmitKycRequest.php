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
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'routing_code' => ['nullable', 'string', 'max:255'],
            'document_path' => ['nullable', 'string'],
            'selfie_path' => ['nullable', 'string'],
            'review_notes' => ['nullable', 'string'],
        ];
    }
}
