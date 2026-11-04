<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\BaseController;
use App\Models\AccountActivationCase;
use App\Models\Contact;
use App\Models\Order;
use App\Models\OrderInsurance;
use App\Models\PostPurchaseInvoice;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Seller;
use App\Models\SellerOrderInsurance;
use App\Models\CustomerBalanceDeposit;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A small, read-only operational stream for administrators.  It reads the
 * existing commerce records instead of creating a second notification ledger.
 * This keeps the feed deployable without a queue worker or websocket service.
 */
class OperationalFeedController extends BaseController
{
    public function index(?Request $request = null, ?string $type = null): JsonResponse
    {
        $request ??= request();
        $after = $this->after($request->query('after'));
        $events = collect();

        if (Schema::hasTable('users')) {
            User::query()->where('created_at', '>', $after)->latest('created_at')->limit(25)->get()->each(function (User $user) use ($events) {
                $events->push($this->event('customer_registration', $user->created_at, translate('new_customer_registration'), 'C'.$user->id.' · '.trim($user->f_name.' '.$user->l_name), route('admin.customer.list')));
            });
        }
        if (Schema::hasTable('sellers')) {
            Seller::query()->where('created_at', '>', $after)->latest('created_at')->limit(25)->get()->each(function (Seller $seller) use ($events) {
                $events->push($this->event('seller_registration', $seller->created_at, translate('new_seller_registration'), 'M'.$seller->id.' · '.trim($seller->f_name.' '.$seller->l_name), route('admin.vendors.vendor-list')));
            });
        }
        if (Schema::hasTable('orders')) {
            Order::query()->with('customer:id,f_name,l_name')->where('created_at', '>', $after)->latest('created_at')->limit(50)->get()->each(function (Order $order) use ($events) {
                if ($order->payment_method === 'offline_payment' && $order->payment_status !== 'paid') {

                    return;
                }
                $events->push($this->event('new_order', $order->created_at, translate('new_order_received'), '#'.$order->id.' · '.setCurrencySymbol(amount: usdToDefaultCurrency(amount: $order->order_amount), currencyCode: getCurrencyCode()), route('admin.orders.details', $order->id)));
            });
        }
        if (Schema::hasTable('support_tickets')) {
            SupportTicket::query()->where('created_at', '>', $after)->latest('created_at')->limit(50)->get()->each(function (SupportTicket $ticket) use ($events) {
                $events->push($this->event('support_ticket', $ticket->created_at, translate('new_support_request'), '#'.$ticket->id.' · '.($ticket->subject ?: translate('support_ticket')), route('admin.support-ticket.singleTicket', $ticket->id)));
            });
        }
        if (Schema::hasTable('contacts')) {
            Contact::query()->where('created_at', '>', $after)->latest('created_at')->limit(25)->get()->each(function (Contact $contact) use ($events) {
                $events->push($this->event(
                    'contact_message',
                    $contact->created_at,
                    translate('new_message_received'),
                    ($contact->name ?: $contact->email).' · '.($contact->subject ?: translate('message')),
                    route('admin.contact.view', $contact->id)
                ));
            });
        }

        if (Schema::hasTable('refund_requests')) {
            RefundRequest::query()->where('created_at', '>', $after)->latest('created_at')->limit(50)->get()->each(function (RefundRequest $refund) use ($events) {
                $events->push($this->event(
                    'refund_request',
                    $refund->created_at,
                    translate('new_refund_request'),
                    '#'.$refund->id.' · '.translate('order').' #'.$refund->order_id,
                    route('admin.refund-section.refund.details', $refund->id)
                ));
            });
        }

        // Payment proofs are updates to an existing order, so they must be
        // emitted explicitly instead of relying on the new-order timestamp.
        if (\App\Utils\Helpers::module_permission_check('people') || \App\Utils\Helpers::module_permission_check('orders')) {
            $query = app(\App\Services\AdminWorkspaceService::class)->pendingPaymentRows();
            if ($query) $query->where('updated_at', '>', $after)->orderByDesc('updated_at')->limit(50)->get()->each(function ($row) use ($events) {
                $item = app(\App\Services\AdminWorkspaceService::class)->paymentItem($row);
                $events->push($this->event('payment_review_'.$row->source.'_'.$row->id, $row->updated_at, $item['title'], $item['detail'].' · '.$item['meta'], $item['url']));
            });
        }

        if (Schema::hasTable('password_resets') && Schema::hasColumn('password_resets', 'created_at')) {
            DB::table('password_resets')->where('created_at', '>', $after)->orderByDesc('created_at')->limit(50)->get()->each(function (object $reset) use ($events) {
                $userType = ($reset->user_type ?? 'customer') === 'seller' ? 'seller' : 'customer';
                $events->push($this->event(
                    'password_reset_request',
                    $reset->created_at,
                    translate('new_password_reset_request'),
                    translate($userType).' · '.$this->maskIdentity((string) ($reset->identity ?? '')),
                    $userType === 'seller' ? route('admin.vendors.vendor-list') : route('admin.customer.list')
                ));
            });
        }

        $summary = $this->summary();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'events' => $events->sortByDesc('timestamp')->take(50)->values(),
            'summary' => $summary,
            'action_items' => $this->actionItems($summary),
            'messages' => $this->recentMessages(),
            'dashboard_metrics' => $request->boolean('dashboard') ? $this->dashboardMetrics() : [],
        ]);
    }

    private function after(mixed $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : now()->subDay();
        } catch (\Throwable) {
            return now()->subDay();
        }
    }

    private function event(string $type, mixed $createdAt, string $title, string $body, string $url): array
    {
        $time = $createdAt ? Carbon::parse($createdAt) : now();

        return [
            'id' => $type.'-'.$time->getTimestamp().'-'.md5($body),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'timestamp' => $time->toIso8601String(),
            'display_time' => $time->diffForHumans(),
        ];
    }

    private function summary(): array
    {
        return [
            'new_orders' => Schema::hasTable('orders') ? Order::query()->where('checked', 0)->count() : 0,
            'pending_orders' => Schema::hasTable('orders') ? Order::query()->where('order_status', 'pending')->count() : 0,
            'pending_products' => Schema::hasTable('products') ? Product::query()->where('added_by', 'seller')->where('request_status', 0)->count() : 0,
            'pending_refunds' => Schema::hasTable('refund_requests') ? RefundRequest::query()->where('status', 'pending')->count() : 0,
            'customer_registrations_today' => Schema::hasTable('users') ? User::query()->whereDate('created_at', today())->count() : 0,
            'seller_registrations_today' => Schema::hasTable('sellers') ? Seller::query()->whereDate('created_at', today())->count() : 0,
            'password_resets_today' => Schema::hasTable('password_resets') && Schema::hasColumn('password_resets', 'created_at') ? DB::table('password_resets')->whereDate('created_at', today())->count() : 0,
            'unread_messages' => Schema::hasTable('contacts') ? Contact::query()->where('seen', 0)->count() : 0,
            'pending_payment_reviews' => $this->pendingPaymentReviews(),
        ];
    }

    private function recentMessages(): array
    {
        if (!Schema::hasTable('contacts')) {
            return [];
        }

        return Contact::query()->where('seen', 0)->latest('created_at')->take(6)->get()
            ->map(fn (Contact $contact) => [
                'id' => $contact->id,
                'subject' => $contact->subject ?: translate('new_message'),
                'sender' => $contact->name ?: $contact->email,
                'preview' => \Illuminate\Support\Str::limit(strip_tags((string) $contact->message), 85),
                'initial' => mb_strtoupper(mb_substr(trim($contact->name ?: $contact->email), 0, 1)),
                'time' => $contact->created_at?->locale(app()->getLocale())->diffForHumans(),
                'url' => route('admin.contact.view', $contact->id),
            ])->values()->all();
    }

    private function dashboardMetrics(): array
    {
        [$from, $to] = $this->dashboardPeriod();
        $orderQuery = Order::query()->when($from && $to, fn ($query) => $query->whereBetween('created_at', [$from, $to]));
        $statusCounts = (clone $orderQuery)->selectRaw('order_status, COUNT(*) as total')->groupBy('order_status')->pluck('total', 'order_status');

        return [
            'order' => (clone $orderQuery)->count(),
            'store' => Schema::hasTable('sellers') ? Seller::query()->when($from && $to, fn ($query) => $query->whereBetween('created_at', [$from, $to]))->count() : 0,
            'product' => Schema::hasTable('products') ? Product::query()->when($from && $to, fn ($query) => $query->whereBetween('created_at', [$from, $to]))->count() : 0,
            'customer' => Schema::hasTable('users') ? User::query()->when($from && $to, fn ($query) => $query->whereBetween('created_at', [$from, $to]))->count() : 0,
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'confirmed' => (int) ($statusCounts['confirmed'] ?? 0),
            'processing' => (int) ($statusCounts['processing'] ?? 0),
            'out_for_delivery' => (int) ($statusCounts['out_for_delivery'] ?? 0),
            'delivered' => (int) ($statusCounts['delivered'] ?? 0),
            'canceled' => (int) ($statusCounts['canceled'] ?? 0),
            'returned' => (int) ($statusCounts['returned'] ?? 0),
            'failed' => (int) ($statusCounts['failed'] ?? 0),
            'active_customer_count' => Schema::hasTable('users') ? User::query()->where('is_active', 1)->count() : 0,
            'active_product_count' => Schema::hasTable('products') ? Product::query()->where('status', 1)->where('request_status', 1)->count() : 0,
            'pending_product_count' => Schema::hasTable('products') ? Product::query()->where('added_by', 'seller')->where('request_status', 0)->count() : 0,
            'total_customer_count' => Schema::hasTable('users') ? User::query()->count() : 0,
            'total_vendor_count' => Schema::hasTable('sellers') ? Seller::query()->count() : 0,
        ];
    }

    private function actionItems(array $summary): array
    {
        $definitions = [
            ['key' => 'pending_payment_reviews', 'title' => translate('payments_waiting_for_review'), 'url' => route('admin.payment-reviews.index', ['status' => 'pending']), 'icon' => 'fi fi-sr-document-signed', 'group' => 'action'],
            ['key' => 'pending_refunds', 'title' => translate('pending_refund_Requests'), 'url' => route('admin.refund-section.refund.list', ['status' => 'pending']), 'icon' => 'fi fi-sr-refund-alt', 'group' => 'action'],
            ['key' => 'customer_registrations_today', 'title' => translate('new_customers_today'), 'url' => route('admin.customer.list'), 'icon' => 'fi fi-sr-user-add', 'group' => 'today'],
            ['key' => 'seller_registrations_today', 'title' => translate('new_sellers_today'), 'url' => route('admin.vendors.vendor-list'), 'icon' => 'fi fi-sr-shop', 'group' => 'today'],
            ['key' => 'password_resets_today', 'title' => translate('password_reset_requests_today'), 'url' => route('admin.customer.list'), 'icon' => 'fi fi-sr-key', 'group' => 'today'],
        ];

        $items = collect($definitions)
            ->filter(fn (array $item) => (int) ($summary[$item['key']] ?? 0) > 0)
            ->map(fn (array $item) => $item + [
                'id' => 'summary-'.$item['key'],
                'count' => (int) $summary[$item['key']],
                'body' => translate('click_to_view_and_manage'),
            ])
            ->values();

        if ((int) ($summary['pending_products'] ?? 0) > 0) {
            Product::query()->where('added_by', 'seller')->where('request_status', 0)
                ->with('seller.shop')->latest('seller_submitted_at')->take(5)->get()
                ->reverse()
                ->each(function (Product $product) use ($items) {
                    $changedFields = $product->seller_review_changes ?? [];
                    $items->prepend([
                        'id' => 'pending-product-'.$product->id,
                        'key' => 'pending_products',
                        'kind' => 'product_review',
                        'title' => $changedFields ? translate('product_update_request') : translate('product_pending_review'),
                        'name' => $product->name,
                        'reference_label' => translate('product_id'),
                        'reference' => '#'.$product->id,
                        'amount_label' => translate('product_price'),
                        'amount' => setCurrencySymbol(amount: usdToDefaultCurrency(amount: $product->unit_price), currencyCode: getCurrencyCode()),
                        'meta' => translate('vendor').': '.($product->seller?->shop?->name ?? 'M'.$product->user_id),
                        'image' => getStorageImages(path: $product->thumbnail_full_url, type: 'backend-product'),
                        'url' => route('admin.products.view', ['addedBy' => 'vendor', 'id' => $product->id]),
                        'count' => 1,
                        'group' => 'action',
                    ]);
                });
        }

        return $items->values()->all();
    }

    private function paymentReviewEvent(string $kind, mixed $createdAt, ?int $orderId, ?string $actor, float $amount, string $url): array
    {
        $subject = str_starts_with($kind, 'seller_') ? translate('seller') : translate('customer');
        $purpose = match ($kind) {
            'customer_purchase' => translate('purchase_payment'),
            'customer_post_purchase' => translate('post_purchase_invoice_payment'),
            'customer_purchase_deposit' => translate('purchase_balance_deposit'),
            default => translate('insurance_payment'),
        };
        $parts = [$subject.': '.($actor ?: '-')];
        if ($orderId) $parts[] = translate('order').' #'.$orderId;
        $parts[] = $purpose;
        $parts[] = setCurrencySymbol(amount: usdToDefaultCurrency(amount: $amount), currencyCode: getCurrencyCode());

        return $this->event('payment_review_'.$kind, $createdAt, translate('payment_requires_admin_review'), implode(' · ', $parts), $url);
    }

    private function pendingPaymentReviews(): int
    {
        return app(\App\Services\AdminWorkspaceService::class)->pendingPaymentRows()?->count() ?? 0;
    }

    private function dashboardPeriod(): array
    {
        return match (session('statistics_type')) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            default => [null, null],
        };
    }

    private function maskIdentity(string $identity): string
    {
        if (str_contains($identity, '@')) {
            [$name, $domain] = array_pad(explode('@', $identity, 2), 2, '');
            return mb_substr($name, 0, 2).'***@'.$domain;
        }

        $visible = mb_substr($identity, -4);
        return str_repeat('*', max(0, mb_strlen($identity) - 4)).$visible;
    }
}
