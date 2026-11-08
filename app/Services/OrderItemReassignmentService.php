<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderTransaction;
use App\Models\OrderVendorPricing;
use App\Models\OrderWorkItem;
use App\Models\OrderWorkItemEvent;
use App\Models\Product;
use App\Models\SellerSettlement;
use App\Utils\OrderManager;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderItemReassignmentService
{
    public function candidates(OrderWorkItem $item, ?string $search = null)
    {
        $source = Product::query()->findOrFail($item->product_id);
        return Product::query()->with('seller.shop')->whereHas('seller.shop')
            ->where('user_id', '<>', $item->seller_id)->where('added_by', 'seller')
            ->where('status', 1)->where('name', $source->getRawOriginal('name'))
            ->where('product_type', $source->product_type)
            ->where('category_id', $source->category_id)
            ->where('current_stock', '>=', (int) ($item->line?->qty ?? 1))
            ->when($source->brand_id, fn ($q) => $q->where('brand_id', $source->brand_id))
            ->when($search, fn ($q) => $q->where(fn ($sub) => $sub->where('code', 'like', '%'.$search.'%')
                ->orWhereHas('seller.shop', fn ($shop) => $shop->where('name', 'like', '%'.$search.'%'))))
            ->orderBy('user_id')->limit(50)->get();
    }

    public function transfer(int $workItemId, int $targetProductId, int $adminId, string $reason): Order
    {
        return DB::transaction(function () use ($workItemId, $targetProductId, $adminId, $reason): Order {
            $item = OrderWorkItem::query()->lockForUpdate()->findOrFail($workItemId);
            $sourceOrder = Order::query()->lockForUpdate()->findOrFail($item->order_id);
            $line = OrderDetail::query()->lockForUpdate()->findOrFail($item->order_detail_id);
            $sellerCanceled = $item->status === 'canceled';
            if ($sellerCanceled && (!Schema::hasTable('order_cancellation_settlements')
                || !DB::table('order_cancellation_settlements')->where('operation_key', 'seller:'.$item->id)
                    ->where('seller_id', $item->seller_id)->where('canceled_by', 'seller')->exists())) {
                throw new DomainException('اعتمد سجل غرامة إلغاء التاجر قبل إعادة تعيين المنتج.');
            }
            if ((int) $line->order_id !== (int) $sourceOrder->id
                || (int) $line->seller_id !== (int) $item->seller_id
                || (int) $line->product_id !== (int) $item->product_id
                || (int) $sourceOrder->seller_id !== (int) $item->seller_id
                || !in_array($item->status, ['sent_to_vendor', 'accepted', 'overdue', 'rejected', 'canceled'], true)
                || $sourceOrder->payment_status !== 'paid' || $sourceOrder->seller_is !== 'seller'
                || app(OrderPaymentSummaryService::class)->forOrder($sourceOrder)['incomplete']
                || $sourceOrder->commerce_flow_status === 'under_investigation'
                || in_array($sourceOrder->order_status, ['out_for_delivery', 'delivered', 'returned'], true)
                || $sourceOrder->shipment_reference || $sourceOrder->third_party_delivery_tracking_id
                || in_array($line->delivery_status, ['delivered', 'out_for_delivery', 'returned'], true)
                || $line->refund_request || $line->refund_started_at
                || SellerSettlement::query()->where('order_id', $sourceOrder->id)->exists()) {
                throw new DomainException('لا يمكن نقل حصة بدأت تسويتها أو تسليمها.');
            }
            if (Schema::hasTable('order_shipping_proofs') && DB::table('order_shipping_proofs')->where('order_id', $sourceOrder->id)->exists()) {
                throw new DomainException('يوجد إثبات شحن لهذا الطلب؛ راجع الشحن قبل النقل.');
            }
            if (Schema::hasTable('post_purchase_invoices') && DB::table('post_purchase_invoices')->where('order_id', $sourceOrder->id)->exists()) {
                throw new DomainException('فاتورة ما بعد الشراء تحتاج معالجة مستقلة قبل نقل المنتج.');
            }
            if (Schema::hasTable('order_admin_purchase_refunds') && DB::table('order_admin_purchase_refunds')->where('order_id', $sourceOrder->id)->exists()) {
                throw new DomainException('بدأ استرداد مبلغ الطلب؛ لا يمكن تقسيمه أثناء الاسترداد.');
            }
            $otherCancellations = DB::table('order_cancellation_settlements')->where('order_id', $sourceOrder->id);
            if ($sellerCanceled) $otherCancellations->where('operation_key', '<>', 'seller:'.$item->id);
            if (Schema::hasTable('order_cancellation_settlements') && $otherCancellations->exists()) {
                throw new DomainException('تمت معالجة إلغاء مالي لهذا الطلب؛ لا يمكن إعادة تعيين المنتج.');
            }
            if ($line->refundRequest()->exists()) {
                throw new DomainException('بدأ استرداد هذا المنتج؛ راجع الاسترداد قبل النقل.');
            }
            $sourceProduct = Product::query()->lockForUpdate()->findOrFail($line->product_id);
            $target = Product::query()->lockForUpdate()->findOrFail($targetProductId);
            if ((int) $sourceProduct->user_id !== (int) $item->seller_id
                || !$this->sameProduct($sourceProduct, $target) || (int) $target->user_id === (int) $item->seller_id
                || $target->added_by !== 'seller'
                || (int) $target->status !== 1 || (int) $target->current_stock < (int) $line->qty) {
                throw new DomainException('التاجر البديل لا يملك المنتج نفسه بكمية متاحة.');
            }
            if (!DB::table('shops')->where('seller_id', $target->user_id)->exists()) {
                throw new DomainException('التاجر البديل ليس لديه متجر صالح.');
            }
            $units = OrderWorkItem::query()->where('order_id', $sourceOrder->id)->lockForUpdate()->get();
            if ($units->count() !== $sourceOrder->details()->count() || $units->contains(fn ($unit) => $unit->customer_total === null
                || $unit->vendor_net_amount === null || $unit->shipping_customer_cost === null || $unit->shipping_vendor_cost === null)) {
                throw new DomainException('راجع جميع حصص الطلب أولًا قبل النقل.');
            }
            $totalDisplayed = $units->sum('customer_total');
            $customerShare = $units->count() === 1 ? (float) $sourceOrder->order_amount
                : round((float) $sourceOrder->order_amount * (float) $item->customer_total / max(0.00000001, $totalDisplayed), 8);
            $shippingShare = (float) $item->shipping_customer_cost;
            $fulfillmentTransfer = $this->prepareFulfillmentTransfer($sourceOrder, $line, $item);
            if (($line->delivery_status === 'canceled' || $item->status === 'canceled')
                && !in_array($fulfillmentTransfer['status_before'] ?? null, ['rejected', 'canceled'], true)) {
                throw new DomainException('المنتج الملغي يحتاج مراجعة مالية قبل إعادة التعيين.');
            }
            $vendorShippingShare = (float) ($fulfillmentTransfer['shipping'] ?? $item->shipping_vendor_cost);
            if ($customerShare <= 0 || $shippingShare < 0 || $shippingShare > (float) $sourceOrder->shipping_cost + 0.01
                || $vendorShippingShare < 0 || $customerShare + 0.01 < $shippingShare
                || abs($units->sum('shipping_customer_cost') - (float) $sourceOrder->shipping_cost) > 0.01) {
                throw new DomainException('توزيع مبلغ العميل والشحن على الحصص غير متوازن.');
            }
            $customerRatio = $units->count() === 1 ? 1.0 : $customerShare / (float) $sourceOrder->order_amount;
            $merchandiseTotal = $units->sum(fn ($unit) => (float) $unit->customer_total - (float) $unit->shipping_customer_cost);
            $merchandiseShare = max(0, (float) $item->customer_total - $shippingShare);
            $merchandiseRatio = $units->count() === 1 ? 1.0 : $merchandiseShare / max(0.00000001, $merchandiseTotal);
            $before = $sourceOrder->only(['seller_id','order_amount','init_order_amount','paid_amount',
                'shipping_cost','shipping_customer_cost','shipping_seller_cost','shipping_seller_entitlement',
                'seller_shipping_allocation','platform_shipping_margin','admin_commission','discount_amount',
                'refer_and_earn_discount','extra_discount','total_tax_amount','order_status']);
            $oldPricing = Schema::hasTable('order_vendor_pricings') ? OrderVendorPricing::query()->where('order_id', $sourceOrder->id)->lockForUpdate()->first() : null;
            $oldPricingSnapshot = $oldPricing?->toArray();

            $replacement = $sourceOrder->replicate();
            $replacement->forceFill([
                'seller_id' => $target->user_id, 'seller_is' => 'seller', 'order_amount' => $customerShare,
                'init_order_amount' => $customerShare, 'paid_amount' => round((float) $sourceOrder->paid_amount * $customerRatio, 8),
                'shipping_cost' => $shippingShare, 'shipping_customer_cost' => $shippingShare,
                'shipping_seller_cost' => null, 'shipping_seller_entitlement' => null,
                'seller_shipping_allocation' => 0, 'platform_shipping_margin' => 0,
                'discount_amount' => round((float) $sourceOrder->discount_amount * $merchandiseRatio, 8),
                'refer_and_earn_discount' => round((float) $sourceOrder->refer_and_earn_discount * $merchandiseRatio, 8),
                'extra_discount' => round((float) $sourceOrder->extra_discount * $merchandiseRatio, 8),
                'admin_commission' => round((float) $sourceOrder->admin_commission * $merchandiseRatio, 8),
                'total_tax_amount' => (float) $line->tax,
                'transaction_ref' => 'reassign-'.$sourceOrder->id.'-'.$line->id,
                'verification_code' => random_int(100000, 999999), 'checked' => 0,
                'order_status' => 'pending', 'commerce_flow_version' => config('order_commerce.new_flow_version'),
                'commerce_flow_status' => 'pending_admin_review',
                'commerce_flow_status_updated_at' => now(), 'admin_order_review_status' => 'pending_admin_review',
                'admin_order_reviewed_by' => null, 'admin_order_reviewed_at' => null,
                'admin_order_review_note' => null, 'shipping_assignment_status' => 'pending',
                'shipping_decided_at' => null, 'shipping_decided_by' => null,
                'sales_settlement_due_at' => null, 'shipping_settlement_due_at' => null,
                'seller_shipping_response_status' => null, 'seller_shipping_response_due_at' => null,
                'seller_shipping_response_at' => null, 'seller_shipping_rejection_reason' => null,
                'seller_shipping_support_ticket_id' => null, 'shipment_reference' => null,
                'operational_blocked_by' => 'admin_review', 'operational_block_reason' => 'reassignment_requires_admin_review',
            ])->save();

            $this->moveStock($sourceProduct, $target, $line);
            if (!(int) $line->is_stock_decreased) $line->is_stock_decreased = 1;
            if ($line->delivery_status === 'canceled') $line->delivery_status = 'pending';
            $targetPrice = app(VendorProductWorkspaceService::class)->approvedPrice($target)['unit_price'] ?? $target->unit_price;
            if ($line->variant) {
                $variants = is_string($target->variation) ? json_decode($target->variation, true) : $target->variation;
                foreach (is_array($variants) ? $variants : [] as $variant) {
                    if (($variant['type'] ?? null) === $line->variant && isset($variant['price'])) {
                        $targetPrice = $variant['price'];
                        break;
                    }
                }
            }
            if (!is_numeric($targetPrice) || (float) $targetPrice < 0) throw new DomainException('سعر التاجر البديل غير معتمد.');
            $line->forceFill(['order_id' => $replacement->id, 'seller_id' => $target->user_id,
                'product_id' => $target->id, 'vendor_unit_price_snapshot' => $targetPrice])->save();
            $sourceOrder->forceFill([
                'order_amount' => max(0, (float) $sourceOrder->order_amount - $customerShare),
                'init_order_amount' => max(0, (float) $sourceOrder->init_order_amount - $customerShare),
                'paid_amount' => max(0, (float) $sourceOrder->paid_amount - (float) $replacement->paid_amount),
                'shipping_cost' => max(0, (float) $sourceOrder->shipping_cost - $shippingShare),
                'shipping_customer_cost' => max(0, (float) $sourceOrder->shipping_customer_cost - $shippingShare),
                'shipping_seller_entitlement' => max(0, (float) $sourceOrder->shipping_seller_entitlement - $vendorShippingShare),
                'shipping_seller_cost' => max(0, (float) $sourceOrder->shipping_seller_cost - $vendorShippingShare),
                'seller_shipping_allocation' => max(0, (float) $sourceOrder->seller_shipping_allocation - $vendorShippingShare),
                'discount_amount' => max(0, (float) $sourceOrder->discount_amount - (float) $replacement->discount_amount),
                'refer_and_earn_discount' => max(0, (float) $sourceOrder->refer_and_earn_discount - (float) $replacement->refer_and_earn_discount),
                'extra_discount' => max(0, (float) $sourceOrder->extra_discount - (float) $replacement->extra_discount),
                'admin_commission' => max(0, (float) $sourceOrder->admin_commission - (float) $replacement->admin_commission),
                'platform_shipping_margin' => max(0, (float) $sourceOrder->platform_shipping_margin - max(0, $shippingShare - $vendorShippingShare)),
                'total_tax_amount' => max(0, (float) $sourceOrder->total_tax_amount - (float) $line->tax),
            ]);
            if ($units->count() === 1) $sourceOrder->forceFill(['order_status'=>'canceled',
                'commerce_flow_status'=>'cancelled', 'admin_order_review_status'=>'reassigned']);
            $sourceOrder->save();

            if ($oldPricing) {
                $vendorGoodsShare = (float) ($fulfillmentTransfer['goods']
                    ?? max(0, (float) $item->vendor_net_amount - (float) $item->shipping_vendor_cost));
                $oldPricing->procurement_amount = max(0, (float) $oldPricing->procurement_amount - $vendorGoodsShare);
                $oldPricing->merchandise_sale = max(0, (float) $oldPricing->merchandise_sale - $merchandiseShare);
                $oldPricing->snapshot = app(OrderVendorPricingService::class)->summary($sourceOrder->fresh(), (float) $oldPricing->procurement_amount);
                $oldPricing->save();
            }
            $this->applyFulfillmentTransfer($fulfillmentTransfer, $line, $sourceOrder, $replacement, $adminId, $reason);
            $remainingVendorGoods = $units->filter(fn ($unit) => $unit->id !== $item->id)
                ->sum(fn ($unit) => max(0, (float) $unit->vendor_net_amount - (float) $unit->shipping_vendor_cost));
            if ($oldPricing) $remainingVendorGoods = (float) $oldPricing->procurement_amount;
            $transactionSplit = $this->transferHeldTransaction($sourceOrder, $replacement, $customerShare,
                $customerRatio, $shippingShare, $remainingVendorGoods, $oldPricing !== null);
            $priorStatus = $item->status;
            $item->forceFill(['order_id'=>$replacement->id, 'seller_id'=>$target->user_id, 'product_id'=>$target->id,
                'status'=>'awaiting_admin', 'vendor_unit_price'=>$targetPrice, 'customer_total'=>$customerShare,
                'vendor_net_amount'=>null, 'expected_delivery_at'=>null, 'vendor_payment_due_at'=>null,
                'shipping_vendor_cost'=>null, 'sent_to_vendor_at'=>null, 'vendor_response_due_at'=>null,
                'vendor_responded_at'=>null, 'vendor_response'=>null, 'assigned_by_admin_id'=>$adminId])->save();
            $receiptId = DB::table('order_reassignment_transfers')->insertGetId([
                'work_item_id'=>$item->id, 'order_detail_id'=>$line->id,
                'source_order_id'=>$sourceOrder->id, 'target_order_id'=>$replacement->id,
                'source_seller_id'=>$before['seller_id'], 'target_seller_id'=>$target->user_id,
                'target_product_id'=>$target->id, 'admin_id'=>$adminId,
                'customer_amount_transferred'=>$customerShare, 'shipping_amount_transferred'=>$shippingShare,
                'before_snapshot'=>json_encode(['order'=>$before,'pricing'=>$oldPricingSnapshot,
                    'transaction'=>$transactionSplit['before'] ?? null,
                    'fulfillment'=>$fulfillmentTransfer], JSON_THROW_ON_ERROR),
                'after_snapshot'=>json_encode(['source_order'=>$sourceOrder->only(array_keys($before)),
                    'source_pricing'=>$oldPricing?->toArray(), 'target_order'=>$replacement->only(array_keys($before)),
                    'transactions'=>$transactionSplit['after'] ?? null,
                    'fulfillment'=>$fulfillmentTransfer ? DB::table('vendor_fulfillment_requests')->where('id', $fulfillmentTransfer['request_id'])->first() : null], JSON_THROW_ON_ERROR),
                'reason'=>$reason, 'created_at'=>now(),
            ]);
            app(OrderFinancialAuditService::class)->record($sourceOrder->fresh(), 'product_reassigned',
                'order_reassignment_transfers', $receiptId, ['target_order_id' => $replacement->id,
                    'customer_amount_transferred' => $customerShare], $adminId);
            OrderWorkItemEvent::query()->create(['work_item_id'=>$item->id, 'order_id'=>$replacement->id,
                'order_detail_id'=>$line->id, 'actor_id'=>$adminId, 'actor_type'=>'admin',
                'event_key'=>'reassigned_to_other_vendor', 'status_before'=>$priorStatus,
                'status_after'=>'awaiting_admin', 'snapshot'=>['transfer_id'=>$receiptId,
                    'source_order_id'=>$sourceOrder->id, 'source_seller_id'=>$before['seller_id'],
                    'target_order_id'=>$replacement->id, 'target_seller_id'=>$target->user_id,
                    'customer_amount'=>$customerShare, 'reason'=>$reason], 'created_at'=>now()]);
            \App\Models\Notification::query()->create(['sent_by'=>'admin','sent_to'=>'seller',
                'seller_id'=>$before['seller_id'], 'title'=>'إعادة تعيين منتج من الطلب',
                'description'=>'تم نقل المنتج #'.$line->id.' من الطلب #'.$sourceOrder->id.' إلى تاجر بديل بواسطة الإدارة.',
                'notification_count'=>1, 'status'=>1]);
            return $replacement;
        });
    }

    private function prepareFulfillmentTransfer(Order $order, OrderDetail $line, OrderWorkItem $item): ?array
    {
        if (!Schema::hasTable('vendor_fulfillment_requests') || !Schema::hasTable('vendor_fulfillment_request_items')) return null;
        $mapping = DB::table('vendor_fulfillment_request_items')->where('order_detail_id', $line->id)->lockForUpdate()->first();
        if (!$mapping) {
            if (DB::table('vendor_fulfillment_requests')->where('order_id', $order->id)->exists())
                throw new DomainException('سجل تنفيذ المنتج غير مكتمل؛ راجعه قبل إعادة التعيين.');
            return null;
        }
        $request = DB::table('vendor_fulfillment_requests')->where('id', $mapping->request_id)->lockForUpdate()->first();
        if (!$request || (int) $request->order_id !== (int) $order->id
            || (int) $request->seller_id !== (int) $order->seller_id
            || !in_array($request->status, ['available', 'locked', 'pending_vendor', 'rejected', 'canceled'], true)) {
            throw new DomainException('بدأ قرار تنفيذ هذا الجزء؛ راجع حالة التاجر قبل النقل.');
        }
        if (in_array($request->status, ['rejected', 'canceled'], true)
            && !DB::table('vendor_fulfillment_financial_reviews')->where('request_id', $request->id)
                ->where('status', 'pending')->exists()) {
            throw new DomainException('مراجعة إلغاء هذا الجزء انتهت؛ لا يمكن إعادة تعيينه.');
        }
        $members = DB::table('vendor_fulfillment_request_items as ri')
            ->join('order_details as d', 'd.id', '=', 'ri.order_detail_id')
            ->leftJoin('order_work_items as wi', 'wi.order_detail_id', '=', 'd.id')
            ->where('ri.request_id', $request->id)->get(['d.id', 'd.qty', 'd.vendor_unit_price_snapshot',
                'wi.shipping_vendor_cost']);
        $weights = $members->mapWithKeys(fn ($member) => [$member->id => max(0,
            (float) $member->vendor_unit_price_snapshot * (int) $member->qty)]);
        $totalWeight = $weights->sum();
        $ratio = $members->count() === 1 ? 1.0 : ($totalWeight > 0
            ? (float) ($weights[$line->id] ?? 0) / $totalWeight : 1 / $members->count());
        $shippingWeights = $members->sum(fn ($member) => max(0, (float) $member->shipping_vendor_cost));
        $shippingRatio = $members->count() === 1 ? 1.0 : ($shippingWeights > 0
            ? max(0, (float) $item->shipping_vendor_cost) / $shippingWeights : $ratio);
        return ['request_id' => (int) $request->id, 'status_before' => $request->status,
            'goods' => round((float) $request->vendor_amount * $ratio, 8),
            'shipping' => round((float) $request->shipping_amount * $shippingRatio, 8),
            'vendor_amount_before' => (float) $request->vendor_amount,
            'shipping_amount_before' => (float) $request->shipping_amount];
    }

    private function applyFulfillmentTransfer(?array $transfer, OrderDetail $line, Order $order, Order $replacement,
        int $adminId, string $reason): void
    {
        if (!$transfer) return;
        DB::table('vendor_fulfillment_request_items')->where('request_id', $transfer['request_id'])
            ->where('order_detail_id', $line->id)->delete();
        $remaining = DB::table('vendor_fulfillment_request_items')->where('request_id', $transfer['request_id'])->exists();
        DB::table('vendor_fulfillment_requests')->where('id', $transfer['request_id'])->update([
            'vendor_amount' => $remaining ? max(0, $transfer['vendor_amount_before'] - $transfer['goods']) : 0,
            'shipping_amount' => $remaining ? max(0, $transfer['shipping_amount_before'] - $transfer['shipping']) : 0,
            'status' => $remaining ? $transfer['status_before'] : 'reassigned',
            'decision_reason' => $remaining ? null : $reason,
            'updated_at' => now(),
        ]);
        if (in_array($transfer['status_before'], ['rejected', 'canceled'], true)) {
            $review = DB::table('vendor_fulfillment_financial_reviews')->where('request_id', $transfer['request_id'])
                ->lockForUpdate()->first();
            if ($review) {
                $remainingIds = DB::table('vendor_fulfillment_request_items')
                    ->where('request_id', $transfer['request_id'])->pluck('order_detail_id')->all();
                DB::table('vendor_fulfillment_financial_reviews')->where('id', $review->id)->update([
                    'line_ids' => json_encode($remainingIds, JSON_THROW_ON_ERROR),
                    'status' => $remaining ? 'pending' : 'reviewed',
                    'admin_note' => $remaining ? $review->admin_note : 'أعيد تعيين المنتج إلى الطلب #'.$replacement->id.'؛ لا يُصدر استرداد لهذا الجزء.',
                    'reviewed_by' => $remaining ? $review->reviewed_by : $adminId,
                    'reviewed_at' => $remaining ? $review->reviewed_at : now(), 'updated_at' => now(),
                ]);
                if (!$remaining && Schema::hasTable('order_operational_alerts')) {
                    DB::table('order_operational_alerts')->where('idempotency_key', 'vendor-portion-review-'.$transfer['request_id'])
                        ->update(['resolved_at' => now()]);
                }
            }
        }
        DB::table('vendor_fulfillment_events')->insert(['request_id' => $transfer['request_id'],
            'event_type' => 'reassign_'.$line->id, 'metadata' => json_encode(['order_detail_id' => $line->id,
                'source_order_id' => $order->id, 'admin_id' => $adminId, 'goods' => $transfer['goods'],
                'shipping' => $transfer['shipping']], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function sameProduct(Product $source, Product $target): bool
    {
        return trim(mb_strtolower((string) $source->getRawOriginal('name'))) === trim(mb_strtolower((string) $target->getRawOriginal('name')))
            && (int) $source->category_id === (int) $target->category_id
            && (string) $source->product_type === (string) $target->product_type
            && (!$source->brand_id || (int) $source->brand_id === (int) $target->brand_id);
    }

    private function moveStock(Product $source, Product $target, OrderDetail $line): void
    {
        $qty = (int) $line->qty;
        if ((int) $target->current_stock < $qty) throw new DomainException('الكمية غير متاحة لدى التاجر البديل.');
        if ((int) $line->is_stock_decreased) $source->current_stock = (int) $source->current_stock + $qty;
        $target->current_stock = (int) $target->current_stock - $qty;
        foreach ([$source, $target] as $product) {
            if ($product->id === $source->id && !(int) $line->is_stock_decreased) continue;
            if ($line->variant) {
                $variants = is_string($product->variation) ? json_decode($product->variation, true) : $product->variation;
                $found = false;
                foreach ($variants ?: [] as &$variant) {
                    if (($variant['type'] ?? null) !== $line->variant) continue;
                    $found = true;
                    if ($product->id === $target->id && (int) ($variant['qty'] ?? 0) < $qty) throw new DomainException('تنويعة المنتج غير متاحة لدى التاجر البديل.');
                    $variant['qty'] = (int) ($variant['qty'] ?? 0) + ($product->id === $source->id ? $qty : -$qty);
                }
                unset($variant);
                if (!$found) throw new DomainException('التنويعة المطلوبة غير موجودة لدى التاجر البديل.');
                $product->variation = json_encode($variants, JSON_UNESCAPED_UNICODE);
            }
            $product->save();
        }
    }

    private function transferHeldTransaction(Order $source, Order $target, float $amount, float $customerRatio,
        float $shippingShare, float $remainingVendorGoods, bool $hasApprovedPricing): array
    {
        $transactions = OrderTransaction::query()->where('order_id', $source->id)->lockForUpdate()->get();
        if ($transactions->isEmpty()) return [];
        if ($transactions->count() !== 1) throw new DomainException('للطلب أكثر من معاملة مالية؛ يلزم تسويتها قبل نقل المنتج.');
        $transaction = $transactions->first();
        if ($transaction->status !== 'hold') throw new DomainException('المعاملة المالية خرجت من مرحلة الحجز؛ لا يمكن إعادة التعيين.');
        if ($amount > (float) $transaction->order_amount + 0.01) throw new DomainException('رصيد المعاملة المالية لا يغطي الحصة المنقولة.');
        $before = $transaction->only(['id','order_id','order_amount','seller_amount','admin_commission','delivery_charge','tax','status']);
        $new = $transaction->replicate();
        $new->order_id = $target->id;
        $new->seller_id = $target->seller_id;
        $new->shop_id = DB::table('shops')->where('seller_id', $target->seller_id)->value('id');
        $new->transaction_id = OrderManager::generateUniqueOrderID();
        $new->order_amount = $amount;
        // The new seller acquires no entitlement until the admin approves its own price.
        $new->seller_amount = 0;
        $new->admin_commission = 0;
        $new->delivery_charge = 0;
        $new->tax = round((float) $transaction->tax * $customerRatio, 8);
        $new->save();
        $transaction->order_amount = max(0, (float) $transaction->order_amount - $amount);
        $transaction->seller_amount = $hasApprovedPricing ? $remainingVendorGoods
            : round((float) $transaction->seller_amount * (1 - $customerRatio), 8);
        $transaction->admin_commission = $hasApprovedPricing ? max(0, (float) $source->order_amount
            - (float) $source->shipping_cost - $remainingVendorGoods)
            : round((float) $transaction->admin_commission * (1 - $customerRatio), 8);
        $transaction->delivery_charge = max(0, (float) $transaction->delivery_charge - $shippingShare);
        $transaction->tax = max(0, (float) $transaction->tax - (float) $new->tax);
        $transaction->save();
        return ['before'=>$before, 'after'=>['source'=>$transaction->only(array_keys($before)),
            'target'=>$new->only(array_keys($before))]];
    }
}
