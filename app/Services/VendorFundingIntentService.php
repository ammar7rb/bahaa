<?php
namespace App\Services;

use App\Models\{Seller, SellerBalanceDeposit, SellerPackage, SellerPackageSubscription, VendorExtraAdPurchase, VendorFundingIntent};
use DomainException;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

class VendorFundingIntentService
{
    public function attach(SellerBalanceDeposit $deposit, array $data): ?VendorFundingIntent
    {
        $kind = $data['purchase_kind'] ?? 'none';
        if ($kind === 'none') return null;
        if (!in_array($kind, ['package','extra'], true) || !config('vendor_advertising.enabled')) throw new DomainException('نوع الشراء غير متاح.');
        $seller = Seller::findOrFail($deposit->seller_id); $this->assertSeller($seller);
        if ($kind === 'package') {
            $package = SellerPackage::findOrFail($data['package_id'] ?? 0);
            $snapshot = app(SellerPackagePurchaseService::class)->snapshotForFunding($package);
            $amount = $snapshot['paid_package_price'];
            if (isset($data['expected_purchase_amount']) && round((float)$data['expected_purchase_amount'],2) !== round((float)$amount,2)) throw new DomainException('تغير السعر؛ راجع الباقة قبل إرسال الشحن.');
            return VendorFundingIntent::create(['seller_id'=>$seller->id,'deposit_id'=>$deposit->id,'kind'=>$kind,'status'=>'awaiting_review',
                'package_id'=>$package->id,'snapshot'=>$snapshot,'purchase_amount'=>$amount,'currency'=>'EGP']);
        }
        $extra = app(VendorExtraAdService::class)->purchase($seller, ['subscription_id'=>$data['subscription_id'] ?? 0,
            'quantity'=>$data['quantity'] ?? 0, 'request_key'=>(string)Str::uuid(), 'payment_method'=>'funding_intent',
            'expected_unit_price'=>number_format((float)($data['expected_unit_price'] ?? 0),2,'.','')], ['funding_deposit_id'=>$deposit->id]);
        return VendorFundingIntent::create(['seller_id'=>$seller->id,'deposit_id'=>$deposit->id,'kind'=>$kind,'status'=>'awaiting_review',
            'extra_purchase_id'=>$extra->id,'subscription_id'=>$extra->seller_package_subscription_id,'snapshot'=>$extra->terms,
            'purchase_amount'=>$extra->total_price,'currency'=>'EGP']);
    }

    /** An expired extra transfer remains unapproved, matching the explicit phase 5 decision. */
    public function beforeApproval(SellerBalanceDeposit $deposit): bool
    {
        if (!Schema::hasTable('vendor_funding_intents')) return true;
        $intent = VendorFundingIntent::where('deposit_id',$deposit->id)->lockForUpdate()->first();
        if (!$intent || $intent->kind !== 'extra') return true;
        $extra = VendorExtraAdPurchase::findOrFail($intent->extra_purchase_id);
        if ($extra->expires_at->gt(now())) return true;
        $intent->update(['status'=>'manual_settlement','message'=>'انتهى الاشتراك قبل اعتماد التحويل؛ يبقى المبلغ معلقًا لتسوية يدوية دون إضافة رصيد أو مساحات.']);
        return false;
    }

    public function execute(SellerBalanceDeposit $deposit): ?VendorFundingIntent
    {
        if (!Schema::hasTable('vendor_funding_intents')) return null;
        return DB::transaction(function () use ($deposit) {
            app(SellerLedgerService::class)->lockSellerFinancialState($deposit->seller_id);
            $intent = VendorFundingIntent::where('deposit_id',$deposit->id)->lockForUpdate()->first();
            if (!$intent || in_array($intent->status,['completed','cancelled','manual_settlement','expired_after_funding'],true)) return $intent;
            if ($deposit->fresh()->status !== 'paid') return $intent;
            $seller = Seller::findOrFail($deposit->seller_id);
            try {
                $this->assertSeller($seller);
                if ($intent->kind === 'extra' && VendorExtraAdPurchase::findOrFail($intent->extra_purchase_id)->expires_at->lte(now())) {
                    $intent->update(['status'=>'expired_after_funding','message'=>'انتهت دورة الإضافات بعد شحن الرصيد؛ بقي المال في operating دون خصم.']); return $intent;
                }
                if ((app(SellerLedgerService::class)->summary($seller->id)['operating'] ?? 0) < (float)$intent->purchase_amount) {
                    $intent->update(['status'=>'insufficient_balance','message'=>'رصيد المدفوعات غير كافٍ. اشحن الرصيد ثم اضغط إعادة المحاولة.']); return $intent;
                }
                // Savepoint: a failed purchase never rolls back the accepted funding credit.
                DB::transaction(function () use ($intent, $seller) {
                    $message = 'تم الشراء بالشروط المثبتة.';
                    if ($intent->kind === 'package') {
                        $subscription = app(SellerPackagePurchaseService::class)->purchaseFundingSnapshot($seller,$intent->package_id,$intent->snapshot,'funding-intent-'.$intent->id);
                        $intent->subscription_id = $subscription->id;
                        if ($subscription->metadata['slot_selection_required'] ?? false) $message = 'تم الشراء؛ اختر المنتجات المستمرة من مدير الإعلانات لأن عددها يتجاوز حصة الدورة الجديدة.';
                    } else {
                        app(VendorAdvertisingSlotService::class)->synchronize($seller);
                        app(VendorExtraAdService::class)->payFundingReservation(VendorExtraAdPurchase::findOrFail($intent->extra_purchase_id));
                    }
                    $intent->fill(['status'=>'completed','message'=>$message,'completed_at'=>now()])->save();
                });
            } catch (DomainException $e) {
                $intent->refresh()->update(['status'=>'action_required','message'=>$e->getMessage()]);
            }
            return $intent->fresh();
        });
    }

    public function cancel(SellerBalanceDeposit $deposit): void
    {
        if (!Schema::hasTable('vendor_funding_intents')) return;
        $intent = VendorFundingIntent::where('deposit_id',$deposit->id)->lockForUpdate()->first();
        if (!$intent || $intent->status === 'completed') return;
        $intent->update(['status'=>'cancelled','message'=>'رُفض التحويل دون إضافة رصيد أو تنفيذ الشراء.']);
        if ($intent->extra_purchase_id) VendorExtraAdPurchase::whereKey($intent->extra_purchase_id)->where('status','pending_review')->update(['status'=>'payment_rejected']);
    }

    private function assertSeller(Seller $seller): void
    {
        if (!in_array($seller->status, ['approved', 'pending'], true)) throw new DomainException('الحساب موقوف إداريًا. تواصل مع الدعم.');
    }
}
