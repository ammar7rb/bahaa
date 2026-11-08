<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read existing receipts without changing or inventing their historical values. */
class OrderTimelineService
{
    public function forOrder(Order $order): Collection
    {
        $events = collect();
        $links = Schema::hasTable('order_communication_links')
            ? DB::table('order_communication_links')->where('order_id', $order->id)->get() : collect();
        $events = $events->merge($links->map(fn ($link) => $this->event('ربط محادثة', $link->channel, $link->created_at,
            'admin', $link->admin_id, null, null, ['resource_id'=>$link->resource_id])));
        if (Schema::hasTable('order_work_item_events')) {
            $ids = Schema::hasTable('order_reassignment_transfers')
                ? DB::table('order_reassignment_transfers')->where('source_order_id', $order->id)
                    ->orWhere('target_order_id', $order->id)->pluck('work_item_id') : collect();
            $events = $events->merge(DB::table('order_work_item_events')->where(function ($q) use ($order, $ids) {
                $q->where('order_id', $order->id)->orWhereIn('work_item_id', $ids);
            })->get()->map(fn ($e) => $this->event('حصة المنتج', $e->event_key, $e->created_at,
                $e->actor_type, $e->actor_id, $e->status_before, $e->status_after, $e->snapshot)));
        }
        if (Schema::hasTable('order_admin_gate_decisions')) {
            $events = $events->merge(DB::table('order_admin_gate_decisions')->where('order_id', $order->id)->get()
                ->map(fn ($e) => $this->event('قرار الطلب', $e->action, $e->created_at,
                    $e->admin_id ? 'admin' : (in_array($e->action, ['seller_shipping_accepted', 'seller_shipping_rejected'], true) ? 'seller' : 'system'),
                    $e->admin_id ?: (in_array($e->action, ['seller_shipping_accepted', 'seller_shipping_rejected'], true) ? $order->seller_id : null),
                    $e->status_before, $e->status_after, $e->snapshot, $e->note)));
        }
        if (Schema::hasTable('order_financial_events')) {
            $events = $events->merge(DB::table('order_financial_events')->where('order_id', $order->id)->get()
                ->map(function ($e) {
                    $data = json_decode((string) $e->snapshot, true) ?: [];
                    return $this->event('تدقيق مالي', $e->event_type, $e->created_at,
                        $data['actor_type'] ?? ($e->admin_id ? 'admin' : 'system'),
                        $data['actor_id'] ?? $e->admin_id, null, null, $data);
                }));
        }
        if (Schema::hasTable('order_logistics_events')) {
            $events = $events->merge(DB::table('order_logistics_events')->where('order_id', $order->id)->get()
                ->map(fn ($e) => $this->event('التنفيذ والشحن', $e->event_type, $e->created_at, $e->actor_type,
                    $e->actor_id, $e->previous_order_status ?? null, $e->order_status ?? null, $e->metadata, $e->note)));
        }
        if (Schema::hasTable('vendor_fulfillment_requests') && Schema::hasTable('vendor_fulfillment_events')) {
            $events = $events->merge(DB::table('vendor_fulfillment_events as event')
                ->join('vendor_fulfillment_requests as request', 'request.id', '=', 'event.request_id')
                ->where('request.order_id', $order->id)
                ->select('event.*', 'request.seller_id')->get()->map(function ($event) {
                    $data = json_decode((string) ($event->metadata ?? ''), true) ?: [];
                    $actor = $data['actor'] ?? 'system';
                    return $this->event('جزء التنفيذ', $event->event_type, $event->created_at, $actor,
                        $data['actor_id'] ?? ($actor === 'seller' ? $event->seller_id : null),
                        null, null, $data + ['request_id' => $event->request_id], $data['reason'] ?? null);
                }));
        }
        if (Schema::hasTable('order_reassignment_transfers')) {
            $events = $events->merge(DB::table('order_reassignment_transfers')->where('source_order_id', $order->id)
                ->orWhere('target_order_id', $order->id)->get()->map(fn ($e) => $this->event('إعادة التعيين', 'نقل منتج',
                    $e->created_at, 'admin', $e->admin_id, null, null,
                    ['before' => json_decode($e->before_snapshot, true), 'after' => json_decode($e->after_snapshot, true),
                        'customer_amount' => $e->customer_amount_transferred, 'source_order_id' => $e->source_order_id,
                        'target_order_id' => $e->target_order_id], $e->reason)));
        }
        if (Schema::hasTable('order_cancellation_settlements')) {
            $events = $events->merge(DB::table('order_cancellation_settlements')->where('order_id', $order->id)->get()
                ->map(fn ($e) => $this->event('تسوية الإلغاء', $e->canceled_by, $e->created_at, 'admin',
                    $e->reviewed_by_admin_id, null, null, ['refund' => $e->refund_amount, 'fee' => $e->fee_amount,
                        'debited' => $e->seller_debited_amount, 'debt' => $e->seller_debt_amount], $e->reason)));
        }
        if (Schema::hasTable('order_risk_investigations') && Schema::hasTable('order_risk_investigation_messages')) {
            $events = $events->merge(DB::table('order_risk_investigation_messages as message')
                ->join('order_risk_investigations as investigation', 'investigation.id', '=', 'message.investigation_id')
                ->where('investigation.order_id', $order->id)->select('message.*')->get()
                ->map(fn ($e) => $this->event('رسالة التحقيق', 'إلى '.$e->recipient_type, $e->created_at,
                    'admin', $e->admin_id, null, null, null, $e->message)));
        }
        if (Schema::hasTable('support_ticket_convs')) {
            $ticketIds = collect([$order->seller_shipping_support_ticket_id])->filter();
            if (Schema::hasTable('support_tickets') && $order->customer_id) {
                $ticketIds = $ticketIds->merge(DB::table('support_tickets')->where('customer_id', $order->customer_id)
                    ->whereIn('id', $links->where('channel', 'support_ticket')->pluck('resource_id'))->pluck('id'));
            }
            if (Schema::hasTable('order_insurances') && Schema::hasColumn('order_insurances', 'support_ticket_id')) {
                $ticketIds = $ticketIds->merge(DB::table('order_insurances')->where('order_id', $order->id)
                    ->whereNotNull('support_ticket_id')->pluck('support_ticket_id'));
            }
            if (Schema::hasTable('order_risk_investigations') && Schema::hasTable('order_risk_investigation_messages')) {
                $ticketIds = $ticketIds->merge(DB::table('order_risk_investigation_messages as message')
                    ->join('order_risk_investigations as investigation', 'investigation.id', '=', 'message.investigation_id')
                    ->where('investigation.order_id', $order->id)->whereNotNull('message.support_ticket_id')
                    ->pluck('message.support_ticket_id'));
            }
            if ($ticketIds->isNotEmpty()) {
                $events = $events->merge(DB::table('support_ticket_convs')->whereIn('support_ticket_id', $ticketIds->unique())->get()
                    ->map(fn ($e) => $this->event('محادثة مرتبطة بالطلب', 'رسالة دعم', $e->created_at,
                        $e->admin_id ? 'admin' : 'customer', $e->admin_id ?: $order->customer_id,
                        null, null, ['support_ticket_id' => $e->support_ticket_id], $e->admin_message ?: $e->customer_message)));
            }
        }
        if ($order->seller_id && Schema::hasTable('contacts') && Schema::hasColumn('contacts', 'mobile_support_token_hash')
            && Schema::hasTable('contact_conversation_messages')) {
            $contact = DB::table('contacts')->where('mobile_support_token_hash',
                hash('sha256', 'seller-shipping:'.$order->seller_id.':'.$order->id))->first();
            if ($contact) {
                $events->push($this->event('محادثة شحن التاجر', 'فتح المحادثة', $contact->created_at,
                    'seller', $order->seller_id, null, null, ['contact_id'=>$contact->id], $contact->message));
                $events = $events->merge(DB::table('contact_conversation_messages')->where('contact_id', $contact->id)->get()
                    ->map(fn ($message) => $this->event('محادثة شحن التاجر', 'رسالة', $message->created_at,
                        $message->sender === 'requester' ? 'seller' : 'admin',
                        $message->sender === 'requester' ? $order->seller_id : null, null, null,
                        ['contact_id'=>$contact->id], $message->body)));
            }
        }
        if (Schema::hasTable('vendor_support_threads') && Schema::hasColumn('vendor_support_threads', 'order_id')
            && Schema::hasTable('vendor_support_messages')) {
            $events = $events->merge(DB::table('vendor_support_messages as message')
                ->join('vendor_support_threads as thread', 'thread.id', '=', 'message.vendor_support_thread_id')
                ->where(fn ($query) => $query->where('thread.order_id', $order->id)
                    ->orWhereIn('thread.id', $links->where('channel', 'vendor_thread')->pluck('resource_id')))
                ->where('thread.seller_id', $order->seller_id)
                ->select('message.*')->get()->map(fn ($message) => $this->event('محادثة التاجر', 'رسالة دعم',
                    $message->created_at, $message->admin_id ? 'admin' : 'seller',
                    $message->admin_id ?: $order->seller_id, null, null,
                    ['thread_id'=>$message->vendor_support_thread_id], $message->body)));
        }
        if (Schema::hasTable('chattings') && $order->customer_id) {
            $events = $events->merge(DB::table('chattings')->whereIn('id', $links->where('channel', 'chat_message')->pluck('resource_id'))
                ->where('user_id', $order->customer_id)
                ->where(fn ($q) => $q->whereNull('seller_id')->orWhere('seller_id', 0)->orWhere('seller_id', $order->seller_id))
                ->get()->map(fn ($message) => $this->event('رسالة مرتبطة', 'محادثة', $message->created_at,
                    $message->sent_by_customer ? 'customer' : ($message->sent_by_seller ? 'seller' : 'admin'),
                    $message->sent_by_customer ? $order->customer_id : ($message->sent_by_seller ? $order->seller_id : $message->admin_id),
                    null, null, ['message_id'=>$message->id], $message->message)));
        }
        return $events->sortByDesc('at')->values();
    }

    private function event(string $source, ?string $type, ?string $at, ?string $actor, mixed $actorId,
        ?string $before, ?string $after, mixed $snapshot, ?string $note = null): array
    {
        $data = is_string($snapshot) ? json_decode($snapshot, true) : $snapshot;
        return compact('source', 'type', 'at', 'actor', 'actorId', 'before', 'after', 'note') + ['snapshot' => $data];
    }
}
