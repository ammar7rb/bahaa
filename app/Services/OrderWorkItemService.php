<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\OrderWorkItem;
use App\Models\OrderWorkItemEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

class OrderWorkItemService
{
    public function recordAdminReview(\App\Models\Order $order, array $items, float $vendorShippingTotal, float $vendorProcurementTotal, int $adminId): void
    {
        $lines = $order->details()->get();
        if (count($items) !== $lines->count()) throw new DomainException('راجع بيانات كل المنتجات قبل الإرسال.');
        $summary = app(OrderVendorPricingService::class)->summary($order, null, true);
        $priced = collect($summary['lines'])->keyBy('line_id');
        $customerShipping = 0.0;
        $vendorShipping = 0.0;
        foreach ($lines as $line) {
            $input = $items[$line->id] ?? null;
            if (!is_array($input)) throw new DomainException('بيانات إحدى حصص الطلب ناقصة.');
            $original = $priced[$line->id]['vendor_unit_price'] ?? null;
            if ($original === null || abs((float) $input['vendor_unit_price'] - (float) $original) > 0.000001) {
                throw new DomainException('تغير سعر التاجر الأصلي؛ حدّث الصفحة وراجع المنتج.');
            }
            if ($line->getRawOriginal('vendor_unit_price_snapshot') === null
                || abs((float) $line->getRawOriginal('vendor_unit_price_snapshot') - (float) $original) > 0.000001) {
                $line->forceFill(['vendor_unit_price_snapshot' => $original])->save();
            }
            $item = $this->ensureForLine($line);
            if (in_array($item->status, ['rejected', 'overdue'], true)) {
                $item = $this->transition($item, 'awaiting_admin', $adminId, 'admin', 'admin_reopened_line_review');
            }
            if ($item->status !== 'awaiting_admin') throw new DomainException('هذه الحصة خرجت من مراجعة الإدارة.');
            $sale = (float) ($priced[$line->id]['sale'] ?? 0);
            $procurement = (float) ($priced[$line->id]['procurement'] ?? 0);
            $vendorMerchandise = (float) $summary['procurement_amount'] > 0
                ? $vendorProcurementTotal * $procurement / (float) $summary['procurement_amount']
                : $vendorProcurementTotal / max(1, $lines->count());
            $customerShipping += (float) $input['shipping_customer_cost'];
            $vendorShipping += (float) $input['shipping_vendor_cost'];
            $before = $item->only(['vendor_unit_price', 'shipping_customer_cost', 'shipping_vendor_cost', 'customer_total', 'vendor_net_amount', 'expected_delivery_at', 'vendor_payment_due_at']);
            $item->forceFill([
                'vendor_unit_price' => $original,
                'shipping_customer_cost' => $input['shipping_customer_cost'],
                'shipping_vendor_cost' => $input['shipping_vendor_cost'],
                'customer_total' => round($sale + (float) $input['shipping_customer_cost'], 8),
                'vendor_net_amount' => round($vendorMerchandise + (float) $input['shipping_vendor_cost'], 8),
                'expected_delivery_at' => $input['expected_delivery_at'],
                'vendor_payment_due_at' => $input['vendor_payment_due_at'],
                'assigned_by_admin_id' => $adminId,
            ])->save();
            OrderWorkItemEvent::query()->create([
                'work_item_id' => $item->id, 'order_id' => $order->id, 'order_detail_id' => $line->id,
                'actor_id' => $adminId, 'actor_type' => 'admin', 'event_key' => 'admin_line_reviewed',
                'status_before' => $item->status, 'status_after' => $item->status,
                'snapshot' => ['before' => $before, 'after' => $item->only(array_keys($before)),
                    'company_share' => round($sale - $vendorMerchandise, 8)], 'created_at' => now(),
            ]);
        }
        if (abs($customerShipping - (float) $order->shipping_cost) > 0.01 || abs($vendorShipping - $vendorShippingTotal) > 0.01) {
            throw new DomainException('مجموع شحن المنتجات لا يطابق شحن الطلب ومستحق التاجر.');
        }
    }

    /** Keep operational work item records in sync with newly created order lines. */
    public function ensureForLine(OrderDetail $line): OrderWorkItem
    {
        return DB::transaction(function () use ($line): OrderWorkItem {
            $line->loadMissing('order');
            $order = $line->order;
            $status = $this->statusForOrder($order);
            $sentAt = $status === 'sent_to_vendor' ? ($order?->admin_order_reviewed_at ?? now()) : null;
            $item = OrderWorkItem::query()->firstOrCreate(['order_detail_id' => $line->id], [
                'order_id' => $line->order_id,
                'product_id' => $line->product_id,
                'seller_id' => $line->seller_id ?: $order?->seller_id,
                'status' => $status,
                'vendor_unit_price' => $line->getRawOriginal('vendor_unit_price_snapshot'),
                'sent_to_vendor_at' => $sentAt,
                'vendor_response_due_at' => $sentAt ? \Illuminate\Support\Carbon::parse($sentAt)->copy()->addHours(24) : null,
            ]);
            if ($item->wasRecentlyCreated) {
                OrderWorkItemEvent::query()->create([
                    'work_item_id' => $item->id, 'order_id' => $item->order_id,
                    'order_detail_id' => $line->id, 'actor_type' => 'system',
                    'event_key' => $status === 'sent_to_vendor' ? 'line_registered_after_release' : 'line_registered', 'status_after' => $item->status,
                    'snapshot' => ['product_id' => $item->product_id, 'seller_id' => $item->seller_id],
                    'created_at' => now(),
                ]);
            }
            return $item;
        });
    }

    /** Mirror the order-level vendor decision into every product line for the current seller. */
    public function syncOrderStatus(\App\Models\Order $order): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('order_work_items')) return;
        $next = $this->statusForOrder($order);
        if ($next === 'awaiting_admin') return;
        OrderWorkItem::query()->where('order_id', $order->id)->whereIn('status', ['awaiting_admin', 'sent_to_vendor', 'accepted', 'overdue', 'rejected'])
            ->each(function (OrderWorkItem $item) use ($next, $order): void {
                if ($item->status === $next) return;
                if ($next === 'sent_to_vendor' && in_array($item->status, ['accepted', 'rejected', 'overdue'], true)) return;
                $actorType = in_array($next, ['accepted', 'rejected'], true) ? 'seller' : ($next === 'sent_to_vendor' ? 'admin' : 'system');
                $actorId = $actorType === 'seller' ? (int) $order->seller_id : ($actorType === 'admin' ? $order->admin_order_reviewed_by : null);
                $eventKey = ['accepted'=>'vendor_accepted', 'rejected'=>'vendor_rejected',
                    'sent_to_vendor'=>'admin_sent_line', 'overdue'=>'vendor_response_timeout'][$next] ?? 'order_status_synchronized';
                $sentAt = $item->sent_to_vendor_at ?? $order->admin_order_reviewed_at ?? now();
                $this->transition($item, $next, $actorId, $actorType, $eventKey, [
                    'order_status' => $order->order_status, 'commerce_flow_status' => $order->commerce_flow_status,
                    'seller_shipping_response_status' => $order->seller_shipping_response_status,
                    'seller_shipping_rejection_reason' => $order->seller_shipping_rejection_reason,
                ], [
                    'vendor_responded_at' => in_array($next, ['accepted', 'rejected'], true) ? ($order->seller_shipping_response_at ?? now()) : $item->vendor_responded_at,
                    'vendor_response' => in_array($next, ['accepted', 'rejected'], true) ? $next : $item->vendor_response,
                    'sent_to_vendor_at' => $next === 'sent_to_vendor' ? $sentAt : $item->sent_to_vendor_at,
                    'vendor_response_due_at' => $next === 'sent_to_vendor' ? \Illuminate\Support\Carbon::parse($sentAt)->copy()->addHours(24) : $item->vendor_response_due_at,
                ]);
            });
    }

    public function syncLineStatus(OrderDetail $line): void
    {
        if (!$line->wasChanged('delivery_status') || !\Illuminate\Support\Facades\Schema::hasTable('order_work_items')) return;
        $next = match ($line->getRawOriginal('delivery_status')) {
            'delivered' => 'completed',
            'canceled' => 'canceled',
            default => null,
        };
        if (!$next) return;
        $item = OrderWorkItem::query()->where('order_detail_id', $line->id)->first();
        if (!$item || $item->status === $next) return;
        $this->transition($item, $next, null, 'system', 'product_line_status_synchronized', [
            'delivery_status' => $line->getRawOriginal('delivery_status'),
        ]);
    }

    public function markOverdue(int $limit = 500): int
    {
        $count = 0;
        OrderWorkItem::query()->where('status', 'sent_to_vendor')->where('vendor_response_due_at', '<=', now())
            ->orderBy('id')->limit($limit)->get()->each(function (OrderWorkItem $item) use (&$count): void {
                try {
                    $this->transition($item, 'overdue', null, 'system', 'vendor_response_timeout', [
                        'due_at' => $item->vendor_response_due_at?->toIso8601String(),
                    ]);
                    $count++;
                } catch (DomainException) {
                    // A concurrent vendor decision has already moved this line.
                }
            });
        return $count;
    }

    /** Apply a valid single-line status change and retain its before/after receipt. */
    public function transition(OrderWorkItem $item, string $next, ?int $actorId, string $actorType, string $eventKey, array $snapshot = [], array $attributes = []): OrderWorkItem
    {
        $allowed = [
            'awaiting_admin' => ['sent_to_vendor', 'accepted', 'rejected', 'canceled', 'completed'],
            'sent_to_vendor' => ['accepted', 'overdue', 'rejected', 'canceled', 'completed'],
            'overdue' => ['accepted', 'rejected', 'canceled', 'awaiting_admin'],
            'accepted' => ['completed', 'canceled'],
            'rejected' => ['awaiting_admin', 'canceled'],
            'canceled' => [], 'completed' => [],
        ];
        return DB::transaction(function () use ($item, $next, $actorId, $actorType, $eventKey, $snapshot, $attributes, $allowed): OrderWorkItem {
            $locked = OrderWorkItem::query()->lockForUpdate()->findOrFail($item->id);
            if (!in_array($next, OrderWorkItem::STATUSES, true) || !in_array($next, $allowed[$locked->status] ?? [], true)) {
                throw new DomainException('حالة حصة الطلب لا تسمح بهذا الانتقال.');
            }
            if ($next === 'overdue' && (!$locked->vendor_response_due_at || $locked->vendor_response_due_at->isFuture())) {
                throw new DomainException('مهلة رد التاجر لم تنته بعد.');
            }
            $before = $locked->status;
            $financialFields = ['vendor_unit_price', 'shipping_customer_cost', 'shipping_vendor_cost', 'customer_total', 'vendor_net_amount'];
            $financialBefore = $locked->only($financialFields);
            $locked->forceFill(array_merge($attributes, ['status' => $next]))->save();
            OrderWorkItemEvent::query()->create([
                'work_item_id' => $locked->id, 'order_id' => $locked->order_id, 'order_detail_id' => $locked->order_detail_id,
                'actor_id' => $actorId, 'actor_type' => $actorType, 'event_key' => $eventKey,
                'status_before' => $before, 'status_after' => $next,
                'snapshot' => array_merge($snapshot, ['financial_before' => $financialBefore,
                    'financial_after' => $locked->only($financialFields)]), 'created_at' => now(),
            ]);
            return $locked->fresh();
        });
    }

    private function statusForOrder(?\App\Models\Order $order): string
    {
        if (!$order) return 'awaiting_admin';
        if ($order->order_status === 'canceled') return 'canceled';
        if ($order->order_status === 'delivered') return 'completed';
        if ($order->admin_order_review_status === 'seller_shipping_rejected') return 'rejected';
        if ($order->seller_shipping_response_status === 'accepted') return 'accepted';
        if ($order->admin_order_review_status === 'seller_shipping_response_overdue') return 'overdue';
        if (in_array($order->commerce_flow_status, ['released_to_seller', 'fulfillment_in_progress', 'fulfillment_completed'], true)) return 'sent_to_vendor';
        return 'awaiting_admin';
    }
}
