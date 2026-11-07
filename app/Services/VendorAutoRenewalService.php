<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Seller;
use App\Models\SellerPackage;
use App\Models\SellerPackageSubscription;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorAutoRenewalService
{
    /** The vendor approves the current price. A changed price requires new consent. */
    public function enable(Seller $seller, int $subscriptionId, float $expectedPrice): void
    {
        DB::transaction(function () use ($seller, $subscriptionId, $expectedPrice) {
            app(SellerLedgerService::class)->lockSellerFinancialState($seller->id);
            $subscription = SellerPackageSubscription::where('seller_id', $seller->id)->whereKey($subscriptionId)->lockForUpdate()->firstOrFail();
            if ($subscription->status !== 'active' || $subscription->payment_status !== 'paid' || !$subscription->expires_at?->gt(now())) {
                throw new DomainException('يلزم اشتراك مدفوع وسارٍ لتفعيل التجديد.');
            }
            $package = SellerPackage::whereKey($subscription->seller_package_id)->lockForUpdate()->firstOrFail();
            if (!$package->status || (float) $package->package_price <= 0) throw new DomainException('الباقة غير متاحة للتجديد.');
            if ((int) round((float) $package->package_price * 100) !== (int) round($expectedPrice * 100)) throw new DomainException('تغير سعر الباقة. راجع السعر قبل تفعيل التجديد.');
            DB::table('vendor_auto_renewals')->updateOrInsert(['seller_id' => $seller->id], [
                'subscription_id' => $subscription->id, 'approved_price' => $package->package_price,
                'enabled' => true, 'disabled_by_admin_id' => null, 'disabled_at' => null, 'updated_at' => now(), 'created_at' => now(),
            ]);
        });
    }

    public function disableByAdmin(int $sellerId, int $adminId): void
    {
        DB::transaction(function () use ($sellerId, $adminId) {
            app(SellerLedgerService::class)->lockSellerFinancialState($sellerId);
            $renewal = DB::table('vendor_auto_renewals')->where('seller_id', $sellerId)->lockForUpdate()->first();
            if (!$renewal || !$renewal->enabled) return;
            DB::table('vendor_auto_renewals')->where('seller_id', $sellerId)->update(['enabled' => false, 'disabled_by_admin_id' => $adminId, 'disabled_at' => now(), 'updated_at' => now()]);
            if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'seller_id')) {
                Notification::create(['sent_by' => 'admin', 'sent_to' => 'seller', 'seller_id' => $sellerId, 'title' => 'التجديد التلقائي', 'description' => 'ألغت الإدارة التجديد التلقائي لاشتراكك.', 'notification_count' => 1, 'status' => 1]);
            }
        });
    }

    public function process(int $sellerId): void
    {
        DB::transaction(function () use ($sellerId) {
            app(SellerLedgerService::class)->lockSellerFinancialState($sellerId);
            $renewal = DB::table('vendor_auto_renewals')->where('seller_id', $sellerId)->lockForUpdate()->first();
            if (!$renewal || !$renewal->enabled) return;
            $current = SellerPackageSubscription::where('seller_id', $sellerId)->whereKey($renewal->subscription_id)->lockForUpdate()->first();
            if (!$current || !$current->expires_at) return $this->stop($sellerId, 'تعذر تجديد الاشتراك تلقائيًا. راجع الاشتراك مع الدعم.');
            if ($current->payment_status !== 'paid' || !in_array($current->status, ['active', 'scheduled', 'expired'], true)) {
                return $this->stop($sellerId, 'توقف التجديد التلقائي لأن الاشتراك لم يعد صالحًا للتجديد.');
            }
            if ($current->expires_at->gt(now())) return;

            // A manually purchased future cycle already covers this boundary.
            $covered = SellerPackageSubscription::where('seller_id', $sellerId)->where('payment_status', 'paid')
                ->whereIn('status', ['active', 'scheduled'])->where('expires_at', '>', now())
                ->orderByDesc('expires_at')->lockForUpdate()->first();
            if ($covered) {
                if ((int) $covered->seller_package_id !== (int) $current->seller_package_id) {
                    return $this->stop($sellerId, 'توقف التجديد التلقائي لأن الباقة القادمة مختلفة.');
                }
                DB::table('vendor_auto_renewals')->where('seller_id', $sellerId)->update(['subscription_id' => $covered->id, 'updated_at' => now()]);
                return;
            }
            $package = SellerPackage::whereKey($current->seller_package_id)->lockForUpdate()->first();
            if (!$package || !$package->status || (int) round((float) $package->package_price * 100) !== (int) round((float) $renewal->approved_price * 100)) {
                return $this->stop($sellerId, 'توقف التجديد التلقائي لتغير سعر الباقة أو عدم توفرها.');
            }
            $pending = SellerPackageSubscription::where('seller_id', $sellerId)->where('payment_status', 'unpaid')->whereIn('status', ['pending', 'pending_review'])->exists();
            if ($pending) return $this->stop($sellerId, 'توقف التجديد التلقائي لوجود طلب اشتراك آخر قيد المعالجة.');
            $balance = app(SellerLedgerService::class)->summary($sellerId, initializeLegacy: false)[SellerLedgerService::OPERATING] ?? 0;
            if ($balance + 0.000000001 < (float) $renewal->approved_price) return $this->stop($sellerId, 'توقف التجديد التلقائي لعدم كفاية الرصيد النقدي. اشحن الرصيد لتجديد الاشتراك.');
            $result = app(SellerPackagePurchaseService::class)->purchaseWithPaymentsBalance(Seller::findOrFail($sellerId), $package, 'auto-renew-'.$current->id, (float) $renewal->approved_price);
            DB::table('vendor_auto_renewals')->where('seller_id', $sellerId)->update(['subscription_id' => $result['subscription']->id, 'updated_at' => now()]);
        });
    }

    private function stop(int $sellerId, string $message): void
    {
        DB::table('vendor_auto_renewals')->where('seller_id', $sellerId)->update(['enabled' => false, 'updated_at' => now()]);
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'seller_id')) {
            Notification::create(['sent_by' => 'admin', 'sent_to' => 'seller', 'seller_id' => $sellerId, 'title' => 'التجديد التلقائي', 'description' => $message, 'notification_count' => 1, 'status' => 1]);
        }
    }
}
