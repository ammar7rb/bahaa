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

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:sellers,email',
            'phone' => ['required', 'regex:/^01[0125][0-9]{8}$/', new \App\Rules\UniqueSellerPhone],
            'password' => 'required|min:8',
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
            'password.min' => translate('Password must be at least 8 characters.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => app(EgyptPhoneService::class)->normalizeLocal($this->input('phone')),
        ]);
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
