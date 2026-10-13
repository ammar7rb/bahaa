<?php

namespace App\Http\Requests\Web;

use App\Traits\CalculatorTrait;
use App\Traits\RecaptchaTrait;
use App\Traits\ResponseHandler;
use App\Services\EgyptPhoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomerProfileUpdateRequest extends FormRequest
{
    use RecaptchaTrait;
    use CalculatorTrait, ResponseHandler;

    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['nullable','string','min:8','max:255'],
            'current_password' => ['required_with:password','nullable','string','max:255'],
            'password_confirmation' => ['required_with:password','nullable','string','same:password'],
            'f_name' => 'required',
            'l_name' => 'required',
            'phone' => [
                'required',
                'regex:/^01[0125][0-9]{8}$/',
                new \App\Rules\UniqueCustomerPhone(auth('customer')->id()),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(auth('customer')->id(), 'id'),
            ],
            'image' => getRulesStringForImageValidation(
                rules: ['nullable'],
                skipMimes: ['.svg', '.gif'],
                maxSize: getFileUploadMaxSize(unit: 'kb'),
                isDisallowed: true
            ),
        ];
    }

    public function messages(): array
    {
        return [
            'f_name.required' => translate('first_name_is_required'),
            'current_password.required_with'=>'أدخل كلمة المرور الحالية لتغييرها.',
            'password.min'=>'كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف.',
            'password_confirmation.same'=>'تأكيد كلمة المرور لا يطابق الجديدة.',
            'l_name.required' => translate('last_name_is_required'),
            'phone.required' => translate('phone_number_is_required'),
            'phone.unique' => translate('phone_number_already_has_been_taken'),
            'phone.regex' => 'أدخل رقم موبايل مصري من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015',
            'image.mimes' => translate('The_image_type_must_be') . '.jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff,.webp',
            'image.max' => translate('The_image_may_not_be_greater_than_2_MB')
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this['password'] && !empty($this['password']) && empty($this['confirm_password'])) {
                    $validator->errors()->add(
                        'confirm_password.required', translate('confirm_password_is_required')
                    );
                } elseif ($this['confirm_password'] && !empty($this['confirm_password']) && empty($this['password'])) {
                    $validator->errors()->add(
                        'password.required', translate('password_is_required')
                    );
                } elseif ($this['password'] != $this['confirm_password']) {
                    $validator->errors()->add(
                        'password.same:confirm_password', translate('passwords_must_match_with_confirm_password')
                    );
                }
            }
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'password_confirmation' => $this->input('confirm_password'),
            'phone' => app(EgyptPhoneService::class)->normalizeLocal($this->input('phone')),
        ]);
    }
}
