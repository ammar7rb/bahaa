<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationSeen;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerActivationTicketMessage;
use Illuminate\Support\Facades\DB;

/** Reading an alert never mutates the order's operational/payment status. */
class VendorInboxService
{
    public function protectNotification(Seller $seller, Notification $notification): Notification
    {
        if (preg_match('/#(\d+)/', (string) $notification->description, $match)) {
            $order = $this->orders($seller)->with('sellerOrderInsurance')->whereKey((int) $match[1])->first();
            if ($order && !app(SellerOrderVisibilityService::class)->canSellerView($order)) {
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
        return SellerActivationTicketMessage::query()->where('sender_type', 'admin')
            ->whereHas('ticket', fn ($q) => $q->where('seller_id', $seller->id));
    }

    public function orders(Seller $seller)
    {
        return Order::query()->sellerAccessible()->where(['seller_id' => $seller->id, 'seller_is' => 'seller']);
    }

    public function snapshot(Seller $seller): array
    {
        $reads = DB::table('vendor_inbox_reads')->where('seller_id', $seller->id)->get();
        $readMessages = $reads->where('kind', 'message')->pluck('record_id')->all();
        $readOrders = $reads->where('kind', 'order_alert')->pluck('record_id')->all();
        $seenNotifications = NotificationSeen::where('seller_id', $seller->id)->pluck('notification_id')->all();
        $orders = $this->orders($seller)->with('sellerOrderInsurance')->select(['id', 'order_status', 'order_amount', 'seller_restricted_access_token', 'created_at', 'seller_id', 'commerce_flow_version', 'commerce_flow_status', 'admin_order_review_status', 'post_purchase_status'])->latest('id')->get();
        $orderIds = $orders->pluck('id')->all();
        $notifications = $this->notifications($seller)->latest('id')->get();
        $orderNotifications = [];
        $general = [];
        foreach ($notifications as $notification) {
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
            $locked = !app(SellerOrderVisibilityService::class)->canSellerView($order);
            $reference = $locked ? app(OrderCommerceContractService::class)->restrictedAccessToken($order) : (string) $order->id;
            if (!$reference) continue;
            $general[] = ['kind' => 'order_alert', 'id' => $reference,
                'title' => translate('order_received'), 'body' => translate($locked ? 'details_hidden_until_insurance_is_paid' : 'view_order'),
                'order_reference' => $locked ? '***'.substr((string) $order->id, -3) : (string) $order->id,
                'created_at' => $order->created_at?->toIso8601String(), 'unread' => $unread,
                'action_url' => route('vendor.orders.details', $reference)];
        }
        $messages = $this->messages($seller)->latest('id')->get()->map(fn ($m) => [
            'kind' => 'message', 'id' => $m->id, 'title' => translate('administration_message'), 'body' => $m->body,
            'attachments' => $m->attachment_full_url, 'created_at' => $m->created_at?->toIso8601String(),
            'unread' => !in_array($m->id, $readMessages, true),
        ])->values()->all();
        $general = collect($general)->sortByDesc('created_at')->values()->all();
        return ['counts' => [
            'orders' => $orders->filter(fn ($o) => $o->order_status === 'pending' || in_array($o->commerce_flow_status, ['seller_insurance_pending', 'seller_insurance_under_review'], true))->count(),
            'notifications' => collect($general)->where('unread', true)->count(),
            'messages' => collect($messages)->where('unread', true)->count(),
        ], 'notifications' => $general, 'messages' => $messages];
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
}
