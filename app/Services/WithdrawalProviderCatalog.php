<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class WithdrawalProviderCatalog
{
    public static function wallets(): array
    {
        return [
            'vodafone_cash' => ['ar' => 'فودافون كاش', 'en' => 'Vodafone Cash'],
            'etisalat_cash' => ['ar' => 'اتصالات كاش', 'en' => 'Etisalat Cash'],
            'orange_cash' => ['ar' => 'اورانج كاش', 'en' => 'Orange Cash'],
            'we_cash' => ['ar' => 'وي كاش', 'en' => 'WE Cash'],
        ];
    }

    public static function provider(string $value): string
    {
        $value = trim($value);
        foreach (self::wallets() as $key => $labels) {
            if ($value === $key || mb_strtolower($value) === mb_strtolower($labels['en']) || $value === $labels['ar']) return $key;
        }
        throw ValidationException::withMessages(['method_info.wallet_provider' => translate('choose_wallet_provider')]);
    }

    public static function label(string $value): string
    {
        $labels = self::wallets()[$value] ?? null;
        return $labels ? (getDefaultLanguage() === 'en' ? $labels['en'] : $labels['ar']) : $value;
    }
}
