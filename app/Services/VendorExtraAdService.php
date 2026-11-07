<?php

namespace App\Services;

use App\Models\{Seller, SellerPackageSubscription, SellerPackageTransaction, VendorAdvertisingSlot, VendorExtraAdPurchase};
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorExtraAdService
{
    public function settings(): array
    {
        $row = DB::table('vendor_extra_ad_settings')->find(1);
        return ['enabled' => (bool) ($row->enabled ?? false), 'unit_price' => $row?->unit_price,
            'max_slots' => (int) ($row->max_slots ?? 100), 'currency' => 'EGP', 'price_is_final' => true,
            'duration_policy' => 'subscription_expiry', 'content_rejection_available' => true];
    }

    public function purchase(Seller $seller, array $data, ?array $proof = null, ?int $adminId = null): VendorExtraAdPurchase
    {
        if (!config('vendor_advertising.enabled')) throw new DomainException('شراء الإضافات غير متاح.');
        return DB::transaction(function () use ($seller, $data, $proof, $adminId) {
            app(SellerLedgerService::class)->lockSellerFinancialState($seller->id);
            $existing = VendorExtraAdPurchase::where('seller_id', $seller->id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->quantity !== (int) $data['quantity'] || $existing->payment_method !== $data['payment_method']
                    || $existing->seller_package_subscription_id !== (int) $data['subscription_id']
                    || (int) round((float) $existing->unit_price * 100) !== (int) round((float) $data['expected_unit_price'] * 100)) {
                    throw new DomainException('مفتاح الطلب مستخدم لشراء مختلف.');
                }
                return $existing;
            }
            if (!in_array($seller->status, ['approved', 'pending'], true)) {
                throw new DomainException('يلزم حساب بائع نشط ومعتمد.');
            }
            app(VendorAdvertisingSlotService::class)->synchronize($seller);
            $subscription = SellerPackageSubscription::where('seller_id', $seller->id)->whereKey($data['subscription_id'])->lockForUpdate()->firstOrFail();
            $this->assertActive($subscription);
            $settings = $this->settings();
            if (!$settings['enabled'] || $settings['unit_price'] === null || (float) $settings['unit_price'] <= 0) throw new DomainException('لم تفعّل الإدارة شراء المساحات الإضافية بسعر محدد بعد.');
            $quantity = (int) $data['quantity'];
            $reserved = VendorExtraAdPurchase::where('seller_package_subscription_id', $subscription->id)->whereIn('status', ['paid', 'pending_review', 'allocated_with_debt'])->sum('quantity');
            if ($quantity < 1 || $reserved + $quantity > $settings['max_slots']) throw new DomainException('العدد يتجاوز حد المساحات الإضافية لهذه الدورة.');
            $total = number_format(((int) round((float) $settings['unit_price'] * 100)) * $quantity / 100, 2, '.', '');
            if ((string) $data['expected_unit_price'] !== number_format((float) $settings['unit_price'], 2, '.', '')) throw new DomainException('تغير سعر المساحة. حدّث الصفحة وراجع السعر قبل تأكيد الشراء.');
            $wallet = $data['payment_method'] === 'operating_balance';
            if (!$wallet && !$proof) throw new DomainException('إثبات التحويل مطلوب.');
            $purchase = VendorExtraAdPurchase::create([
                'admin_reference' => 'VAD-'.now()->format('Y').'-'.strtoupper((string) Str::ulid()),
                'seller_id' => $seller->id, 'seller_package_subscription_id' => $subscription->id,
                'request_key' => $data['request_key'], 'quantity' => $quantity, 'unit_price' => $settings['unit_price'],
                'total_price' => $total, 'currency' => 'EGP', 'payment_method' => $data['payment_method'],
                'status' => 'pending_review', 'expires_at' => $subscription->expires_at,
                'terms' => ['duration_policy' => 'subscription_expiry', 'expires_at' => $subscription->expires_at->toIso8601String(),
                    'price_is_final' => true, 'tax_included' => true, 'guaranteed_featured_placement' => false,
                    'renewal_included' => false, 'content_rejection_available' => true],
                'payment_details' => $proof, 'reviewed_by' => $adminId,
            ]);
            if ($data['payment_method'] === 'offline_payment' && ($proof['transfer_reference'] ?? null)) app(VendorTransferReferenceService::class)->reserve((int)$proof['method_id'],$proof['transfer_reference'],'extra_ad',$purchase->id);
            if ($wallet) {
                $ledger = app(SellerLedgerService::class);
                if (($ledger->summary($seller->id)['operating'] ?? 0) < (float) $total) throw new DomainException('رصيد المدفوعات غير كافٍ. برجاء شحن الرصيد.');
                $ledger->post($seller->id, 'extra_ad_purchase', 'extra-ad-'.$purchase->id,
                    [['bucket' => 'operating', 'direction' => 'debit', 'amount' => (float) $total, 'reporting_category' => 'package', 'reference_code' => $purchase->admin_reference]],
                    VendorExtraAdPurchase::class, $purchase->id, ['currency' => 'EGP', 'reference' => $purchase->admin_reference], $adminId);
                $this->activate($purchase, $subscription, 'extra-ad-wallet-'.$purchase->id);
            }
            return $purchase->fresh();
        });
    }

    public function payFundingReservation(VendorExtraAdPurchase $purchase): VendorExtraAdPurchase
    {
        return DB::transaction(function () use ($purchase) {
            $ledger = app(SellerLedgerService::class); $ledger->lockSellerFinancialState($purchase->seller_id);
            $purchase = VendorExtraAdPurchase::lockForUpdate()->findOrFail($purchase->id);
            if ($purchase->status === 'paid') return $purchase;
            if ($purchase->status !== 'pending_review' || $purchase->payment_method !== 'funding_intent') throw new DomainException('حجز الإضافات غير متاح للدفع.');
            $subscription = SellerPackageSubscription::lockForUpdate()->findOrFail($purchase->seller_package_subscription_id);
            $this->assertActive($subscription);
            if (($ledger->summary($purchase->seller_id)['operating'] ?? 0) < (float)$purchase->total_price) throw new DomainException('رصيد المدفوعات غير كافٍ.');
            $ledger->post($purchase->seller_id, 'extra_ad_purchase', 'extra-ad-'.$purchase->id,
                [['bucket'=>'operating', 'direction'=>'debit', 'amount'=>(float)$purchase->total_price]], VendorExtraAdPurchase::class, $purchase->id,
                ['currency'=>$purchase->currency, 'reference'=>$purchase->admin_reference]);
            $this->activate($purchase, $subscription, 'extra-ad-funded-'.$purchase->id);
            return $purchase->fresh();
        });
    }

    public function approve(int $id, int $adminId, string $reference, ?string $note = null): VendorExtraAdPurchase
    {
        return DB::transaction(function () use ($id, $adminId, $reference, $note) {
            $sellerId = VendorExtraAdPurchase::findOrFail($id)->seller_id;
            app(SellerLedgerService::class)->lockSellerFinancialState($sellerId);
            $purchase = VendorExtraAdPurchase::lockForUpdate()->findOrFail($id);
            if (in_array($purchase->status, ['paid', 'manual_settlement'], true)) return $purchase;
            if ($purchase->status !== 'pending_review' || $purchase->payment_method !== 'offline_payment') throw new DomainException('الطلب غير متاح للاعتماد.');
            if (trim($reference) === '') throw new DomainException('رقم التحويل المؤكد مطلوب.');
            $details = $purchase->payment_details ?: [];
            $details['review_reference'] = trim($reference);
            $purchase->payment_details = $details;
            $method = $purchase->payment_details['method_id'] ?? 0;
            $reference = 'extra-ad-offline-'.$method.'-'.hash('sha256', trim($reference));
            if (VendorExtraAdPurchase::where('payment_reference', $reference)->where('id', '!=', $id)->exists()) throw new DomainException('سبق استخدام رقم التحويل لهذه الطريقة.');
            app(VendorTransferReferenceService::class)->reserve((int)$method,$details['review_reference'],'extra_ad',$id,true);
            $subscription = SellerPackageSubscription::lockForUpdate()->findOrFail($purchase->seller_package_subscription_id);
            $purchase->fill(['reviewed_by' => $adminId, 'review_note' => $note]);
            if ($subscription->payment_status !== 'paid' || !in_array($subscription->status, ['active', 'scheduled'], true)
                || !$subscription->starts_at?->lte(now()) || !$purchase->expires_at->gt(now())) {
                $details = $purchase->payment_details ?: [];
                $details['manual_settlement_reference'] = $details['review_reference'];
                $purchase->payment_details = $details;
                $purchase->payment_reference = $reference;
                $purchase->fill(['status' => 'manual_settlement', 'review_note' => $note ?: 'انتهى الاشتراك قبل اعتماد التحويل؛ تسوية يدوية مطلوبة.'])->save();
            } else $this->activate($purchase, $subscription, $reference);
            return $purchase->fresh();
        });
    }

    public function rejectPayment(int $id, int $adminId, string $reason): void
    {
        DB::transaction(function () use ($id, $adminId, $reason) {
            $sellerId = VendorExtraAdPurchase::findOrFail($id)->seller_id;
            app(SellerLedgerService::class)->lockSellerFinancialState($sellerId);
            $purchase = VendorExtraAdPurchase::lockForUpdate()->findOrFail($id);
            if ($purchase->status === 'payment_rejected') return;
            if ($purchase->status !== 'pending_review' || $purchase->payment_method !== 'offline_payment') throw new DomainException('لا يمكن رفض دفعة معتمدة.');
            app(VendorTransferReferenceService::class)->release('extra_ad',$purchase->id);
            $purchase->update(['status' => 'payment_rejected', 'reviewed_by' => $adminId, 'review_note' => $reason]);
        });
    }

    private function assertActive(SellerPackageSubscription $s): void
    {
        if ($s->payment_status !== 'paid' || $s->status !== 'active' || !$s->starts_at?->lte(now()) || !$s->expires_at?->gt(now())) throw new DomainException('يلزم اشتراك مدفوع وسارٍ لشراء مساحة إضافية.');
    }

    private function activate(VendorExtraAdPurchase $purchase, SellerPackageSubscription $subscription, string $reference): void
    {
        app(VendorAdvertisingSlotService::class)->provision($subscription);
        $position = (int) VendorAdvertisingSlot::where('seller_package_subscription_id', $subscription->id)->max('position');
        for ($i = 1; $i <= $purchase->quantity; $i++) VendorAdvertisingSlot::create([
            'seller_id' => $purchase->seller_id, 'seller_package_subscription_id' => $subscription->id,
            'position' => $position + $i, 'extra_ad_purchase_id' => $purchase->id,
            'starts_at' => now(), 'expires_at' => $purchase->expires_at,
        ]);
        $purchase->fill(['status' => 'paid', 'paid_at' => now(), 'payment_reference' => $reference])->save();
        SellerPackageTransaction::create(['seller_id' => $purchase->seller_id, 'seller_package_subscription_id' => $subscription->id,
            'seller_package_id' => $subscription->seller_package_id, 'transaction_type' => 'extra_ad_purchase',
            'quota_type' => 'product', 'credit' => $purchase->quantity, 'debit' => 0,
            'balance_after' => VendorAdvertisingSlot::where('seller_package_subscription_id', $subscription->id)->count(),
            'paid_amount' => $purchase->total_price, 'transaction_id' => $reference, 'reference' => $purchase->admin_reference,
            'created_by_admin_id' => $purchase->reviewed_by, 'metadata' => ['extra_ad_purchase_id' => $purchase->id, 'currency' => $purchase->currency, 'terms' => $purchase->terms]]);
    }
}
