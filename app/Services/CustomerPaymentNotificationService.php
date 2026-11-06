<?php
namespace App\Services;
use App\Models\User;
use App\Traits\PushNotificationTrait;
use Illuminate\Support\Facades\{DB, Schema};
class CustomerPaymentNotificationService
{
    use PushNotificationTrait;
    public function notify(int $customerId, string $key, string $message): void {
        if (!Schema::hasColumn('notifications', 'customer_event_key')) return;
        $inserted = DB::table('notifications')->insertOrIgnore(['customer_id' => $customerId, 'customer_event_key' => $key,
            'sent_by' => 'admin', 'sent_to' => 'customer', 'title' => 'تحديث الدفع والباقة', 'description' => $message,
            'status' => 1, 'notification_count' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()]);
        if ($inserted) DB::afterCommit(function () use ($customerId, $message) {
            $token = User::find($customerId)?->cm_firebase_token;
            if ($token) { try { $this->sendPushNotificationToDevice($token, ['title' => 'تحديث حسابك', 'description' => $message, 'image' => '', 'type' => 'notification']); }
                catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('Customer push delivery failed', ['customer_id' => $customerId, 'error_type' => get_class($e)]); } }
        });
    }
}
