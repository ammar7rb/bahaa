<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderVendorPricing;
use App\Models\VendorFulfillmentRequest;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Immutable forwarding snapshots. Reading or buying a package never unlocks or accepts a request. */
class VendorFulfillmentEligibilityService
{
    public const MESSAGE = 'برجاء اختيار إحدى الباقات';

    public function available(): bool
    {
        // Existing restrictions remain protected even when new forwarding is deferred.
        return Schema::hasTable('vendor_fulfillment_requests');
    }

    public function requests(Order $order): Collection
    {
        return $this->available() ? VendorFulfillmentRequest::where('order_id', $order->id)
            ->where('seller_id', $order->seller_id)->orderBy('id')->get() : collect();
    }

    public function hasRestrictions(Order $order): bool
    {
        return $this->requests($order)->contains(fn ($request) => $this->restricted($request));
    }

    public function restricted(object $row): bool
    {
        return in_array($row->status,['locked','pending_vendor'],true)
            || ($row->status==='canceled' && $row->portion==='outside_slot' && !$row->unlocked_at);
    }

    /** Protected-field filters must not reveal a locked recipient or item through result counts. */
    public function constrainVendorFilters($query, array $filters, ?string $search): void
    {
        if (!$this->available() || (trim((string) $search) === '' && (int) ($filters['customer_id'] ?? 0) <= 0 && (int) ($filters['delivery_man_id'] ?? 0) <= 0)) return;
        $query->whereNotExists(fn ($requests) => $requests->selectRaw('1')->from('vendor_fulfillment_requests as r')
            ->whereColumn('r.order_id', 'orders.id')->whereColumn('r.seller_id', 'orders.seller_id')
            ->where(fn($status)=>$status->whereIn('r.status',['locked','pending_vendor'])->orWhere(fn($closed)=>$closed->where('r.status','canceled')->where('r.portion','outside_slot')->whereNull('r.unlocked_at'))));
    }

    public function slotFor(int $sellerId, int $productId): ?int
    {
        return DB::table('vendor_advertising_slots as s')
            ->join('seller_package_subscriptions as p', 'p.id', '=', 's.seller_package_subscription_id')
            ->where('s.seller_id', $sellerId)->where('p.seller_id', $sellerId)->where('s.product_id', $productId)
            ->where('p.payment_status', 'paid')->whereIn('p.status', ['active', 'scheduled'])
            ->where('p.starts_at', '<=', now())->where('p.expires_at', '>', now())
            ->where('s.starts_at', '<=', now())->where('s.expires_at', '>', now())
            ->orderBy('s.id')->value('s.id');
    }

    public function forward(Order $order, array $allocation = []): Collection
    {
        if (!config('vendor_order_eligibility.enabled', false) || !$this->available() || $order->seller_is !== 'seller') return collect();
        return DB::transaction(function () use ($order, $allocation) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            // Serialize with slot allocation/removal and future debt operations for this vendor.
            DB::table('sellers')->where('id', $locked->seller_id)->lockForUpdate()->first();
            if (($existing = $this->requests($locked))->isNotEmpty()) return $existing;
            $pricing = OrderVendorPricing::where('order_id', $locked->id)->first();
            if (!$pricing) throw new DomainException('اعتمد مبلغ التاجر قبل تحويل طلب التنفيذ.');
            $groups = ['advertising' => [], 'outside_slot' => []];
            $original = ['advertising' => 0.0, 'outside_slot' => 0.0];
            foreach ($locked->details()->lockForUpdate()->get() as $line) {
                if ((int) $line->seller_id !== (int) $locked->seller_id) throw new DomainException('بائع سطر الطلب لا يطابق طلب التنفيذ.');
                $price = $line->getRawOriginal('vendor_unit_price_snapshot');
                if ($price === null || !is_numeric($price) || (float) $price < 0) throw new DomainException('سعر التاجر الأصلي غير محفوظ.');
                $slot = $this->slotFor((int) $locked->seller_id, (int) $line->product_id);
                $portion = $slot ? 'advertising' : 'outside_slot';
                $groups[$portion][] = ['line' => $line, 'slot' => $slot];
                $original[$portion] += (float) $price * (int) $line->qty;
            }
            if (!$groups['advertising'] && !$groups['outside_slot']) throw new DomainException('طلب التنفيذ لا يحتوي منتجات.');
            $total = (float) $pricing->procurement_amount;
            $shipping = $locked->shipping_fulfillment_mode === 'seller_shipping' ? (float) $locked->shipping_seller_entitlement : 0.0;
            $mixed = $groups['advertising'] && $groups['outside_slot'];
            if ($mixed && abs(array_sum($original) - $total) > 0.00000001 && !isset($allocation['accessible_procurement_amount'])) {
                throw new DomainException('حدد مبلغ التوريد للجزء المتاح من الطلب المختلط بعد تعديل إجمالي التاجر.');
            }
            if ($mixed && $shipping > 0 && !isset($allocation['accessible_shipping_amount'])) {
                throw new DomainException('حدد استحقاق الشحن للجزء المتاح من الطلب المختلط.');
            }
            $accessible = !$groups['advertising'] ? 0.0 : (!$mixed ? $total : (float) ($allocation['accessible_procurement_amount'] ?? $original['advertising']));
            $accessibleShipping = !$groups['advertising'] ? 0.0 : (!$mixed ? $shipping : (float) ($allocation['accessible_shipping_amount'] ?? 0));
            foreach ([[$accessible, $total], [$accessibleShipping, $shipping]] as [$amount, $maximum]) {
                if (!is_finite($amount) || $amount < 0 || $amount > $maximum) throw new DomainException('توزيع مبلغ التاجر أو الشحن غير صحيح.');
            }
            $at = now();
            foreach ($groups as $portion => $items) {
                if (!$items) continue;
                $restricted = $portion === 'outside_slot';
                $request = VendorFulfillmentRequest::create([
                    'order_id' => $locked->id, 'seller_id' => $locked->seller_id, 'portion' => $portion,
                    'status' => $restricted ? 'locked' : 'available',
                    'vendor_amount' => number_format($restricted ? $total - $accessible : $accessible, 8, '.', ''),
                    'shipping_amount' => number_format($restricted ? $shipping - $accessibleShipping : $accessibleShipping, 8, '.', ''),
                    'forwarded_at' => $at, 'pending_due_at' => $restricted ? $at->copy()->addHours(24) : null,
                ]);
                foreach ($items as $item) DB::table('vendor_fulfillment_request_items')->insert([
                    'request_id' => $request->id, 'order_detail_id' => $item['line']->id,
                    'product_id' => $item['line']->product_id, 'advertising_slot_id' => $item['slot'], 'created_at' => $at,
                ]);
                DB::table('vendor_fulfillment_events')->insert(['request_id' => $request->id, 'event_type' => 'forwarded', 'created_at' => $at]);
            }
            return $this->requests($locked);
        });
    }

    public function lineAllowed(OrderDetail $line): bool
    {
        if (!$this->available()) return true;
        $item = DB::table('vendor_fulfillment_request_items as i')
            ->join('vendor_fulfillment_requests as r', 'r.id', '=', 'i.request_id')
            ->where('i.order_detail_id', $line->id)->where('r.order_id', $line->order_id)
            ->where('r.seller_id', $line->seller_id)->first(['r.status','r.portion','r.unlocked_at']);
        if ($item) return !$this->restricted($item);
        // No snapshot means historical behavior, except a line injected into a snapshotted request.
        return !VendorFulfillmentRequest::where('order_id', $line->order_id)->exists();
    }

    public function summary(Order $order): ?array
    {
        $requests = $this->requests($order);
        if ($requests->isEmpty()) return null;
        $locked = $requests->filter(fn ($row) => $this->restricted($row));
        $accessible = $requests->diff($locked);
        // Closed portions remain visible for their decision history, but no
        // longer form part of the vendor's accessible fulfillment entitlement.
        $payable = $accessible->reject(fn ($row) => in_array($row->status, ['rejected', 'canceled', 'reassigned'], true));
        $managed = Schema::hasColumn('vendor_fulfillment_requests', 'accepted_at');
        return [
            'details_locked' => $accessible->isEmpty(), 'partially_locked' => $locked->isNotEmpty() && $accessible->isNotEmpty(),
            'has_restrictions' => $locked->isNotEmpty(), 'message' => $locked->isNotEmpty() ? self::MESSAGE : null,
            'forwarded_at' => $requests->first()->forwarded_at->toIso8601String(),
            'pending_on_vendor_side' => $locked->contains('status', 'pending_vendor'),
            'pending_label' => 'طلبات معلقة من جهة التاجر',
            'accessible_amount' => round((float) $payable->sum('vendor_amount') + (float) $payable->sum('shipping_amount'), 8),
            'restricted_amount' => round((float) $locked->sum('vendor_amount') + (float) $locked->sum('shipping_amount'), 8),
            'accessible_procurement_amount' => (float) $payable->sum('vendor_amount'),
            'accessible_shipping_amount' => (float) $payable->sum('shipping_amount'),
            'amount_basis' => $accessible->isEmpty() ? 'vendor_total' : 'restricted_portion_separate',
            'portion_actions_enabled' => $managed,
            'portions' => $requests->map(fn ($row) => [
                'id' => $row->id, 'status' => $row->status,
                'amount' => round((float) $row->vendor_amount + (float) $row->shipping_amount, 8),
                'forwarded_at' => $row->forwarded_at->toIso8601String(),
                'pending_due_at' => $row->pending_due_at?->toIso8601String(), 'pending_at' => $row->pending_at?->toIso8601String(),
            ] + ($managed ? app(VendorFulfillmentDecisionService::class)->present($row, $order) : []))->all(),
        ];
    }

    public function classifyPending(int $limit = 200): int
    {
        if (!$this->available()) return 0;
        $changed = 0;
        VendorFulfillmentRequest::where('status', 'locked')->where('pending_due_at', '<=', now())
            ->orderBy('id')->chunkById($limit, function ($rows) use (&$changed) {
                foreach ($rows as $candidate) {
                    $changed += DB::transaction(function () use ($candidate) {
                        if(DB::getDriverName()==='sqlite')DB::table('sellers')->where('id',$candidate->seller_id)->update(['id'=>DB::raw('id')]);
                        // Same order-then-request lock order used by forwarding and future unlocking.
                        $order = Order::whereKey($candidate->order_id)->lockForUpdate()->firstOrFail();
                        if (in_array($order->order_status, ['canceled', 'delivered', 'returned', 'failed'], true)) return 0;
                        $row = VendorFulfillmentRequest::lockForUpdate()->findOrFail($candidate->id);
                        if ($row->status !== 'locked' || !$row->pending_due_at || $row->pending_due_at->gt(now())) return 0;
                        $row->update(['status' => 'pending_vendor', 'pending_at' => now()]);
                        $notification = Schema::hasTable('notifications') ? Notification::create([
                            'sent_by' => 'admin', 'sent_to' => 'seller', 'seller_id' => $row->seller_id,
                            'title' => 'طلبات معلقة من جهة التاجر',
                            'description' => 'طلب تنفيذ معلق #'.$row->order_id.' — '.self::MESSAGE,
                            'notification_count' => 1, 'status' => 1,
                        ]) : null;
                        DB::table('vendor_fulfillment_events')->insert([
                            'request_id' => $row->id, 'event_type' => 'pending_vendor',
                            'notification_id' => $notification?->id, 'created_at' => now(),
                        ]);
                        return 1;
                    }, 3);
                }
            });
        return $changed;
    }
}
