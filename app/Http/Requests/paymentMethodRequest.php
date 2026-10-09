<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class paymentMethodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    // public function authorize(): bool
    // {
    //     return false;
    // }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'investment_id'      => 'required',
            'method'      => 'required|string',
            'proof_image' => 'sometimes|nullable|image|max:2048',
        ];
    }

    #[Override]
    public function messages()
    {
        return[
            'method.required' => 'Select A Payment Method'
        ];  
    }
}
