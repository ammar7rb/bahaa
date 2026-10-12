<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonthlySubscriptionPricingService
{
    public function get(): array
    {
        $row = DB::table('monthly_subscription_prices')->find(1);
        return [
            'customer_price' => $row?->customer_price,
            'vendor_price' => $row?->vendor_price,
            'currency_code' => $row?->currency_code ?? getCurrencyCode(type: 'default'),
        ];
    }

    public function update(array $data, int $adminId): void
    {
        foreach (['customer_price','vendor_price'] as $field) {
            if (($data[$field] ?? null) !== null && (!is_numeric($data[$field]) || !is_finite((float) $data[$field]) || (float) $data[$field] <= 0)) {
                throw ValidationException::withMessages([$field => 'أدخل مبلغًا أكبر من صفر أو اترك السعر غير محدد.']);
            }
        }
        // The admin enters amounts in the current default currency, matching
        // the platform's other price settings; the payment snapshots currency.
        DB::table('monthly_subscription_prices')->updateOrInsert(['id' => 1], [
            'customer_price' => $data['customer_price'] ?? null,
            'vendor_price' => $data['vendor_price'] ?? null,
            'currency_code' => getCurrencyCode(type: 'default'),
            'updated_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
