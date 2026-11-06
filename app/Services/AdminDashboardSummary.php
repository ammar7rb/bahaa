<?php

namespace App\Services;

use App\Models\{Seller, Product, Order};
use App\Utils\Helpers;
use Illuminate\Support\Facades\DB;

class AdminDashboardSummary
{
    public function subscriptions(string $audience)
    {
        $vendor = $audience === 'vendor';
        $q = DB::table($vendor ? 'seller_package_subscriptions' : 'customer_monthly_subscriptions');
        if ($vendor) $q->where('status', 'active')->where('payment_status', 'paid');
        return $q->where('starts_at', '<=', now())->where(fn ($q) => $vendor ? $q->whereNull('expires_at')->orWhere('expires_at', '>', now()) : $q->where('expires_at', '>', now()));
    }

    public function cards(): array
    {
        $people = Helpers::module_permission_check('people');
        $orders = Helpers::module_permission_check('orders');
        $pending = ($people || $orders) ? app(\App\Http\Controllers\Admin\PaymentReviewController::class)->pendingSummary() : collect();
        $sales = Helpers::module_permission_check('reports') ? app(\App\Services\Admin\CompanyFinancialsService::class)->totals('sales', [])['merchandise_sale'] : null;
        return [
            ['vendors', 'summary_vendors', 'shop', $people ? Seller::count() : null, route('admin.vendors.vendor-list'), 'summary_all_records'],
            ['products', 'summary_products', 'box', Helpers::module_permission_check('catalog') ? Product::count() : null, route('admin.products.product-gallery', ['summary_scope'=>'all']), 'summary_all_records'],
            ['sales', 'summary_sales', 'chart-histogram', $sales?->map(fn ($r) => number_format($r->amount, 2).' '.$r->currency)->implode(' · ') ?: ($sales === null ? null : '0'), route('admin.report.financials.company', ['tab'=>'sales', 'metric'=>'merchandise_sale']), 'summary_sales_scope'],
            ['orders', 'summary_orders', 'shopping-cart', $orders ? Order::count() : null, route('admin.orders.list', ['status'=>'all']), 'summary_all_records'],
            ['customer_subscriptions', 'summary_customer_subscriptions', 'users', $people ? $this->subscriptions('customer')->count() : null, route('admin.workspace.subscriptions', ['audience'=>'customer']), 'summary_active_subscriptions'],
            ['vendor_subscriptions', 'summary_vendor_subscriptions', 'shop', $people ? $this->subscriptions('vendor')->count() : null, route('admin.workspace.subscriptions', ['audience'=>'vendor']), 'summary_active_subscriptions'],
            ['vendor_payments', 'summary_vendor_payments', 'credit-card', $people ? $pending->where('audience', 'vendor')->count() : null, route('admin.payment-reviews.index', ['audience'=>'vendor', 'status'=>'pending']), 'summary_pending_review'],
            ['customer_payments', 'summary_customer_payments', 'credit-card', ($people || $orders) ? $pending->where('audience', 'customer')->count() : null, route('admin.payment-reviews.index', ['audience'=>'customer', 'status'=>'pending']), 'summary_pending_review'],
        ];
    }
}
