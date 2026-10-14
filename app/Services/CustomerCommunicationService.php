<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketConv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CustomerCommunicationService
{
    public function notifications(int $customerId)
    {
        return Notification::active()->forCustomer($customerId)
            ->where('sent_to', 'customer')->whereIn('sent_by', ['admin', 'system'])
            ->latest('id');
    }

    public function messages(int $customerId)
    {
        return SupportTicket::visibleToSubject()->where('customer_id', $customerId)
            ->with('latestConversation')->latest('updated_at')->latest('id');
    }

    public function notificationItem(Notification $notice, int $customerId): array
    {
        $target = null;
        $url = null;
        $key = (string) $notice->getRawOriginal('customer_event_key');
        if (preg_match('/^initial-order-(\d+)-requirements$/', $key, $match)
            && Order::whereKey($match[1])->where('customer_id', $customerId)->where('is_guest', 0)->exists()) {
            $target = ['type' => 'subscription', 'id' => (int) $match[1]];
            $url = route('customer.subscription.index');
        } elseif (preg_match('/^order-status-(\d+)-/', $key, $match)
            && Order::whereKey($match[1])->where('customer_id', $customerId)->where('is_guest', 0)->exists()) {
            $target = ['type' => 'order', 'id' => (int) $match[1]];
            $url = route('account-order-details', ['id' => (int) $match[1]]);
        } elseif (preg_match('/^support-reply-(\d+)-/', $key, $match)
            && SupportTicket::visibleToSubject()->whereKey($match[1])->where('customer_id', $customerId)->exists()) {
            $target = ['type' => 'message', 'id' => (int) $match[1]];
            $url = route('support-ticket.index', $match[1]);
        }
        return ['id' => $notice->id, 'title' => $notice->title, 'description' => $notice->description,
            'created_at' => $notice->created_at?->toIso8601String(), 'target' => $target, 'url' => $url];
    }

    public function messageItem(SupportTicket $ticket): array
    {
        $latest = $ticket->latestConversation;
        return ['id' => $ticket->id, 'title' => $ticket->subject, 'status' => $ticket->status, 'purpose' => $ticket->purpose,
            'description' => Str::limit($latest?->admin_message ?: ($latest?->customer_message ?: $ticket->description), 180),
            'created_at' => $ticket->updated_at?->toIso8601String(),
            'target' => ['type' => 'message', 'id' => $ticket->id],
            'url' => route('support-ticket.index', $ticket->id)];
    }

    public function recordOrderStatus(object|array $order): void
    {
        $customerId = (int) data_get($order, 'customer_id');
        $orderId = (int) data_get($order, 'id');
        $orderStatus = (string) data_get($order, 'order_status');
        if (data_get($order, 'is_guest') || !$customerId || !$orderId) return;
        $labels = ['pending' => 'قيد الانتظار', 'confirmed' => 'تم التأكيد', 'processing' => 'قيد التجهيز',
            'out_for_delivery' => 'في الطريق للتسليم', 'delivered' => 'تم التسليم', 'returned' => 'تم الإرجاع',
            'failed' => 'تعذر التسليم', 'canceled' => 'ملغي'];
        $status = $labels[$orderStatus] ?? $orderStatus;
        $version = hash('sha256', (string) data_get($order, 'updated_at').'|'.$orderStatus);
        $this->record($customerId, 'order-status-'.$orderId.'-'.$version,
            'تحديث الطلب #'.$orderId, 'حالة طلبك: '.$status);
    }

    public function recordSupportReply(SupportTicketConv $reply): void
    {
        if (!$reply->admin_id) return;
        $ticket = SupportTicket::visibleToSubject()->find($reply->support_ticket_id);
        if (!$ticket || !$ticket->customer_id) return;
        $this->record((int) $ticket->customer_id, 'support-reply-'.$ticket->id.'-'.$reply->id,
            'رد الإدارة: '.$ticket->subject, Str::limit($reply->admin_message ?: 'أرسلت الإدارة مرفقاً في المحادثة.', 180));
    }

    private function record(int $customerId, string $key, string $title, string $description): void
    {
        // Reuse the existing customer event ledger; no financial data or schema is changed.
        if (!Schema::hasColumn('notifications', 'customer_event_key')) return;
        DB::table('notifications')->insertOrIgnore(['customer_id' => $customerId, 'customer_event_key' => $key,
            'sent_by' => 'system', 'sent_to' => 'customer', 'title' => $title, 'description' => $description,
            'image' => '', 'status' => 1, 'notification_count' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
}
