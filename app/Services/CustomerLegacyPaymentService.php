<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CustomerLegacyPaymentService
{
    /** Verified old callbacks are retained for financial matching, never used
     * to recreate retired insurance or to grant a monthly subscription. */
    public function record(string $reference, ?int $customerId, float $amount, string $currency, array $snapshot): void
    {
        if ($reference === '' || !is_finite($amount) || $amount <= 0) throw new \DomainException('invalid_legacy_payment');
        DB::table('customer_legacy_payment_receipts')->insertOrIgnore([
            'reference' => $reference, 'customer_id' => $customerId, 'amount' => $amount,
            'currency_code' => $currency, 'snapshot' => json_encode($snapshot,JSON_UNESCAPED_UNICODE), 'received_at' => now(),
        ]);
    }
}
