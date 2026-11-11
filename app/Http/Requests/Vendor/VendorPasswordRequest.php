<?php

namespace App\Http\Requests\Vendor;

use App\Traits\ResponseHandler;
use Illuminate\Foundation\Http\FormRequest;

class VendorPasswordRequest extends FormRequest
{
    use ResponseHandler;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize():bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules():array
    {
        return [
            'password' => 'required|string|min:6|max:255|same:confirm_password',
            'confirm_password' => 'required',
        ];
    }
    public function messages(): array
    {
        return [
            'password.required' => translate('The_password_field_is_required'),
            'confirm_password.required' => translate('The_confirm_password_field_is_required'),
            'password.same' => translate('The_password_and_confirm_password_must_be_match'),
            'password.min' => 'كلمة المرور 6 أحرف على الأقل.',
        ];
    }
}
