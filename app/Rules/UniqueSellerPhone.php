<?php

namespace App\Rules;

use App\Models\Seller;
use App\Services\EgyptPhoneService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueSellerPhone implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreSellerId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phones = app(EgyptPhoneService::class);
        if (!$phones->isValid($value)) return;
        $matches = Seller::query()->whereIn('phone', $phones->aliases($value));
        if ($this->ignoreSellerId !== null) $matches->where('id', '!=', $this->ignoreSellerId);
        if ($matches->exists()) $fail('هذا الرقم مسجل بالفعل لحساب تاجر آخر.');
    }
}
