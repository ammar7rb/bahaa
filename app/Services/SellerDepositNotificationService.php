<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Seller;
use App\Models\SellerBalanceDeposit;
use App\Traits\PushNotificationTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SellerDepositNotificationService
{
    use PushNotificationTrait;

    public function notify(SellerBalanceDeposit $deposit, bool $approved): void
    {
        if (!Schema::hasTable('notifications') || !Schema::hasColumn('notifications', 'seller_id')) return;
        $title = $approved ? 'تم قبول شحن الرصيد' : 'تم رفض شحن الرصيد';
        $message = $approved ? 'تمت مراجعة طلب الشحن وإضافة المبلغ إلى رصيد المدفوعات.' : 'تم رفض طلب شحن الرصيد. يمكنك مراجعة السبب في سجل الشحن.';
        Notification::create(['sent_by' => 'admin', 'sent_to' => 'seller', 'seller_id' => $deposit->seller_id,
            'title' => $title, 'description' => $message, 'notification_count' => 1, 'status' => 1]);
        DB::afterCommit(function () use ($deposit, $title, $message) {
            try {
                $token = Seller::find($deposit->seller_id)?->cm_firebase_token;
                if ($token) $this->sendPushNotificationToDevice($token, ['title' => $title, 'description' => $message, 'image' => '', 'type' => 'notification']);
            } catch (\Throwable $error) {
                Log::warning('Seller deposit push failed', ['seller_id' => $deposit->seller_id, 'error_type' => get_class($error)]);
            }
        });
    }
}
