<?php

namespace App\Services;

use App\Models\CustomerMonthlySubscription;
use App\Models\CustomerSubscriptionPayment;
use App\Models\OfflinePaymentMethod;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Utils\Convert;
use DomainException;
use Illuminate\Support\Facades\DB;

class CustomerMonthlySubscriptionService
{
    public function status(int $customerId): array
    {
        $subscription = CustomerMonthlySubscription::where('customer_id', $customerId)->latest('id')->first();
        $active = $subscription && $subscription->starts_at->lte(now()) && $subscription->expires_at->gt(now());
        $pricing = app(MonthlySubscriptionPricingService::class)->get();
        return [
            'status' => $active ? 'active' : ($subscription ? 'expired' : 'inactive'),
            'active' => (bool) $active,
            'remaining_days' => $active ? (int) ceil(now()->diffInSeconds($subscription->expires_at, false) / 86400) : 0,
            'starts_at' => $subscription?->starts_at->toIso8601String(),
            'expires_at' => $subscription?->expires_at->toIso8601String(),
            'price' => $pricing['customer_price'], 'currency_code' => $pricing['currency_code'],
            'payment_available' => (float) ($pricing['customer_price'] ?? 0) > 0,
            'open_payment' => CustomerSubscriptionPayment::where('open_customer_id', $customerId)->first(),
        ];
    }

    public function checkout(int $customerId, string $requestKey): CustomerSubscriptionPayment
    {
        return DB::transaction(function () use ($customerId, $requestKey) {
            User::query()->lockForUpdate()->findOrFail($customerId);
            $existing = CustomerSubscriptionPayment::where('customer_id', $customerId)->where('request_key', $requestKey)->first();
            if ($existing) return $existing;
            $open = CustomerSubscriptionPayment::where('open_customer_id', $customerId)->first();
            if ($open) return $open;
            $pricing = app(MonthlySubscriptionPricingService::class)->get();
            $amount = (float) ($pricing['customer_price'] ?? 0);
            if (!is_finite($amount) || $amount <= 0) throw new DomainException('لم تحدد الإدارة سعر الاشتراك بعد.');
            $debit = round((float) Convert::usdPaymentModule($amount, $pricing['currency_code']), 3);
            if (!is_finite($debit) || $debit <= 0) throw new DomainException('تعذر تحديد قيمة الدفع. تواصل مع الإدارة.');
            return CustomerSubscriptionPayment::create([
                'customer_id' => $customerId, 'open_customer_id' => $customerId,
                'request_key' => $requestKey, 'quoted_amount' => $amount,
                'wallet_debit_amount' => $debit, 'currency_code' => $pricing['currency_code'],
            ])->refresh();
        }, 3);
    }

    public function payFromWallet(int $customerId, int $paymentId): CustomerSubscriptionPayment
    {
        return DB::transaction(function () use ($customerId, $paymentId) {
            $customer = User::query()->lockForUpdate()->findOrFail($customerId);
            $payment = CustomerSubscriptionPayment::where('customer_id', $customerId)->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status === 'approved') return $payment;
            if ($payment->status !== 'draft') throw new DomainException('طلب الدفع قيد المراجعة أو تم رفضه.');
            if (!$customer->is_active) throw new DomainException('الحساب موقوف إداريًا. تواصل مع الدعم.');
            if (getWebConfig('wallet_status') != 1) throw new DomainException('محفظة المشتريات غير متاحة حاليًا.');
            $amount = (int) round((float) $payment->wallet_debit_amount * 1000);
            $balance = (int) round((float) $customer->wallet_balance * 1000);
            if ($balance < $amount) throw new DomainException('عذرًا، رصيد محفظة المشتريات لا يكفي. برجاء الإيداع فيها أو استخدام طريقة أخرى.');
            $reference = 'customer-monthly-subscription-'.$payment->id;
            $customer->wallet_balance = ($balance - $amount) / 1000;
            $customer->save();
            WalletTransaction::create([
                'user_id' => $customerId, 'transaction_id' => $reference, 'reference' => $reference,
                'transaction_type' => 'monthly_subscription', 'payment_method' => 'purchase_wallet',
                'credit' => 0, 'debit' => $amount / 1000, 'admin_bonus' => 0, 'balance' => $customer->wallet_balance,
            ]);
            $payment->update(['payment_method' => 'purchase_wallet', 'payment_reference' => $reference, 'submitted_at' => now()]);
            return $this->approveLocked($payment, null, 'خصم مؤكد من رصيد المشتريات المعتمد');
        }, 3);
    }

    public function submitOffline(int $customerId, int $paymentId, OfflinePaymentMethod $method, array $data, string $proofPath): CustomerSubscriptionPayment
    {
        return DB::transaction(function () use ($customerId, $paymentId, $method, $data, $proofPath) {
            $customer = User::query()->lockForUpdate()->findOrFail($customerId);
            $payment = CustomerSubscriptionPayment::where('customer_id', $customerId)->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== 'draft') throw new DomainException('تم تقديم طلب الدفع بالفعل.');
            if (!$customer->is_active) throw new DomainException('الحساب موقوف إداريًا. تواصل مع الدعم.');
            $method = OfflinePaymentMethod::where('status', 1)->whereIn('payment_channel', ['wallet','instapay'])->findOrFail($method->id);
            if (isset($data['transferred_amount']) && (int) round((float) $data['transferred_amount'] * 1000) !== (int) round((float) $payment->quoted_amount * 1000)) {
                throw new DomainException('مبلغ التحويل يجب أن يساوي مبلغ الاشتراك المطلوب.');
            }
            $information = [];
            foreach ($method->method_informations ?? [] as $field) {
                $key = $field['customer_input'] ?? '';
                if ($key === '' || $key === 'payment_screenshot') continue;
                $value = trim($data['method_information'][$key] ?? '');
                if (($field['is_required'] ?? false) && $value === '') throw new DomainException('أكمل بيانات التحويل المطلوبة.');
                if ($value !== '' && preg_match('/phone|mobile|هاتف|موبايل/iu', $key)) {
                    $phones = app(EgyptPhoneService::class);
                    if (!$phones->isValid($value)) throw new DomainException('أدخل رقم موبايل مصري من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015');
                    $value = $phones->normalizeLocal($value);
                }
                $information[$key] = $value;
            }
            if (isset($data['transferred_amount'])) $information['transferred_amount'] = (string) $data['transferred_amount'];
            $payment->update([
                'status' => 'pending_review', 'payment_method' => $method->payment_channel,
                'offline_payment_method_id' => $method->id, 'method_name' => $method->method_name,
                'method_fields' => $method->method_fields, 'method_information' => $information,
                'payment_reference' => trim($data['payment_reference']), 'payment_proof' => $proofPath,
                'payment_note' => $data['payment_note'] ?? null, 'submitted_at' => now(),
            ]);
            return $payment;
        }, 3);
    }

    public function review(int $paymentId, string $decision, int $adminId, ?string $note): CustomerSubscriptionPayment
    {
        if (!in_array($decision, ['approve','reject'], true) || $adminId <= 0) throw new DomainException('قرار مراجعة غير صالح.');
        return DB::transaction(function () use ($paymentId, $decision, $adminId, $note) {
            // Use one lock order in every payment path, including two approvals
            // from different admin sessions and a simultaneous wallet attempt.
            $candidate = CustomerSubscriptionPayment::findOrFail($paymentId);
            User::query()->lockForUpdate()->findOrFail($candidate->customer_id);
            $payment = CustomerSubscriptionPayment::lockForUpdate()->findOrFail($paymentId);
            if ($payment->status === ($decision === 'approve' ? 'approved' : 'rejected')) return $payment;
            if ($payment->status !== 'pending_review') throw new DomainException('لا يمكن تغيير قرار دفع تمت مراجعته.');
            if ($decision === 'reject') {
                if (!trim((string) $note)) throw new DomainException('اكتب سبب رفض الدفع.');
                $payment->update(['status' => 'rejected', 'open_customer_id' => null, 'reviewed_by' => $adminId, 'review_note' => $note, 'reviewed_at' => now()]);
                return $payment;
            }
            return $this->approveLocked($payment, $adminId, $note);
        }, 3);
    }

    private function approveLocked(CustomerSubscriptionPayment $payment, ?int $adminId, ?string $note): CustomerSubscriptionPayment
    {
        $approvedAt = now()->startOfSecond();
        CustomerMonthlySubscription::create([
            'customer_id' => $payment->customer_id, 'payment_id' => $payment->id,
            'starts_at' => $approvedAt,
            // Exactly 30 elapsed days, including an Egyptian DST transition.
            'expires_at' => $approvedAt->copy()->utc()->addSeconds(30 * 86400)->setTimezone($approvedAt->timezone),
        ]);
        $payment->update([
            'status' => 'approved', 'open_customer_id' => null, 'approved_at' => $approvedAt,
            'reviewed_at' => $approvedAt, 'reviewed_by' => $adminId, 'review_note' => $note,
        ]);
        app(CustomerPurchaseEligibilityService::class)->reevaluateCustomer((int) $payment->customer_id);
        return $payment;
    }
}
