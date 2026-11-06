<?php

namespace App\Http\Requests\API\v3;

use App\Traits\ResponseHandler;
use App\Services\EgyptPhoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class SellerUpdateRequest extends FormRequest
{
    use ResponseHandler;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'f_name' => 'required|string|max:255',
            'l_name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^01[0125][0-9]{8}$/', new \App\Rules\UniqueSellerPhone($this->seller?->id)],
            'image' => getRulesStringForImageValidation(
                rules: ['nullable'],
                skipMimes: ['.svg', '.gif'],
                maxSize: getFileUploadMaxSize(unit: 'kb'),
                isDisallowed: true),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => app(EgyptPhoneService::class)->normalizeLocal($this->input('phone')),
            ]);
        }
    }

    public function messages(): array
    {
       return [
           'phone.regex' => 'أدخل رقم موبايل مصري من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015',
           'image.required' => translate('Profile image is required.'),
           'image.mimes' => translate('The profile image must be a file of type: ') . getFileUploadFormats(skip: ['.svg', '.gif'], asMessage: true),
           'image.max' => translate('The profile image may not be greater than ') . getFileUploadMaxSize() . ' MB.',

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
