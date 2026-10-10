<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\AdminWalletRepositoryInterface;
use App\Contracts\Repositories\BrandRepositoryInterface;
use App\Contracts\Repositories\ChattingRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\DeliveryManRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\OrderTransactionRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\RestockProductRepositoryInterface;
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Contracts\Repositories\VendorWalletRepositoryInterface;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerPackageSubscription;
use App\Models\SellerProductPromotion;
use App\Models\PlatformDailyMetric;
use App\Models\SellerActivationTicket;
use App\Models\AccountActivationCase;
use App\Models\RefundRequest;
use App\Models\Seller;
use App\Models\User;
use App\Http\Controllers\BaseController;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    public function __construct(
        private readonly AdminWalletRepositoryInterface      $adminWalletRepo,
        private readonly CustomerRepositoryInterface         $customerRepo,
        private readonly OrderTransactionRepositoryInterface $orderTransactionRepo,
        private readonly ProductRepositoryInterface          $productRepo,
        private readonly DeliveryManRepositoryInterface      $deliveryManRepo,
        private readonly OrderRepositoryInterface            $orderRepo,
        private readonly BrandRepositoryInterface            $brandRepo,
        private readonly VendorRepositoryInterface           $vendorRepo,
        private readonly VendorWalletRepositoryInterface     $vendorWalletRepo,
        private readonly RestockProductRepositoryInterface   $restockProductRepo,
        private readonly DashboardService                    $dashboardService,
        private readonly ChattingRepositoryInterface          $chattingRepo,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     * Index function is the starting point of a controller
     */
    public function index(Request|null $request, ?string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $workspace = app(\App\Services\AdminWorkspaceService::class);
        $totals = $workspace->totals();
        $data = \App\Utils\Helpers::module_permission_check('people') ? $workspace->activation() : [];
        return view('admin-views.system.dashboard', compact('totals', 'data'));
    }

    public function getOrderStatus(Request $request)
    {
        session()->put('statistics_type', $request['statistics_type']);
        $data = self::getOrderStatusData();
        return response()->json(['view' => view('admin-views.partials._dashboard-order-status', compact('data'))->render()], 200);
    }


    public function getOrderStatusData(): array
    {
        $statisticsType = session('statistics_type');

        [$startDate, $endDate] = match($statisticsType) {
            'today'      => [now()->startOfDay(),   now()->endOfDay()],
            'this_week'  => [now()->startOfWeek(),  now()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'this_year'  => [now()->startOfYear(),  now()->endOfYear()],
            default      => [null, null],
        };

        $statusCounts  = $this->orderRepo->getOrderStatusCounts(startDate: $startDate, endDate:  $endDate);
        $storeCount    = $this->vendorRepo->getCountForDashboard(startDate: $startDate, endDate: $endDate);
        $productCount  = $this->productRepo->getCountForDashboard(startDate: $startDate, endDate:  $endDate);
        $customerCount = $this->customerRepo->getCountForDashboard(startDate:  $startDate, endDate:  $endDate);

        return [
            'order'            => $statusCounts['all'],
            'failed'           => $statusCounts['failed'],
            'pending'          => $statusCounts['pending'],
            'returned'         => $statusCounts['returned'],
            'canceled'         => $statusCounts['canceled'],
            'confirmed'        => $statusCounts['confirmed'],
            'delivered'        => $statusCounts['delivered'],
            'processing'       => $statusCounts['processing'],
            'out_for_delivery' => $statusCounts['out_for_delivery'],
            'store'            => $storeCount,
            'product'          => $productCount,
            'customer'         => $customerCount,
        ];
    }
    public function getCommonQueryOrderStatus($query)
    {
        $today = session()->has('statistics_type') && session('statistics_type') == 'today' ? 1 : 0;
        $this_week = session()->has('statistics_type') && session('statistics_type') == 'this_week' ? 1 : 0;
        $this_month = session()->has('statistics_type') && session('statistics_type') == 'this_month' ? 1 : 0;
        $this_year = session()->has('statistics_type') && session('statistics_type') == 'this_year' ? 1 : 0;

        return $query
            ->when($today, function ($query) {
                return $query->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]);
            })
            ->when($this_week, function ($query) {
                return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            })
            ->when($this_month, function ($query) {
                return $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
            })
            ->when($this_year, function ($query) {
                return $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
            })
            ->count();
    }

    public function getOrderStatistics(Request $request): JsonResponse
    {
        $dateType = $request['type'];
        $dateTypeArray = $this->dashboardService->getDateTypeData(dateType: $dateType);
        $from = $dateTypeArray['from'];
        $to = $dateTypeArray['to'];
        $type = $dateTypeArray['type'];
        $range = $dateTypeArray['range'];
        $inHouseOrderEarningArray = $this->getOrderStatisticsData(from: $from, to: $to, range: $range, type: $type, userType: 'admin');
        $vendorOrderEarningArray = $this->getOrderStatisticsData(from: $from, to: $to, range: $range, type: $type, userType: 'seller');
        $label = $dateTypeArray['keyRange'] ?? [];
        $inHouseOrderEarningArray = array_values($inHouseOrderEarningArray);
        $vendorOrderEarningArray = array_values($vendorOrderEarningArray);
        return response()->json([
            'view' => view('admin-views.system.partials.order-statistics', compact('inHouseOrderEarningArray', 'vendorOrderEarningArray', 'label', 'dateType'))->render(),
        ]);
    }

    public function getEarningStatistics(Request $request): JsonResponse
    {
        $dateType = $request['type'];
        $dateTypeArray = $this->dashboardService->getDateTypeData(dateType: $dateType);
        $from = $dateTypeArray['from'];
        $to = $dateTypeArray['to'];
        $type = $dateTypeArray['type'];
        $range = $dateTypeArray['range'];
        $inHouseEarning = $this->getEarning(from: $from, to: $to, range: $range, type: $type, userType: 'admin');
        $vendorEarning = $this->getEarning(from: $from, to: $to, range: $range, type: $type, userType: 'seller');
        $commissionEarn = $this->getAdminCommission(from: $from, to: $to, range: $range, type: $type);
        $label = $dateTypeArray['keyRange'] ?? [];
        $inHouseEarning = array_values($inHouseEarning);
        $vendorEarning = array_values($vendorEarning);
        $commissionEarn = array_values($commissionEarn);
        return response()->json([
            'view' => view('admin-views.system.partials.earning-statistics', compact('inHouseEarning', 'vendorEarning', 'commissionEarn', 'label', 'dateType'))->render(),
        ]);
    }

    protected function getOrderStatisticsData($from, $to, $range, $type, $userType): array
    {
        $orderEarnings = $this->orderRepo->getListWhereBetween(
            filters: [
                'seller_is' => $userType,
                'payment_status' => 'paid'
            ],
            selectColumn: 'order_amount',
            whereBetween: 'created_at',
            whereBetweenFilters: [$from, $to],
        );
        $orderEarningArray = [];
        foreach ($range as $value) {
            $matchingEarnings = $orderEarnings->where($type, $value);
            if ($matchingEarnings->count() > 0) {
                $orderEarningArray[$value] = usdToDefaultCurrency($matchingEarnings->sum('sums'));
            } else {
                $orderEarningArray[$value] = 0;
            }
        }
        return $orderEarningArray;
    }

    protected function getEarning(string|Carbon $from, string|Carbon $to, array $range, string $type, $userType): array
    {
        $earning = $this->orderTransactionRepo->getListWhereBetween(
            filters: [
                'seller_is' => $userType,
                'status' => 'disburse',
            ],
            selectColumn: 'seller_amount',
            whereBetween: 'created_at',
            groupBy: $type,
            whereBetweenFilters: [$from, $to],
        );
        return $this->dashboardService->getDateWiseAmount(range: $range, type: $type, amountArray: $earning);
    }

    /**
     * @param string|Carbon $from
     * @param string|Carbon $to
     * @param array $range
     * @param string $type
     * @return array
     */
    protected function getAdminCommission(string|Carbon $from, string|Carbon $to, array $range, string $type): array
    {
        $commissionGiven = $this->orderTransactionRepo->getListWhereBetween(
            filters: [
                'seller_is' => 'seller',
                'status' => 'disburse',
            ],
            selectColumn: 'admin_commission',
            whereBetween: 'created_at',
            groupBy: $type,
            whereBetweenFilters: [$from, $to],
        );
        return $this->dashboardService->getDateWiseAmount(range: $range, type: $type, amountArray: $commissionGiven);
    }

    public function getRealTimeActivities(): JsonResponse
    {
        $newOrder = $this->orderRepo->getListWhere(filters: ['checked' => 0], dataLimit: 'all')->count();
        $pendingProductReviewCount = Product::query()
            ->where('added_by', 'seller')
            ->where('request_status', 0)
            ->count();
        $restockProductList = $this->restockProductRepo->getListWhere(filters: ['added_by' => 'in_house'], dataLimit: 'all')->groupBy('product_id');
        $restockProduct = [];
        if (count($restockProductList) == 1) {
            $products = $this->restockProductRepo->getListWhere(orderBy: ['updated_at' => 'desc'], filters: ['added_by' => 'in_house'], relations: ['product'], dataLimit: 'all');
            $firstProduct = $products->first();
            $count = $products?->sum('restock_product_customers_count') ?? 0;
            $restockProduct = [
                'title' => $firstProduct?->product?->name ?? '',
                'body' => $count < 100 ? translate('This_product_has') . ' ' . $count . ' ' . translate('restock_request') : translate('This_product_has') . ' 99+ ' . translate('restock_request'),
                'image' => getStorageImages(path: $firstProduct?->product?->thumbnail_full_url ?? '', type: 'product'),
                'route' => route('admin.products.request-restock-list')
            ];
        } elseif (count($restockProductList) > 1) {
            $restockProduct = [
                'title' => translate('Restock_Request'),
                'body' => count($restockProductList) < 100 ? (count($restockProductList) . ' ' . translate('products_have_restock_request')) : ('99 +' . ' ' . translate('more_products_have_restock_request')),
                'image' => dynamicAsset(path: 'public/assets/back-end/img/icons/restock-request-icon.svg'),
                'route' => route('admin.products.request-restock-list')
            ];
        }
        $chatting = $this->chattingRepo->getListWhereNotNull(
            filters: ['admin_id' => 0, 'seen_by_admin' => 0, 'notification_receiver' => 'admin', 'seen_notification' => 0],
            whereNotNull: ['admin_id'],
        )->count();

        $this->chattingRepo->updateListWhereNotNull(
            filters: ['admin_id' => 0, 'seen_by_admin' => 0, 'notification_receiver' => 'admin', 'seen_notification' => 0],
            whereNotNull: ['admin_id'],
            data: ['seen_notification' => 1]
        );

        return response()->json([
            'success' => 1,
            'new_order_count' => $newOrder,
            'pending_product_review_count' => $pendingProductReviewCount,
            'restockProductCount' => $restockProductList->count(),
            'restockProduct' => $restockProduct,
            'newMessagesExist' => $chatting,
            'message' => $chatting > 1 ? $chatting . ' ' . translate('New_Message') : translate('New_Message'),
        ]);
    }
}
