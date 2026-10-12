<?php

namespace App\Services;

use App\Models\CustomerInsuranceLedgerEntry;
use App\Models\User;
use App\Utils\Convert;
use Illuminate\Support\Facades\DB;

class CustomerInsuranceDepositService
{
    /** Called only by the verified gateway success hook, never by the return URL. */
    public function complete($payment): void
    {
        if (($payment['is_paid'] ?? 0) != 1 || ($payment['attribute'] ?? '') !== 'customer_insurance_deposit') {
            return;
        }
        $customerId = (int) ($payment['payer_id'] ?? 0);
        $paymentId = (string) ($payment['id'] ?? '');
        $amount = (float) ($payment['payment_amount'] ?? 0);
        if (!$customerId || $paymentId === '' || !is_finite($amount) || $amount <= 0) {
            throw new \DomainException('invalid_customer_insurance_deposit');
        }
        $credit = round((float) Convert::usdPaymentModule($amount, $payment['currency_code']), 2);
        if (!is_finite($credit) || $credit <= 0) {
            throw new \DomainException('invalid_customer_insurance_deposit');
        }

        app(CustomerLegacyPaymentService::class)->record('insurance-deposit-'.$paymentId,$customerId,$amount,$payment['currency_code'],[
            'payment_id'=>$paymentId,'payment_method'=>$payment['payment_method'],'base_amount'=>$credit,
        ]);
    }
}
