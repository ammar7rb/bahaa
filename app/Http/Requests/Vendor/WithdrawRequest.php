<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'withdraw_method' => 'required_without:saved_method_id|integer',
            'saved_method_id' => 'required_without:withdraw_method|integer',
            'request_key' => 'nullable|uuid',
            'amount' => 'required|numeric|min:0.01',
        ];
    }
}
