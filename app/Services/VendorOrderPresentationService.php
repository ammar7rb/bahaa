<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderVendorPricing;
use App\Models\VendorProductWorkspace;

/** Allowlisted fulfillment DTO, shared by vendor web, API and exports. */
class VendorOrderPresentationService
{
    public function order(Order $order, bool $withLines = true): array
    {
        $eligibility = app(VendorFulfillmentEligibilityService::class)->summary($order);
        if ($eligibility && $eligibility['details_locked']) {
            return [
                'id' => (int) $order->id, 'order_status' => $order->order_status,
                'details_locked' => true, 'partially_locked' => false,
                'order_amount' => $eligibility['restricted_amount'], 'amount_basis' => 'vendor_total',
                'restricted_amount' => $eligibility['restricted_amount'],
                'fulfillment_eligibility' => $eligibility, 'order_details' => [],
                'restriction_reason' => 'advertising_slot_required',
                'restriction_message' => VendorFulfillmentEligibilityService::MESSAGE,
                'forwarded_at' => $eligibility['forwarded_at'],
            ];
        }
        $approval = OrderVendorPricing::where('order_id', $order->id)->first();
        $amount = $approval ? (float) $approval->procurement_amount : 0.0;
        $shipping = $order->shipping_fulfillment_mode === 'seller_shipping' ? (float) $order->shipping_seller_entitlement : 0.0;
        $next = ['confirmed' => 'processing', 'processing' => 'out_for_delivery', 'out_for_delivery' => 'delivered'][$order->order_status] ?? null;
        if ($order->shipping_fulfillment_mode === 'admin_shipping' && $next !== 'processing') $next = null;
        if (in_array($order->seller_shipping_response_status, ['pending', 'overdue', 'rejected'], true)) $next = null;
        $address = (array) ($order->customer_address_snapshot ?: $order->shipping_address_data ?: []);
        $address = array_intersect_key($address, array_flip(['contact_person_name', 'phone', 'address', 'city', 'country', 'zip', 'latitude', 'longitude']));
        $decision = $order->shippingDecisions()->latest('id')->first();
        $dto = ['id' => (int) $order->id, 'seller_id' => (int) $order->seller_id, 'seller_is' => 'seller',
            'order_status' => $order->order_status, 'payment_status' => $order->payment_status,
            'order_type' => $order->order_type ?: 'default_type', 'is_guest' => (bool) $order->is_guest,
            'created_at' => $order->created_at?->toIso8601String(), 'updated_at' => $order->updated_at?->toIso8601String(),
            'amount_basis' => 'vendor_procurement', 'procurement_approved' => (bool) $approval,
            'vendor_procurement_amount' => $approval ? $amount : null,
            // Compatibility aliases are vendor amounts, never customer prices.
            'order_amount' => $amount + $shipping, 'init_order_amount' => $amount + $shipping,
            'shipping_cost' => $shipping, 'shipping_seller_entitlement' => $shipping,
            'total_product_price' => $amount, 'total_product_discount' => 0, 'total_tax_amount' => 0,
            'discount_amount' => 0, 'extra_discount' => 0, 'refer_and_earn_discount' => 0,
            'edited_status' => 0, 'edit_due_amount' => 0, 'edit_return_amount' => 0, 'is_shipping_free' => $shipping == 0,
            'shipping_address_data' => $address, 'billing_address_data' => null,
            'verification_code' => $order->verification_code,
            'shipping_responsibility' => $order->shipping_responsibility,
            'shipping_fulfillment_mode' => $order->shipping_fulfillment_mode,
            'shipping_assignment' => ['status' => $order->shipping_assignment_status,
                'responsible_party' => $order->shipping_fulfillment_mode === 'seller_shipping' ? 'seller' : 'company',
                'seller_entitlement' => $shipping, 'mode' => $order->shipping_fulfillment_mode,
                'service_name' => $decision?->service_name, 'tracking_number' => $decision?->tracking_number,
                'expected_delivery_date' => $decision?->expected_delivery_date?->format('Y-m-d'),
                'instructions' => $decision?->instructions, 'sales_due_at' => $order->sales_settlement_due_at?->toIso8601String(),
                'shipping_due_at' => $order->shipping_settlement_due_at?->toIso8601String(),
                'allowed_seller_statuses' => $next ? [$next] : [],
                'seller_shipping_response' => ['status' => $order->seller_shipping_response_status,
                    'required' => in_array($order->seller_shipping_response_status, ['pending', 'overdue'], true),
                    'due_at' => $order->seller_shipping_response_due_at?->toIso8601String()]],
            'fulfillment_cta' => 'حدّث حالة الطلب لمتابعة استحقاق مستحقاتك'];
        if ($withLines) $dto['order_details'] = $order->details
            ->filter(fn ($line) => app(VendorFulfillmentEligibilityService::class)->lineAllowed($line))
            ->map(fn ($line) => $this->line($line, false))->values()->all();
        if ($eligibility) {
            $dto['fulfillment_eligibility'] = $eligibility;
            $dto['details_locked'] = false;
            $dto['partially_locked'] = $eligibility['partially_locked'];
            $dto['forwarded_at'] = $eligibility['forwarded_at'];
            if ($eligibility['has_restrictions']) {
                $dto['restricted_amount'] = $eligibility['restricted_amount'];
                $dto['amount_basis'] = 'accessible_portion';
                $dto['vendor_procurement_amount'] = $eligibility['accessible_procurement_amount'];
                $dto['total_product_price'] = $eligibility['accessible_procurement_amount'];
                $dto['order_amount'] = $dto['init_order_amount'] = $eligibility['accessible_amount'];
                $dto['shipping_cost'] = $dto['shipping_seller_entitlement'] = $eligibility['accessible_shipping_amount'];
                $dto['verification_code'] = null;
                // Shared free-text instructions, files and tracking can describe locked items.
                foreach (['service_name', 'tracking_number', 'instructions'] as $key) $dto['shipping_assignment'][$key] = null;
                $dto['shipping_assignment']['seller_entitlement'] = $eligibility['accessible_shipping_amount'];
                $dto['shipping_assignment']['allowed_seller_statuses'] = [];
                $dto['shipping_assignment']['seller_shipping_response']['required'] = false;
            }
            if ($eligibility['portion_actions_enabled'] ?? false) {
                $dto['vendor_procurement_amount'] = $dto['total_product_price'] = $eligibility['accessible_procurement_amount'];
                $dto['order_amount'] = $dto['init_order_amount'] = $eligibility['accessible_amount'];
                $dto['shipping_cost'] = $dto['shipping_seller_entitlement'] = $eligibility['accessible_shipping_amount'];
                $dto['shipping_assignment']['seller_entitlement'] = $eligibility['accessible_shipping_amount'];
                $dto['shipping_assignment']['allowed_seller_statuses'] = [];
                $dto['shipping_assignment']['seller_shipping_response']['required'] = false;
                $dto['fulfillment_cta'] = 'راجع كل جزء ثم اقبله أو ارفضه؛ الباقة وإتاحة التفاصيل لا تعدان قبولًا.';
            }
        }
        return $dto;
    }

    public function line(OrderDetail $line, bool $withOrder = true): array
    {
        abort_unless(app(VendorFulfillmentEligibilityService::class)->lineAllowed($line), 423, VendorFulfillmentEligibilityService::MESSAGE);
        $state = VendorProductWorkspace::where('product_id', $line->product_id)->where('seller_id', $line->seller_id)->first();
        $revision = $state ? \Illuminate\Support\Facades\DB::table('vendor_product_submission_revisions')
            ->where('workspace_id', $state->id)->where('revision', $line->getRawOriginal('vendor_content_revision'))->first() : null;
        $original = $revision ? json_decode($revision->seller_content, true) : ($state?->seller_content ?? []);
        $original = array_intersect_key($original, array_flip(['name', 'description', 'images', 'thumbnail', 'thumbnail_full_url', 'images_full_url', 'product_type', 'digital_product_type', 'digital_file_ready', 'unit', 'colors', 'choice_options', 'variation', 'unit_price', 'current_stock']));
        $unit = $line->getRawOriginal('vendor_unit_price_snapshot');
        $original['id'] = (int) ($state?->private_reference ?? 0);
        $original['name'] ??= 'منتج المورد';
        $original['unit_price'] = $unit === null ? 0.0 : (float) $unit;
        $original['purchase_price'] = $original['unit_price'];
        $original['discount'] = 0.0;
        $original['current_stock'] ??= 0;
        foreach (['images', 'variation', 'choice_options'] as $key) {
            $value = $original[$key] ?? [];
            if (is_string($value)) $value = json_decode($value, true);
            $original[$key] = is_array($value) ? $value : [];
        }
        $original['images'] = array_values(array_filter($original['images'], 'is_string'));
        $dto = ['id' => (int) $line->id, 'order_id' => (int) $line->order_id,
            'seller_id' => (int) $line->seller_id, 'product_id' => $original['id'], 'qty' => (int) $line->qty,
            'price' => $unit === null ? 0.0 : (float) $unit, 'price_available' => $unit !== null,
            'tax' => 0, 'discount' => 0, 'product_details' => $original,
            'tax_model' => 'exclude', 'delivery_status' => $line->delivery_status,
            'payment_status' => $line->payment_status, 'variant' => $line->variant,
            'digital_file_after_sell' => $line->digital_file_after_sell,
            'digital_file_after_sell_full_url' => $line->digital_file_after_sell_full_url,
            'modified_variation' => [], 'verification_images' => [],
            'created_at' => $line->created_at?->toIso8601String(), 'updated_at' => $line->updated_at?->toIso8601String()];
        if ($withOrder) $dto['order'] = $this->order($line->order, false);
        return $dto;
    }

    public function sanitize(mixed $payload, int $sellerId): mixed
    {
        if (!is_array($payload)) return $payload;
        if (isset($payload['id'], $payload['order_status'])) {
            $order = Order::whereKey($payload['id'])->where('seller_id', $sellerId)->where('seller_is', 'seller')->first();
            return $order && app(SellerOrderVisibilityService::class)->canSellerView($order) ? $this->order($order) : null;
        }
        if (isset($payload['id'], $payload['order_id'], $payload['product_details'])) {
            $line = OrderDetail::whereKey($payload['id'])->where('seller_id', $sellerId)->first();
            return $line && app(SellerOrderVisibilityService::class)->canSellerView($line->order)
                && app(VendorFulfillmentEligibilityService::class)->lineAllowed($line) ? $this->line($line) : null;
        }
        foreach ($payload as $key => $value) $payload[$key] = $this->sanitize($value, $sellerId);
        return array_is_list($payload) ? array_values(array_filter($payload, fn ($value) => $value !== null)) : $payload;
    }
}
