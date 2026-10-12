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
        $packagesEnabled = app(CustomerMonthlyAllowanceService::class)->enabled();
        $active = $subscription && (!$packagesEnabled || $subscription->package_id) && $subscription->starts_at->lte(now()) && $subscription->expires_at->gt(now());
        $pricing = app(MonthlySubscriptionPricingService::class)->get();
        return [
            'status' => $active ? 'active' : ($subscription ? 'expired' : 'inactive'),
            'active' => (bool) $active,
            'remaining_days' => $active ? (int) ceil(now()->diffInSeconds($subscription->expires_at, false) / 86400) : 0,
            'starts_at' => $subscription?->starts_at->toIso8601String(),
            'expires_at' => $subscription?->expires_at->toIso8601String(),
            'package_name' => $active ? $subscription->package_name : null,
            'package_id' => $active ? $subscription->package_id : null,
            'allowance' => $packagesEnabled ? app(CustomerMonthlyAllowanceService::class)->summary($customerId) : null,
            'price' => $packagesEnabled ? ($active ? $subscription->package_price : null) : $pricing['customer_price'], 'currency_code' => $packagesEnabled ? 'EGP' : $pricing['currency_code'],
            'payment_available' => $packagesEnabled ? \App\Models\CustomerPurchasePackage::where('status',1)->where('package_price','>',0)->where(fn ($q) => $q->where('is_custom',0)->orWhere('customer_id',$customerId))->exists() : (float) ($pricing['customer_price'] ?? 0) > 0,
            'open_payment' => CustomerSubscriptionPayment::where('open_customer_id', $customerId)->first(),
        ];
    }

    public function checkout(int $customerId, string $requestKey, ?int $packageId = null, ?int $offerId = null, int $quantity = 1): CustomerSubscriptionPayment
    {
        if ($quantity < 1 || $quantity > 5 || (!$offerId && $quantity !== 1)) throw new DomainException('اختر مضاعف زيادة من 1 إلى 5.');
        return DB::transaction(function () use ($customerId, $requestKey, $packageId, $offerId, $quantity) {
            User::query()->lockForUpdate()->findOrFail($customerId);
            $existing = CustomerSubscriptionPayment::where('customer_id', $customerId)->where('request_key', $requestKey)->first();
            if ($existing) return $existing;
            $open = CustomerSubscriptionPayment::where('open_customer_id', $customerId)->first();
            if ($open) {
                if (app(CustomerMonthlyAllowanceService::class)->enabled() && ((int) $open->package_id !== (int) $packageId || (int) $open->offer_id !== (int) $offerId || (int) ($open->offer_quantity ?? 1) !== $quantity)) {
                    throw new DomainException('لديك عملية دفع مفتوحة. أكملها أو ألغ مسودة الدفع قبل اختيار عرض آخر.');
                }
                return $open;
            }
            $pricing = app(MonthlySubscriptionPricingService::class)->get();
            $amount = (float) ($pricing['customer_price'] ?? 0);
            $terms = [];
            if (app(CustomerMonthlyAllowanceService::class)->enabled()) {
                $active = app(CustomerMonthlyAllowanceService::class)->active($customerId);
                if ($offerId) {
                    if (!$active) throw new DomainException('زيادة الحد تحتاج باقة نشطة.');
                    $offer = DB::table('customer_credit_offers')->where('active', true)->lockForUpdate()->find($offerId);
                    if (!$offer || $packageId) throw new DomainException('عرض زيادة غير متاح.');
                    $amount = round((float) $offer->price * $quantity, 3);
                    $terms = ['offer_id' => $offer->id, 'offer_quantity' => $quantity, 'target_subscription_id' => $active->id, 'purchase_kind' => 'increase', 'terms_name' => $offer->name.($quantity > 1 ? ' × '.$quantity : ''), 'granted_allowance' => round((float) $offer->allowance * $quantity, 3)];
                } else {
                    $package = \App\Models\CustomerPurchasePackage::lockForUpdate()->where('status', 1)
                        ->where(fn ($q) => $q->where('is_custom', 0)->orWhere('customer_id', $customerId))->find($packageId);
                    if (!$package) throw new DomainException('اختر باقة شهرية متاحة.');
                    if ($active && ($package->id === $active->package_id || (float) $package->purchase_limit <= (float) $active->granted_allowance)) {
                        throw new DomainException('لا يوجد تجديد مبكر. اختر باقة أعلى للترقية أو زد الحد الائتماني.');
                    }
                    $amount = (float) $package->package_price;
                    $terms = ['package_id' => $package->id, 'purchase_kind' => $active ? 'upgrade' : 'activation',
                        'terms_name' => $package->name, 'granted_allowance' => $package->purchase_limit];
                }
                $pricing['currency_code'] = 'EGP';
            }
            if (!is_finite($amount) || $amount <= 0) throw new DomainException('لم تحدد الإدارة سعر الاشتراك بعد.');
            $debit = round((float) Convert::usdPaymentModule($amount, $pricing['currency_code']), 3);
            if (!is_finite($debit) || $debit <= 0) throw new DomainException('تعذر تحديد قيمة الدفع. تواصل مع الإدارة.');
            return CustomerSubscriptionPayment::create(array_merge([
                'customer_id' => $customerId, 'open_customer_id' => $customerId,
                'request_key' => $requestKey, 'quoted_amount' => $amount,
                'wallet_debit_amount' => $debit, 'currency_code' => $pricing['currency_code'],
            ], $terms))->refresh();
        }, 3);
    }

    public function payFromWallet(int $customerId, int $paymentId): CustomerSubscriptionPayment
    {
        return DB::transaction(function () use ($customerId, $paymentId) {
            $customer = User::query()->lockForUpdate()->findOrFail($customerId);
            $payment = CustomerSubscriptionPayment::where('customer_id', $customerId)->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status === 'approved') return $payment;
            if ($payment->status !== 'draft') throw new DomainException('طلب الدفع قيد المراجعة أو تم رفضه.');
            $this->validatePurchase($payment);
            if (!$customer->is_active) throw new DomainException('الحساب موقوف إداريًا. تواصل مع الدعم.');
            if (getWebConfig('wallet_status') != 1) throw new DomainException('المحفظة غير متاحة حاليًا.');
            $amount = (int) round((float) $payment->wallet_debit_amount * 1000);
            $balance = (int) round((float) $customer->wallet_balance * 1000);
            if ($balance < $amount) throw new DomainException('عذرًا، رصيد المحفظة لا يكفي. أودع فيها أو استخدم طريقة أخرى.');
            $reference = 'customer-monthly-subscription-'.$payment->id;
            $customer->wallet_balance = ($balance - $amount) / 1000;
            $customer->save();
            WalletTransaction::create([
                'user_id' => $customerId, 'transaction_id' => $reference, 'reference' => $reference,
                'transaction_type' => 'monthly_subscription', 'payment_method' => 'wallet_balance',
                'credit' => 0, 'debit' => $amount / 1000, 'admin_bonus' => 0, 'balance' => $customer->wallet_balance,
            ]);
            $payment->update(['payment_method' => 'wallet_balance', 'payment_reference' => $reference, 'submitted_at' => now()]);
            return $this->approveLocked($payment, null, 'خصم مؤكد من رصيد المحفظة');
        }, 3);
    }

    public function submitOffline(int $customerId, int $paymentId, OfflinePaymentMethod $method, array $data, string $proofPath): CustomerSubscriptionPayment
    {
        return DB::transaction(function () use ($customerId, $paymentId, $method, $data, $proofPath) {
            $customer = User::query()->lockForUpdate()->findOrFail($customerId);
            $payment = CustomerSubscriptionPayment::where('customer_id', $customerId)->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== 'draft') throw new DomainException('تم تقديم طلب الدفع بالفعل.');
            $this->validatePurchase($payment);
            if (!$customer->is_active) throw new DomainException('الحساب موقوف إداريًا. تواصل مع الدعم.');
            $method = OfflinePaymentMethod::where('status', 1)->whereIn('payment_channel', ['wallet','instapay'])->findOrFail($method->id);
            if (isset($data['transferred_amount']) && (int) round((float) $data['transferred_amount'] * 1000) !== (int) round((float) $payment->quoted_amount * 1000)) {
                throw new DomainException('مبلغ التحويل يجب أن يساوي مبلغ الاشتراك المطلوب.');
            }
            $modern = ($data['transfer_schema'] ?? null) === 'sender_v2';
            $information = $modern ? app(CustomerTransferInformationService::class)->sender($data) : [];
            foreach ($modern ? [] : ($method->method_informations ?? []) as $field) {
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
            app(CustomerPaymentNotificationService::class)->notify($customerId, 'package-payment-'.$payment->id.'-pending', 'تم استلام دفع '.($payment->terms_name ?: 'الاشتراك').'. الدفع قيد المراجعة.');
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
                app(CustomerPaymentNotificationService::class)->notify((int)$payment->customer_id, 'package-payment-'.$payment->id.'-rejected', 'تم رفض الدفع: '.$note);
                return $payment;
            }
            return $this->approveLocked($payment, $adminId, $note);
        }, 3);
    }

    private function approveLocked(CustomerSubscriptionPayment $payment, ?int $adminId, ?string $note): CustomerSubscriptionPayment
    {
        $this->validatePurchase($payment);
        $approvedAt = now()->startOfSecond();
        $terms = [];
        $packageMode = app(CustomerMonthlyAllowanceService::class)->enabled();
        if ($packageMode && $payment->purchase_kind === 'increase') {
            CustomerMonthlySubscription::whereKey($payment->target_subscription_id)->increment('additional_allowance', $payment->granted_allowance);
        } else {
        if ($packageMode) {
            // A higher package starts a new period; the previous entitlement and ledger stay historical.
            CustomerMonthlySubscription::where('customer_id', $payment->customer_id)->whereNotNull('package_id')->where('expires_at', '>', $approvedAt)->update(['expires_at' => $approvedAt]);
            $terms = ['package_id' => $payment->package_id, 'package_name' => $payment->terms_name,
                'package_price' => $payment->quoted_amount, 'granted_allowance' => $payment->granted_allowance];
        }
        CustomerMonthlySubscription::create(array_merge([
            'customer_id' => $payment->customer_id, 'payment_id' => $payment->id,
            'starts_at' => $approvedAt,
            // Exactly 30 elapsed days, including an Egyptian DST transition.
            'expires_at' => $approvedAt->copy()->utc()->addSeconds(30 * 86400)->setTimezone($approvedAt->timezone),
        ], $terms));
        }
        $payment->update([
            'status' => 'approved', 'open_customer_id' => null, 'approved_at' => $approvedAt,
            'reviewed_at' => $approvedAt, 'reviewed_by' => $adminId, 'review_note' => $note,
        ]);
        app(CustomerPurchaseEligibilityService::class)->reevaluateCustomer((int) $payment->customer_id);
        app(CustomerPaymentNotificationService::class)->notify((int)$payment->customer_id, 'package-payment-'.$payment->id.'-approved', $payment->purchase_kind === 'increase' ? 'تم اعتماد زيادة الحد الائتماني. تنتهي مع باقتك الحالية.' : 'مدفوع · تم تفعيل باقة '.($payment->terms_name ?: 'الاشتراك').' لمدة 30 يومًا.');
        return $payment;
    }

    private function validatePurchase(CustomerSubscriptionPayment $payment): void
    {
        if (!app(CustomerMonthlyAllowanceService::class)->enabled()) return;
        if (!$payment->package_id && !$payment->offer_id) throw new DomainException('مسودة اشتراك سابقة: اختر باقة جديدة.');
        $active = app(CustomerMonthlyAllowanceService::class)->active((int) $payment->customer_id);
        if ($payment->purchase_kind === 'increase') {
            if (!$active || $active->id !== $payment->target_subscription_id) throw new DomainException('انتهت الباقة المرتبطة بالزيادة. لا يمكن اعتمادها؛ يلزم معالجة التحويل مع الإدارة.');
        } elseif ($active && ($payment->package_id === $active->package_id || (float) $payment->granted_allowance <= (float) $active->granted_allowance)) {
            throw new DomainException('الباقة نشطة بالفعل. الترقية فقط إلى باقة أعلى، والتجديد بعد الانتهاء.');
        }
    }

    public function cancelDraft(int $customerId, int $paymentId): void
    {
        DB::transaction(function () use ($customerId, $paymentId) {
            User::lockForUpdate()->findOrFail($customerId);
            $payment = CustomerSubscriptionPayment::where('customer_id', $customerId)->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== 'draft') throw new DomainException('لا يمكن إلغاء تحويل مقدم للمراجعة.');
            $payment->update(['status' => 'cancelled', 'open_customer_id' => null]);
        }, 3);
    }
}
