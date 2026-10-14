<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerInsurance;
use App\Models\SellerBalanceDeposit;
use App\Models\SellerOrderInsurance;
use App\Models\SellerPackageSubscription;
use App\Services\SellerInsuranceService;
use App\Services\SellerBalanceDepositService;
use App\Services\SellerOrderInsuranceService;
use App\Services\PostPurchaseInvoiceService;
use App\Services\SellerPackagePurchaseService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PaymentReviewController extends Controller
{
    public function __construct(
        private readonly SellerPackagePurchaseService $packageService,
        private readonly SellerBalanceDepositService $balanceDeposits,
        private readonly SellerInsuranceService $insuranceService,
        private readonly SellerOrderInsuranceService $sellerOrderInsuranceService,
        private readonly PostPurchaseInvoiceService $postPurchaseInvoices,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('people') || \App\Utils\Helpers::module_permission_check('orders'), 403);
        $request->validate(['record_type' => 'nullable|string|max:40', 'record_id' => 'nullable|integer|min:1']);
        $rows = $this->rows($request)->sortByDesc('created_at')->values();
        if ($request->filled('record_type')) $rows = $rows->where('type', $request->input('record_type'))->values();
        if ($request->filled('record_id')) $rows = $rows->where('id', (int) $request->input('record_id'))->values();
        $perPage = (int) (getWebConfig(name: 'pagination_limit') ?: 20);
        $page = max(1, (int) $request->get('page', 1));
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('admin-views.payment-reviews.index', ['reviews' => $paginator, 'pendingCount' => $rows->where('status', 'pending')->count()]);
    }

    public function requestPackage(int $id): RedirectResponse
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('orders'), 403);
        app(\App\Services\CustomerPurchaseEligibilityService::class)->requestPackageForOrder($id);
        ToastMagic::success('تم إرسال إشعار اعتماد الدفع وطلب اختيار الباقة.');
        return back();
    }

    public function approve(Request $request, string $type, int|string $id): RedirectResponse
    {
        abort_unless(\App\Utils\Helpers::module_permission_check((str_starts_with($type, 'vendor_') || $type === 'customer_subscription') ? 'people' : 'orders'), 403);
        $request->validate(['payment_reference' => 'nullable|string|max:191', 'review_note' => 'nullable|string|max:1000']);
        try {
            if ($type === 'customer_subscription') {
                abort_unless(\App\Utils\Helpers::module_permission_check('people'),403);
                app(\App\Services\CustomerMonthlySubscriptionService::class)->review((int)$id,'approve',(int)auth('admin')->id(),$request->get('review_note'));
            } elseif ($type === 'vendor_balance_deposit') {
                $deposit = SellerBalanceDeposit::query()->where('source', SellerBalanceDeposit::SOURCE_OFFLINE)->where('status', SellerBalanceDeposit::STATUS_PENDING)->findOrFail($id);
                $this->balanceDeposits->markPaid($deposit, ['payment_method' => 'offline_payment', 'transaction_id' => $request->get('payment_reference') ?: 'seller-balance-offline-'.$deposit->id, 'payment_amount' => $deposit->amount, 'currency_code' => $deposit->currency_code], auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'vendor_package') {
                $subscription = SellerPackageSubscription::query()->where('payment_method', 'offline_payment')->where('payment_status', 'unpaid')->where('status', SellerPackageSubscription::STATUS_PENDING_REVIEW)->findOrFail($id);
                $result = $this->packageService->markPaid($subscription, ['payment_method' => 'offline_payment', 'transaction_id' => $request->get('payment_reference') ?: 'unified-review-'.$subscription->id, 'payment_amount' => (float) $subscription->paid_package_price, 'currency_code' => getCurrencyCode(type: 'default')], auth('admin')->id(), $request->get('review_note'));
                if (($result['status'] ?? 0) !== 1) throw new DomainException($result['message'] ?? 'seller_package_approval_failed');
            } elseif ($type === 'vendor_insurance') {
                $insurance = SellerInsurance::query()->where('payment_method', 'offline_payment')->where('payment_status', 'unpaid')->where('status', SellerInsurance::STATUS_PENDING_REVIEW)->findOrFail($id);
                $result = $this->insuranceService->markPaid($insurance, ['payment_method' => 'offline_payment', 'transaction_id' => $request->get('payment_reference') ?: 'unified-review-'.$insurance->id, 'payment_amount' => (float) $insurance->amount, 'currency_code' => getCurrencyCode(type: 'default')], auth('admin')->id(), $request->get('review_note'));
                if (($result['status'] ?? 0) !== 1) throw new DomainException($result['message'] ?? 'seller_insurance_approval_failed');
            } elseif ($type === 'vendor_order_insurance') {
                $insurance = SellerOrderInsurance::query()->where('payment_method', 'offline_payment')->where('payment_status', 'unpaid')->where('status', SellerOrderInsurance::STATUS_PENDING_REVIEW)->findOrFail($id);
                $this->sellerOrderInsuranceService->markPaid($insurance, 'offline_payment', $request->get('payment_reference') ?: 'unified-order-insurance-'.$insurance->id, auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'customer_initial_order') {
                app(\App\Services\CustomerPurchaseEligibilityService::class)->reviewInitialConfirmedPayment((int) $id, true, (int) auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'customer_order') {
                $order = Order::query()->where('payment_method', 'offline_payment')->findOrFail($id);
                app(\App\Services\CustomerPurchaseEligibilityService::class)->approveOrderPayment((int) $order->id, $request->get('payment_reference') ?: ('offline-review-'.$order->id));
            } else {
                throw new DomainException('offline_payment_review_not_found');
            }
            ToastMagic::success(translate('offline_payment_approved_successfully'));
        } catch (\Throwable $exception) {
            ToastMagic::error(translate($exception->getMessage()));
        }
        return back();
    }

    public function reject(Request $request, string $type, int|string $id): RedirectResponse
    {
        abort_unless(\App\Utils\Helpers::module_permission_check((str_starts_with($type, 'vendor_') || $type === 'customer_subscription') ? 'people' : 'orders'), 403);
        $request->validate(['review_note' => 'required|string|max:1000']);
        try {
            if ($type === 'customer_subscription') {
                abort_unless(\App\Utils\Helpers::module_permission_check('people'),403);
                app(\App\Services\CustomerMonthlySubscriptionService::class)->review((int)$id,'reject',(int)auth('admin')->id(),$request->get('review_note'));
            } elseif ($type === 'vendor_balance_deposit') {
                $deposit = SellerBalanceDeposit::query()->where('source', SellerBalanceDeposit::SOURCE_OFFLINE)->where('status', SellerBalanceDeposit::STATUS_PENDING)->findOrFail($id);
                $this->balanceDeposits->reject($deposit, auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'vendor_package') {
                $subscription = SellerPackageSubscription::query()->where('payment_method', 'offline_payment')->where('payment_status', 'unpaid')->where('status', SellerPackageSubscription::STATUS_PENDING_REVIEW)->findOrFail($id);
                $this->packageService->rejectOfflinePayment($subscription, auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'vendor_insurance') {
                $insurance = SellerInsurance::query()->where('payment_method', 'offline_payment')->where('payment_status', 'unpaid')->where('status', SellerInsurance::STATUS_PENDING_REVIEW)->findOrFail($id);
                $this->insuranceService->rejectOfflinePayment($insurance, auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'vendor_order_insurance') {
                $insurance = SellerOrderInsurance::query()->where('payment_method', 'offline_payment')->where('payment_status', 'unpaid')->where('status', SellerOrderInsurance::STATUS_PENDING_REVIEW)->findOrFail($id);
                $this->sellerOrderInsuranceService->rejectOfflinePayment($insurance, auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'customer_initial_order') {
                app(\App\Services\CustomerPurchaseEligibilityService::class)->reviewInitialConfirmedPayment((int) $id, false, (int) auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'customer_order') {
                $order = Order::query()->where('payment_method', 'offline_payment')->where('payment_status', 'unpaid')->findOrFail($id);
                if ($order->customer_checkout_operation_id) {
                    app(\App\Services\CustomerPurchaseEligibilityService::class)->rejectOrderPayment((int)$id, $request->get('review_note'));
                } else {
                    $order->update([
                    'order_status' => 'canceled',
                    'activation_status' => 'offline_payment_rejected',
                    'order_note' => trim(($order->order_note ? $order->order_note."\n" : '').'Offline payment rejected: '.$request->get('review_note')),
                ]);
                }
            } else {
                throw new DomainException('offline_payment_review_not_found');
            }
            ToastMagic::success(translate('offline_payment_rejected_successfully'));
        } catch (\Throwable $exception) {
            ToastMagic::error(translate($exception->getMessage()));
        }
        return back();
    }

    private function rows(Request $request): Collection
    {
        $rows = collect();
        $audience = $request->get('audience');
        $kind = $request->get('kind');
        $status = $request->get('status');
        $search = trim((string) $request->get('searchValue'));

        if (\App\Utils\Helpers::module_permission_check('orders') && (!$audience || $audience === 'customer')
            && (!$kind || $kind === 'order') && (!$status || $status === 'reconciliation')
            && \Illuminate\Support\Facades\Schema::hasTable('customer_legacy_payment_receipts')) {
            \Illuminate\Support\Facades\DB::table('customer_legacy_payment_receipts')
                ->when($search !== '', fn($q)=>$q->where('reference','like','%'.$search.'%'))->orderByDesc('received_at')->get()->each(function($receipt) use (&$rows) {
                    $rows->push(['id'=>$receipt->id,'type'=>'customer_legacy_receipt','kind'=>'order','audience'=>'customer',
                        'status'=>'reconciliation','actor'=>\App\Models\User::find($receipt->customer_id),
                        'label'=>'دفعة قديمة مؤكدة — تحتاج مطابقة مالية','amount'=>(float)$receipt->amount,'currency'=>$receipt->currency_code,
                        'reference'=>$receipt->reference,'offline'=>['ملاحظة'=>'مبلغ مؤكد من النظام السابق، دون إنشاء طلب أو تأمين جديد.'], 'created_at'=>$receipt->received_at]);
                });
        }


        if (\App\Utils\Helpers::module_permission_check('people') && (!$audience || $audience === 'customer') && (!$kind || $kind === 'subscription')) {
            \App\Models\CustomerSubscriptionPayment::with('customer')->whereNotIn('status',['draft','cancelled'])
                ->when($status,fn($q)=>$q->where('status',$status === 'pending' ? 'pending_review' : $status))
                ->when($search !== '',fn($q)=>$q->where(function($q) use($search){ $q->where('payment_reference','like','%'.$search.'%')->orWhereHas('customer',fn($c)=>$c->where('phone','like','%'.$search.'%')->orWhere('f_name','like','%'.$search.'%')); }))
                ->latest('id')->get()->each(function($payment) use(&$rows) {
                    $rows->push(['id'=>$payment->id,'type'=>'customer_subscription','kind'=>'subscription','audience'=>'customer','status'=>$payment->status === 'pending_review' ? 'pending' : $payment->status,'actor'=>$payment->customer,'label'=>($payment->terms_name ?: 'اشتراك سابق').' · الحد الممنوح '.$payment->granted_allowance.' ج.م · '.($payment->method_name ?: $payment->payment_method),'amount'=>(float)$payment->quoted_amount,'currency'=>$payment->currency_code,'reference'=>$payment->payment_reference,'offline'=>$payment->method_information,'created_at'=>$payment->created_at]);
                });
        }

        if (\App\Utils\Helpers::module_permission_check('people') && (!$audience || $audience === 'vendor')) {
            if (!$kind || $kind === 'balance_deposit') {
                SellerBalanceDeposit::query()->with(['seller:id,f_name,l_name,email,phone', 'offlinePaymentMethod:id,method_name'])
                    ->where('source', SellerBalanceDeposit::SOURCE_OFFLINE)
                    ->when($status === 'pending', fn ($q) => $q->where('status', SellerBalanceDeposit::STATUS_PENDING))
                    ->when($status === 'approved', fn ($q) => $q->where('status', SellerBalanceDeposit::STATUS_PAID))
                    ->when($status === 'rejected', fn ($q) => $q->where('status', SellerBalanceDeposit::STATUS_REJECTED))
                    ->when(!$status, fn ($q) => $q->whereIn('status', [SellerBalanceDeposit::STATUS_PENDING, SellerBalanceDeposit::STATUS_PAID, SellerBalanceDeposit::STATUS_REJECTED]))
                    ->latest('id')->get()->each(function (SellerBalanceDeposit $deposit) use (&$rows, $search) {
                        $text = strtolower(implode(' ', [$deposit->id, $deposit->transaction_reference, $deposit->seller?->email, $deposit->seller?->phone]));
                        if ($search !== '' && !str_contains($text, strtolower($search))) return;
                        $target = data_get($deposit->metadata, 'wallet_target') === 'insurance' ? 'رصيد التأمين' : 'رصيد المشتريات';
                        $rows->push([
                            'id' => $deposit->id, 'type' => 'vendor_balance_deposit', 'kind' => 'balance_deposit', 'audience' => 'vendor',
                            'status' => $deposit->status === SellerBalanceDeposit::STATUS_PENDING ? 'pending' : ($deposit->status === SellerBalanceDeposit::STATUS_PAID ? 'approved' : 'rejected'),
                            'actor' => $deposit->seller, 'label' => $target, 'amount' => (float) $deposit->amount,
                            'currency' => $deposit->currency_code, 'reference' => $deposit->transaction_reference ?: 'DEP-'.$deposit->id,
                            'offline' => array_merge(['method_name' => $deposit->offlinePaymentMethod?->method_name], $deposit->offline_information ?: [], ['payment_proof' => $deposit->payment_proof, 'payment_note' => $deposit->payment_note]),
                            'created_at' => $deposit->created_at,
                        ]);
                    });
            }
            if (!$kind || $kind === 'package') {
                SellerPackageSubscription::query()->with(['seller:id,f_name,l_name,email,phone', 'package:id,name'])->where('payment_method', 'offline_payment')->when($status === 'pending', fn ($q) => $q->where('status', SellerPackageSubscription::STATUS_PENDING_REVIEW))->when($status === 'approved', fn ($q) => $q->where('status', SellerPackageSubscription::STATUS_ACTIVE))->when($status === 'rejected', fn ($q) => $q->where('status', SellerPackageSubscription::STATUS_REJECTED))->when(!$status, fn ($q) => $q->whereIn('status', [SellerPackageSubscription::STATUS_PENDING_REVIEW, SellerPackageSubscription::STATUS_ACTIVE, SellerPackageSubscription::STATUS_REJECTED]))->latest('id')->get()->each(function ($subscription) use (&$rows, $search) {
                    $text = strtolower(implode(' ', [$subscription->package_name, $subscription->payment_reference, $subscription->seller?->email, $subscription->seller?->phone]));
                    if ($search !== '' && !str_contains($text, strtolower($search))) return;
                    $rows->push($this->normalize($subscription, 'vendor_package', 'package', 'vendor', $subscription->seller, $subscription->package_name, $subscription->paid_package_price, $subscription->metadata['offline_payment'] ?? null));
                });
            }
            if (!$kind || $kind === 'insurance') {
                SellerInsurance::query()->with(['seller:id,f_name,l_name,email,phone'])->where('payment_method', 'offline_payment')->when($status === 'pending', fn ($q) => $q->where('status', SellerInsurance::STATUS_PENDING_REVIEW))->when($status === 'approved', fn ($q) => $q->where('status', SellerInsurance::STATUS_PAID))->when($status === 'rejected', fn ($q) => $q->where('status', SellerInsurance::STATUS_REJECTED))->when(!$status, fn ($q) => $q->whereIn('status', [SellerInsurance::STATUS_PENDING_REVIEW, SellerInsurance::STATUS_PAID, SellerInsurance::STATUS_REJECTED]))->latest('id')->get()->each(function ($insurance) use (&$rows, $search) {
                    $text = strtolower(implode(' ', [$insurance->transaction_id, $insurance->payment_reference, $insurance->seller?->email, $insurance->seller?->phone]));
                    if ($search !== '' && !str_contains($text, strtolower($search))) return;
                    $rows->push($this->normalize($insurance, 'vendor_insurance', 'insurance', 'vendor', $insurance->seller, translate('seller_insurance'), $insurance->amount, $insurance->metadata['offline_payment'] ?? null));
                });
                SellerOrderInsurance::query()->with(['seller:id,f_name,l_name,email,phone'])->where('payment_method', 'offline_payment')->when($status === 'pending', fn ($q) => $q->where('status', SellerOrderInsurance::STATUS_PENDING_REVIEW))->when($status === 'approved', fn ($q) => $q->where('status', SellerOrderInsurance::STATUS_PAID))->when(!$status, fn ($q) => $q->whereIn('status', [SellerOrderInsurance::STATUS_PENDING_REVIEW, SellerOrderInsurance::STATUS_PAID]))->latest('id')->get()->each(function ($insurance) use (&$rows, $search) {
                    $text = strtolower(implode(' ', [$insurance->order_id, $insurance->payment_reference, $insurance->seller?->email, $insurance->seller?->phone]));
                    if ($search !== '' && !str_contains($text, strtolower($search))) return;
                    $rows->push($this->normalize($insurance, 'vendor_order_insurance', 'insurance', 'vendor', $insurance->seller, translate('seller_order_insurance') . ' #' . $insurance->order_id, $insurance->amount, $insurance->metadata['offline_payment'] ?? null));
                });
            }
        }
        if (\App\Utils\Helpers::module_permission_check('orders') && (!$audience || $audience === 'customer') && (!$kind || $kind === 'order')) {
            Order::query()
                ->with(['customer:id,f_name,l_name,email,phone', 'offlinePayments'])
                ->where('payment_method', 'offline_payment')
                ->when($status === 'pending', fn ($q) => $q->where('payment_status', 'unpaid'))
                ->when($status === 'approved', fn ($q) => $q->where('payment_status', 'paid'))
                ->when($status === 'rejected', fn ($q) => $q->where('activation_status', 'offline_payment_rejected'))
                ->when(!$status, fn ($q) => $q->where(function ($q) {
                    $q->whereIn('payment_status', ['unpaid', 'paid'])
                        ->orWhere('activation_status', 'offline_payment_rejected');
                }))
                ->latest('id')
                ->get()
                ->each(function (Order $order) use (&$rows, $search) {
                    $text = strtolower(implode(' ', [$order->id, $order->customer?->email, $order->customer?->phone]));
                    if ($search !== '' && !str_contains($text, strtolower($search))) return;
                    $status = $order->activation_status === 'offline_payment_rejected' ? 'rejected' : ($order->payment_status === 'paid' ? 'approved' : 'pending');
                    $rows->push([
                        'id' => $order->id,
                        'type' => 'customer_order',
                        'kind' => 'order',
                        'audience' => 'customer',
                        'status' => $status,
                        'actor' => $order->customer,
                        'label' => 'Order #'.$order->id,
                        'customer_state' => app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order),
                        'amount' => (float) $order->order_amount,
                        'currency' => getCurrencyCode(type: 'default'),
                        'reference' => $order->transaction_ref ?: 'ORD-'.$order->id,
                        'offline' => $order->offlinePayments?->payment_info,
                        'created_at' => $order->created_at,
                    ]);
                });
        }
        if (\App\Utils\Helpers::module_permission_check('orders') && (!$audience || $audience === 'customer') && (!$kind || $kind === 'order')
            && \Illuminate\Support\Facades\Schema::hasColumn('orders', 'customer_initial_payment_review_status')) {
            Order::with('customer')->where('payment_status', 'paid')->where('payment_method', '!=', 'offline_payment')
                ->whereNotNull('customer_initial_payment_review_status')
                ->when($status, fn($q) => $q->where('customer_initial_payment_review_status', $status))
                ->latest('id')->get()->each(function (Order $order) use (&$rows, $search) {
                    $text = implode(' ', [$order->id, $order->transaction_ref, $order->customer?->phone, $order->customer?->f_name]);
                    if ($search !== '' && !str_contains(strtolower($text), strtolower($search))) return;
                    $rows->push(['id' => $order->id, 'type' => 'customer_initial_order', 'kind' => 'order', 'audience' => 'customer',
                        'status' => $order->customer_initial_payment_review_status, 'actor' => $order->customer,
                        'label' => 'مراجعة دفع الطلب الأول #'.$order->id, 'amount' => (float) $order->order_amount,
                        'currency' => getCurrencyCode(type: 'default'), 'reference' => $order->transaction_ref ?: 'ORD-'.$order->id,
                        'offline' => ['method_name' => $order->payment_method, 'ملاحظة' => 'الدفع مؤكد ماليًا. الموافقة لا تخصم مرة أخرى، والرفض يبقي السجل المالي للمراجعة.'],
                        'customer_state' => app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order), 'created_at' => $order->created_at]);
                });
        }
        return $rows;
    }

    private function normalize($record, string $type, string $kind, string $audience, $actor, string $label, float $amount, ?array $offline): array
    {
        $pending = in_array($record->status, ['pending_offline_review', SellerPackageSubscription::STATUS_PENDING_REVIEW, SellerInsurance::STATUS_PENDING_REVIEW, SellerOrderInsurance::STATUS_PENDING_REVIEW], true);
        return ['id' => $record->id, 'type' => $type, 'kind' => $kind, 'audience' => $audience, 'status' => $pending ? 'pending' : (($record->status === 'rejected') ? 'rejected' : 'approved'), 'actor' => $actor, 'label' => $label, 'amount' => $amount, 'currency' => $record->currency_code ?? getCurrencyCode(type: 'default'), 'reference' => $record->invoice_no ?? $record->payment_reference ?? $record->transaction_id, 'offline' => $offline, 'created_at' => $record->created_at];
    }

}
