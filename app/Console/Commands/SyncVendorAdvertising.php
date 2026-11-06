<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Services\VendorAdvertisingSlotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncVendorAdvertising extends Command
{
    protected $signature = 'vendor:sync-advertising';
    protected $description = 'Advance already-paid vendor cycles and notify expired vendors without charging money';

    public function handle(): int
    {
        if (! config('vendor_advertising.enabled')) return self::SUCCESS;
        Seller::whereIn('id', SellerPackageSubscription::where('payment_status', 'paid')->whereIn('status', ['active', 'scheduled'])->select('seller_id'))
            ->orderBy('id')->chunkById(100, function ($sellers) {
                foreach ($sellers as $seller) app(VendorAdvertisingSlotService::class)->synchronize($seller);
            });
        SellerPackageSubscription::where('payment_status', 'paid')->where('status', 'expired')->where('expires_at', '<=', now())
            ->orderBy('id')->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription) {
                        $subscription = SellerPackageSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
                        $metadata = $subscription->metadata ?: [];
                        if (isset($metadata['expiry_notification_id'])) return;
                        $notification = Notification::create([
                            'sent_by' => 'admin', 'sent_to' => 'seller', 'seller_id' => $subscription->seller_id,
                            'title' => 'انتهت دورة اشتراك الإعلانات',
                            'description' => 'انتهت الدورة السابقة. دون اشتراك سارٍ تختفي الإعلانات ولا تستقبل طلبات جديدة، ويمكنك إكمال طلباتك القائمة. إذا جددت مسبقًا فدورتك التالية تبدأ تلقائيًا.',
                            'image' => '', 'status' => 1, 'notification_count' => 1,
                        ]);
                        $metadata['expiry_notification_id'] = $notification->id;
                        $subscription->update(['metadata' => $metadata]);
                    });
                }
            });
        cacheRemoveByType(type: 'products');
        return self::SUCCESS;
    }
}
