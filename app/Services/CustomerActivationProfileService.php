<?php
namespace App\Services;

use App\Models\AccountActivationCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerActivationProfileService
{
    public function update(AccountActivationCase $case, array $input, int $adminId): void
    {
        abort_unless($case->subject_type === AccountActivationCase::SUBJECT_CUSTOMER, 404);
        $input['phone'] = app(EgyptPhoneService::class)->normalizeLocal($input['phone'] ?? '');
        DB::transaction(function () use ($case, $input, $adminId) {
            $customer = User::lockForUpdate()->findOrFail($case->subject_id);
            $lockedCase = AccountActivationCase::lockForUpdate()->findOrFail($case->id);
            abort_if($lockedCase->isCompleted(), 409, 'تم إغلاق ملف التفعيل.');
            $data = Validator::make($input, [
                'name' => ['required','string','max:100'], 'phone' => ['required','regex:/^01[0125][0-9]{8}$/'],
                'email' => ['nullable','email:rfc','max:254',Rule::unique('users','email')->ignore($customer->id)],
            ], ['phone.regex'=>'أدخل رقم موبايل مصري من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015'])->validate();
            if (User::where('id','<>',$customer->id)->where(fn ($q) => $q->where('canonical_phone',$data['phone'])
                ->orWhereIn('phone',app(EgyptPhoneService::class)->aliases($data['phone'])))->exists()) {
                throw ValidationException::withMessages(['phone'=>'هذا الرقم مرتبط بحساب آخر.']);
            }
            $phoneChanged = $customer->phone !== $data['phone'];
            $emailChanged = $customer->email !== (($data['email'] ?? null) ?: null);
            $updates = ['name'=>trim($data['name']),'f_name'=>trim($data['name']),'l_name'=>'','phone'=>$data['phone'],
                'canonical_phone'=>$data['phone'],'email'=>($data['email'] ?? null) ?: null];
            if ($phoneChanged) $updates['is_phone_verified'] = 0;
            if ($emailChanged) $updates['is_email_verified'] = 0;
            $customer->forceFill($updates)->save();
            if ($phoneChanged) DB::table('oauth_access_tokens')->where('user_id',$customer->id)->update(['revoked'=>true]);
            app(AccountActivationCenterService::class)->saveField($lockedCase,[
                'label'=>'بيانات حساب العميل','value'=>json_encode(['name'=>$customer->name,'phone'=>$customer->phone,'email'=>$customer->email],JSON_UNESCAPED_UNICODE),
                'is_sensitive'=>true,'is_required'=>false,'is_verified'=>false,
            ],$adminId);
        }, 3);
    }
}
