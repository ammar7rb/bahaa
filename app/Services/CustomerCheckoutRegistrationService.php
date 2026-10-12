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
        $input['phone'] = $phones->normalizeLocal($input['phone'] ?? '');
        $data = Validator::make($input, [
            'name' => 'required|string|max:100',
            'phone' => ['required', 'regex:/^01[0125][0-9]{8}$/'],
            'password' => 'required|string|min:8|max:128',
            'terms_accepted' => 'required|accepted',
            'policy_version_ids' => 'nullable|array',
            'policy_version_ids.*' => 'integer',
        ], ['phone.regex' => 'أدخل رقم موبايل مصري من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015'])->validate();

        try {
        return DB::transaction(function () use ($data, $phones) {
            if (User::query()->whereIn('phone', $phones->aliases($data['phone']))->orWhere('canonical_phone', $data['phone'])->exists()) {
                throw ValidationException::withMessages(['phone' => 'هذا الرقم مسجل بالفعل. سجّل الدخول بكلمة المرور للحفاظ على حسابك وسلتك.']);
            }
            $policies = app(PolicyAcceptanceService::class);
            $ids = $policies->requiredFor('customer')->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (array_diff($ids, $data['policy_version_ids'] ?? [])) {
                throw ValidationException::withMessages(['policy_version_ids' => 'راجع الشروط والسياسات الحالية ووافق عليها قبل التسجيل.']);
            }
            $user = User::query()->create([
                'name' => trim($data['name']), 'f_name' => trim($data['name']), 'l_name' => '',
                'phone' => $data['phone'], 'canonical_phone' => $data['phone'], 'email' => null,
                'password' => Hash::make($data['password']), 'is_active' => 1,
                'is_phone_verified' => 0, 'is_email_verified' => 0,
                'activation_status' => 'pending_support', 'onboarding_journey' => 'checkout_first',
                'terms_accepted_at' => now(), 'privacy_accepted_at' => now(),
            ]);
            $policies->accept('customer', (int) $user->id, $ids);
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
