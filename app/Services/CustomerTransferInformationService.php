<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Customer transfer data; never changes vendor payment method configuration. */
class CustomerTransferInformationService
{
    public function sender(array $data): array
    {
        $phone = app(EgyptPhoneService::class);
        $number = (string) ($data['sender_phone'] ?? $data['sender_identifier'] ?? '');
        $name = trim((string) ($data['sender_name'] ?? ''));
        if (!$phone->isValid($number)) {
            throw ValidationException::withMessages(['sender_phone' => 'أدخل رقم موبايل مصري من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015']);
        }
        if ($name === '' || mb_strlen($name) > 150) {
            throw ValidationException::withMessages(['sender_name' => 'أدخل اسم المحفظة أو الحساب الذي حولت منه، بحد أقصى 150 حرفًا.']);
        }
        return ['sender_phone' => $phone->normalizeLocal($number), 'sender_name' => $name];
    }

    public function recipientIsNumber(array $field): bool
    {
        $label = (string) ($field['input_name'] ?? '');
        if (preg_match('/name|owner|holder|اسم|صاحب/iu', $label)) return false;
        return (bool) preg_match('/number|phone|mobile|account|wallet|instapay|address|رقم|محفظ|حساب|عنوان/iu', $label);
    }
}
