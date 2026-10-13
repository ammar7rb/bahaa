<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Validation\ValidationException;

class CustomerContactPhoneService
{
    public function list(int $customerId): array
    {
        return Schema::hasTable('customer_contact_phones') ? DB::table('customer_contact_phones')->where('customer_id', $customerId)->orderBy('id')->pluck('phone')->all() : [];
    }

    public function replace(int $customerId, array $phones): array
    {
        $normalizer = app(EgyptPhoneService::class);
        $phones = array_map(fn ($phone) => is_string($phone) ? $normalizer->normalizeLocal($phone) : $phone, $phones);
        Validator::make(['phones' => $phones], ['phones' => 'array|max:20', 'phones.*' => 'required|string|regex:/^01[0125][0-9]{8}$/|distinct'],
            ['phones.*.regex'=>'أدخل رقم موبايل مصري صحيح من 11 رقماً.','phones.*.distinct'=>'أرقام التواصل يجب ألا تتكرر.','phones.*.required'=>'أدخل الرقم أو أزل الحقل الفارغ.'])->validate();
        return DB::transaction(function () use ($customerId, $phones, $normalizer) {
            $user = User::lockForUpdate()->findOrFail($customerId);
            if (in_array($normalizer->normalizeLocal($user->phone), $phones, true)) throw ValidationException::withMessages(['phones' => 'الرقم الأساسي ظاهر بالفعل؛ أضف أرقام تواصل مختلفة.']);
            $previous = $this->list($customerId);
            if ($previous === $phones) return $previous;
            DB::table('customer_contact_phones')->where('customer_id', $customerId)->delete();
            foreach ($phones as $phone) DB::table('customer_contact_phones')->insert(['customer_id' => $customerId, 'phone' => $phone, 'created_at' => now(), 'updated_at' => now()]);
            app(CustomerProfileAuditService::class)->record($customerId, 'profile', $customerId, 'contact_phones_updated', ['contact_phones' => ['from' => $previous, 'to' => $phones]]);
            return $phones;
        });
    }
}
