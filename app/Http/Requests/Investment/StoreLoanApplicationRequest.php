<?php

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_offer_id' => ['required', 'integer', 'exists:loan_offers,id'],
            'principal_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
