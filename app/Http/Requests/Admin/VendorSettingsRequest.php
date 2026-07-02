<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VendorSettingsRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_forgot_password_method' => 'nullable|in:email,phone',
            'seller_insurance_status' => 'nullable|in:1',
            'seller_insurance_amount' => 'exclude_unless:seller_insurance_status,1|required|numeric|min:0.01|max:999999999999.99',
            'seller_insurance_repayment_after_forfeiture' => 'nullable|in:1',
        ];
    }

    public function messages(): array
    {
        return [
            'seller_insurance_amount.required' => translate('seller_insurance_amount_is_required_when_insurance_is_enabled'),
            'seller_insurance_amount.min' => translate('seller_insurance_amount_must_be_greater_than_zero'),
        ];
    }

}
