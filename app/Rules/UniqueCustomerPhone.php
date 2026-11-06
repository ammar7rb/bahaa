<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueCustomerPhone implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreCustomerId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phones=app(\App\Services\EgyptPhoneService::class);
        if (!$phones->isValid($value)) return;
        $matches = \App\Models\User::where(function ($query) use ($phones, $value) {
            $query->whereIn('phone', $phones->aliases($value))->orWhere('canonical_phone', $phones->normalizeLocal($value));
        });
        if ($this->ignoreCustomerId !== null) $matches->where('id', '!=', $this->ignoreCustomerId);
        if ($matches->exists()) {
            $fail('هذا الرقم مسجل بالفعل. سجّل الدخول إلى حسابك.');
        }
    }
}
