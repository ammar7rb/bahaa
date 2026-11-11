<?php

namespace App\Http\Requests\API\v3;

use App\Traits\ResponseHandler;
use App\Services\EgyptPhoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class SellerRegistrationRequest extends FormRequest
{
    use ResponseHandler;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = strtolower(trim((string) $this->input('email')));
        $this->merge([
            'email' => $email !== '' ? $email : null,
            'phone' => app(EgyptPhoneService::class)->normalizeLocal($this->input('phone')),
            'f_name' => trim((string) $this->input('f_name')),
        ]);
    }

    public function rules(): array
    {
        return [
            'f_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|unique:sellers,email',
            'phone' => ['required', 'regex:/^01[0125][0-9]{8}$/', new \App\Rules\UniqueSellerPhone],
            'password' => 'required|string|min:6|max:255',
            'shop_name' => 'nullable|string|max:255',
            'shop_address' => 'nullable|string|max:1000',
            'policy_version_ids' => 'nullable|array',
            'policy_version_ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => translate('Email is required.'),
            'email.unique' => translate('This email is already registered.'),
            'phone.unique' => translate('This phone number is already registered.'),
            'phone.regex' => 'أدخل رقم موبايل مصري من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015',
            'password.min' => 'كلمة المرور 6 أحرف على الأقل.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => $this->errorProcessor($validator)
            ], 403)
        );
    }
}
