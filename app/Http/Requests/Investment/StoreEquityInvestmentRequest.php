<?php

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquityInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equity_round_id' => ['required', 'integer', 'exists:equity_rounds,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
