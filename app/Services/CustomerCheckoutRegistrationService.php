<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerCheckoutRegistrationService
{
    public function register(array $input): User
    {
        $phones = app(EgyptPhoneService::class);
        if (is_string($input['name'] ?? null)) $input['name'] = trim($input['name']);
        $input['phone'] = $phones->normalizeLocal($input['phone'] ?? '');
        $data = Validator::make($input, [
            'name' => 'required|string|max:100',
            'phone' => ['required', 'regex:/^01[0125][0-9]{8}$/'],
            'password' => 'required|string|min:6|max:128',
        ], [
            'name.required' => 'اكتب اسمك للمتابعة.',
            'phone.required' => 'أدخل رقم الموبايل.',
            'phone.regex' => 'أدخل رقم موبايل مصري من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015',
            'password.required' => 'أدخل كلمة المرور.',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
        ])->validate();

        try {
        return DB::transaction(function () use ($data, $phones) {
            if (User::query()->whereIn('phone', $phones->aliases($data['phone']))->orWhere('canonical_phone', $data['phone'])->exists()) {
                throw ValidationException::withMessages(['phone' => 'هذا الرقم مسجل بالفعل. سجّل الدخول بكلمة المرور للحفاظ على حسابك وسلتك.']);
            }
            $user = User::query()->create([
                'name' => trim($data['name']), 'f_name' => trim($data['name']), 'l_name' => '',
                'phone' => $data['phone'], 'canonical_phone' => $data['phone'], 'email' => null,
                'password' => Hash::make($data['password']), 'is_active' => 1,
                'is_phone_verified' => 0, 'is_email_verified' => 0,
                'activation_status' => 'active', 'onboarding_journey' => 'checkout_first',
            ]);
            return $user;
        });
        } catch (\Illuminate\Database\QueryException $error) {
            if (($error->errorInfo[0] ?? '') === '23000'
                && User::query()->where('canonical_phone', $data['phone'])->exists()) {
                throw ValidationException::withMessages(['phone' => 'هذا الرقم مسجل بالفعل. سجّل الدخول إلى حسابك.']);
            }
            throw $error;
        }
    }
}
