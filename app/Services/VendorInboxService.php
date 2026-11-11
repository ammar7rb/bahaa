<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationSeen;
use App\Models\Order;
use App\Models\Seller;
use App\Models\VendorSupportMessage;
use App\Models\VendorSupportThread;
use Illuminate\Support\Facades\DB;

/** Reading an alert never mutates the order's operational/payment status. */
class VendorInboxService
{
    public function protectNotification(Seller $seller, Notification $notification): Notification
    {
        if (preg_match('/#(\d+)/', (string) $notification->description, $match)) {
            $order = $this->orders($seller)->with('sellerOrderInsurance')->whereKey((int) $match[1])->first();
            if ($order && app(VendorFulfillmentEligibilityService::class)->hasRestrictions($order)) {
                $summary = app(VendorFulfillmentEligibilityService::class)->summary($order);
                $notification->title = $summary['pending_on_vendor_side'] ? $summary['pending_label'] : 'طلب تنفيذ محول إليك';
                $notification->description = '#'.$order->id.' — '.VendorFulfillmentEligibilityService::MESSAGE;
                $notification->image = null;
            } elseif ($order && !app(SellerOrderVisibilityService::class)->canSellerView($order)) {
                $notification->title = translate('order_received');
                $notification->description = '***'.substr((string) $order->id, -3).' — '.translate('details_hidden_until_insurance_is_paid');
                $notification->image = null;
            }
        }
        return $notification;
    }
    public function notifications(Seller $seller)
    {
        return Notification::query()->active()->where('sent_to', 'seller')
            ->where('created_at', '>=', $seller->created_at)
            ->where(fn ($q) => $q->whereNull('seller_id')->orWhere('seller_id', $seller->id));
    }

    public function messages(Seller $seller)
    {
        // Support is its own channel. Activation tickets never enter this inbox.
        return VendorSupportMessage::query()
            ->whereHas('thread', fn ($q) => $q->where('seller_id', $seller->id));
    }

    public function orders(Seller $seller)
    {
        return Order::query()->sellerAccessible()->where(['seller_id' => $seller->id, 'seller_is' => 'seller']);
    }

    public function snapshot(Seller $seller): array
    {
        $reads = DB::table('vendor_inbox_reads')->where('seller_id', $seller->id)->get();
        $readMessages = $reads->where('kind', 'support_message')->pluck('record_id')->all();
        $readOrders = $reads->where('kind', 'order_alert')->pluck('record_id')->all();
        $seenNotifications = NotificationSeen::where('seller_id', $seller->id)->pluck('notification_id')->all();
        $orders = $this->orders($seller)->with('sellerOrderInsurance')->select(['id', 'order_status', 'order_amount', 'seller_restricted_access_token', 'created_at', 'seller_id', 'commerce_flow_version', 'commerce_flow_status', 'admin_order_review_status', 'post_purchase_status'])->latest('id')->get();
        $orderIds = $orders->pluck('id')->all();
        $notifications = $this->notifications($seller)->latest('id')->get();
        $orderNotifications = [];
        $general = [];
        foreach ($notifications as $notification) {
            $notification = $this->protectNotification($seller, $notification);
            if (preg_match('/#(\d+)/', (string) $notification->description, $match) && in_array((int) $match[1], $orderIds, true)) {
                $orderNotifications[(int) $match[1]][] = $notification;
                continue;
            }
            $general[] = ['kind' => 'notification', 'id' => $notification->id, 'title' => $notification->title,
                'body' => $notification->description, 'created_at' => $notification->created_at?->toIso8601String(),
                'unread' => !in_array($notification->id, $seenNotifications, true)];
        }
        foreach ($orders as $order) {
            $linked = $orderNotifications[$order->id] ?? [];
            // Existing notification acknowledgements are respected; orders with no
            // alert record receive a persisted acknowledgement of their own.
            $unread = $linked ? collect($linked)->contains(fn ($n) => !in_array($n->id, $seenNotifications, true))
                : !in_array($order->id, $readOrders, true);
            $eligibility = app(VendorFulfillmentEligibilityService::class)->summary($order);
            $locked = !app(SellerOrderVisibilityService::class)->canSellerView($order);
            $reference = $locked ? app(OrderCommerceContractService::class)->restrictedAccessToken($order) : (string) $order->id;
            if (!$reference) continue;
            $general[] = ['kind' => 'order_alert', 'id' => $reference,
                'title' => ($eligibility['pending_on_vendor_side'] ?? false) ? 'طلبات معلقة من جهة التاجر' : translate('order_received'),
                'body' => ($eligibility['has_restrictions'] ?? false) ? VendorFulfillmentEligibilityService::MESSAGE : translate($locked ? 'vendor_order_waiting_for_administrative_release' : 'view_order'),
                'order_reference' => $locked ? '***'.substr((string) $order->id, -3) : (string) $order->id,
                // Order alerts start at administrative release, not customer checkout.
                'created_at' => $eligibility['forwarded_at'] ?? ($order->admin_order_reviewed_at ?? $order->created_at)?->toIso8601String(), 'unread' => $unread,
                'fulfillment_eligibility' => $eligibility,
                'action_url' => route('vendor.orders.details', $reference)];
        }
        $messages = $this->messages($seller)->with('thread')->latest('id')->get()->map(fn ($m) => [
            'kind' => 'message', 'id' => $m->id, 'thread_id' => $m->vendor_support_thread_id,
            'title' => $m->thread->subject, 'body' => $m->body, 'status' => $m->thread->status,
            'attachments' => [], 'created_at' => $m->created_at?->toIso8601String(),
            'unread' => !in_array($m->id, $readMessages, true),
        ])->values()->all();
        $threads = collect($messages)->groupBy('thread_id')->map(function ($items) {
            $latest = $items->first();
            return ['id' => $latest['thread_id'], 'title' => $latest['title'],
                'body' => $latest['body'], 'status' => $latest['status'], 'created_at' => $latest['created_at'],
                'unread_count' => $items->where('unread', true)->count(), 'message_count' => $items->count(),
                'action_url' => route('vendor.messages.thread', $latest['thread_id'])];
        })->values()->all();
        $general = collect($general)->sortByDesc('created_at')->values()->all();
        return ['subscription' => ['publishing_requires_subscription' => app(VendorAdvertisingAccessService::class)->requiresSubscription((int) $seller->id)], 'counts' => [
            'orders' => $orders->count(),
            'notifications' => collect($general)->where('unread', true)->count(),
            'messages' => collect($messages)->where('unread', true)->count(),
        ], 'notifications' => $general, 'messages' => $messages, 'threads' => $threads];
    }

    public function read(Seller $seller, string $kind, string $id): void
    {
        if ($kind === 'notification') {
            $record = $this->notifications($seller)->whereKey($id)->firstOrFail();
            NotificationSeen::updateOrCreate(['seller_id' => $seller->id, 'notification_id' => $record->id], ['created_at' => now()]);
            return;
        }
        if ($kind === 'message') {
            $recordId = $this->messages($seller)->whereKey($id)->firstOrFail()->id;
            $kind = 'support_message';
        } else {
            abort_unless($kind === 'order_alert', 404);
            $order = $this->orders($seller)->where(fn ($q) => ctype_digit($id) ? $q->whereKey($id) : $q->where('seller_restricted_access_token', $id))->firstOrFail();
            $recordId = $order->id;
            foreach ($this->notifications($seller)->get() as $notification) {
                if (preg_match('/#(\d+)/', (string) $notification->description, $match) && (int) $match[1] === $recordId) {
                    NotificationSeen::updateOrCreate(['seller_id' => $seller->id, 'notification_id' => $notification->id], ['created_at' => now()]);
                }
            }
        }
        DB::table('vendor_inbox_reads')->updateOrInsert(['seller_id' => $seller->id, 'kind' => $kind, 'record_id' => $recordId], ['created_at' => now(), 'updated_at' => now()]);
    }

    public function thread(Seller $seller, int $id): array
    {
        $thread = VendorSupportThread::where('seller_id', $seller->id)->with('messages')->findOrFail($id);
        $reads = DB::table('vendor_inbox_reads')->where('seller_id', $seller->id)->where('kind', 'support_message')->pluck('record_id')->all();
        return ['id' => $thread->id, 'subject' => $thread->subject, 'status' => $thread->status,
            'messages' => $thread->messages->map(fn ($m) => ['kind' => 'message', 'id' => $m->id,
                'author' => (int) ($m->sender_seller_id ?? 0) > 0 ? 'seller' : 'admin',
                'body' => $m->body, 'created_at' => $m->created_at?->toIso8601String(),
                'unread' => !in_array($m->id, $reads, true)])->all()];
    }

    public function reply(Seller $seller, int $id, string $body): array
    {
        return DB::transaction(function () use ($seller, $id, $body) {
            $thread = VendorSupportThread::where('seller_id', $seller->id)->lockForUpdate()->findOrFail($id);
            abort_unless($thread->status === 'open', 422);
            $thread->messages()->create([
                'admin_id' => 0,
                'sender_seller_id' => $seller->id,
                'body' => $body,
            ]);
            $thread->touch();

            return $this->thread($seller, $id);
        });
    }
}
