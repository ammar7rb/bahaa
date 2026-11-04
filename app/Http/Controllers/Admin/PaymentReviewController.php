<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerBalanceDeposit;
use App\Models\SellerPackageSubscription;
use App\Services\SellerBalanceDepositService;
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
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('people') || \App\Utils\Helpers::module_permission_check('orders'), 403);
        $request->validate([
            'panel' => 'nullable|in:settings',
            'record_type' => 'nullable|string|max:40', 'record_id' => 'nullable|integer|min:1',
            'audience' => 'nullable|in:customer,vendor', 'kind' => 'nullable|in:order,subscription,package,balance_deposit',
            'status' => 'nullable|in:pending,approved,rejected,reconciliation,reversed',
            'method' => 'nullable|string|max:191', 'searchValue' => 'nullable|string|max:191',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''),
            'page' => 'nullable|integer|min:1',
        ]);
        if ($request->input('panel') === 'settings') {
            abort_unless(\App\Utils\Helpers::module_permission_check('people'), 403);
            return view('admin-views.payment-reviews.index', [
                'settingsPanel' => app(\App\Http\Controllers\Admin\Vendor\SellerBalanceFundingSettingsController::class)->pageData(),
            ]);
        }
        // Normalize first, then apply identical filters to every workflow and count.
        $rows = $this->rows(new Request($request->only(['audience', 'kind', 'record_id', 'record_type'])))->map(function ($row) {
            $row['method'] = $row['method'] ?? data_get($row, 'offline.method_name') ?: translate('offline_payment');
            $row['request_number'] = $row['request_number'] ?? '#'.$row['id'];
            return $row;
        })->sortByDesc('created_at')->values();
        $methods = $rows->pluck('method')->filter()->unique()->sort()->values();
        if ($request->filled('record_type')) $rows = $rows->where('type', $request->input('record_type'))->values();
        if ($request->filled('record_id')) $rows = $rows->where('id', (int) $request->input('record_id'))->values();
        $rows = $rows->filter(function ($row) use ($request) {
            if ($request->filled('method') && $row['method'] !== $request->input('method')) return false;
            $date = substr((string) $row['created_at'], 0, 10);
            if ($request->filled('from') && $date < $request->input('from')) return false;
            if ($request->filled('to') && $date > $request->input('to')) return false;
            $actor = $row['actor'];
            $text = implode(' ', [$row['request_number'], $row['reference'], $row['id'], ($row['audience'] === 'vendor' ? 'M' : 'C').$actor?->id, $actor?->f_name, $actor?->l_name, $actor?->email, $actor?->phone]);
            $term = strtr(trim((string)$request->input('searchValue')), array_combine(preg_split('//u', '٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('0123456789')));
            $phones = app(\App\Services\EgyptPhoneService::class);
            return $term === '' || mb_stripos($text, $term) !== false || ($phones->isValid($term) && $phones->normalizeLocal($term) === $phones->normalizeLocal($actor?->phone));
        })->values();
        $counts = $rows->countBy('status');
        if ($request->filled('status')) $rows = $rows->where('status', $request->input('status'))->values();
        $perPage = max(1, min(100, (int) (getWebConfig(name: 'pagination_limit') ?: 20)));
        $page = max(1, (int) $request->get('page', 1));
        $pageRows = $rows->forPage($page, $perPage)->map(fn ($row) => $this->detailRow($row))->values();
        $paginator = new LengthAwarePaginator($pageRows, $rows->count(), $perPage, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('admin-views.payment-reviews.index', ['reviews' => $paginator, 'counts' => $counts, 'methods' => $methods, 'pendingCount' => $counts->get('pending', 0)]);
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
        abort_if(in_array($type, ['vendor_insurance', 'vendor_order_insurance'], true), 410, translate('seller_insurance_retired'));
        $this->authorizeType($type);
        $request->validate(['payment_reference' => 'nullable|string|max:191', 'review_note' => 'nullable|string|max:1000']);
        try {
            if ($type === 'customer_balance_deposit') {
                app(\App\Services\CustomerBalanceDepositService::class)->review(\App\Models\CustomerBalanceDeposit::where('wallet_type', 'purchase')->findOrFail($id), 'approve', (int) auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'customer_subscription') {
                abort_unless(\App\Utils\Helpers::module_permission_check('people'),403);
                app(\App\Services\CustomerMonthlySubscriptionService::class)->review((int)$id,'approve',(int)auth('admin')->id(),$request->get('review_note'));
            } elseif ($type === 'vendor_balance_deposit') {
                $deposit = SellerBalanceDeposit::query()->where('source', SellerBalanceDeposit::SOURCE_OFFLINE)->where('status', SellerBalanceDeposit::STATUS_PENDING)->where(fn ($q) => $q->whereNull('metadata->wallet_target')->orWhere('metadata->wallet_target', '!=', 'insurance')->orWhereNotNull('metadata->insurance_retirement'))->findOrFail($id);
                $this->balanceDeposits->markPaid($deposit, ['payment_method' => 'offline_payment', 'transaction_id' => $request->get('payment_reference') ?: 'seller-balance-offline-'.$deposit->id, 'payment_amount' => $deposit->amount, 'currency_code' => $deposit->currency_code], auth('admin')->id(), $request->get('review_note'));
                if ($deposit->fresh()->funding_intent?->status === 'manual_settlement') return back()->withErrors(['payment'=>$deposit->fresh()->funding_intent->message]);
            } elseif ($type === 'vendor_package') {
                $subscription = SellerPackageSubscription::query()->where('payment_method', 'offline_payment')->where('payment_status', 'unpaid')->where('status', SellerPackageSubscription::STATUS_PENDING_REVIEW)->findOrFail($id);
                $result = $this->packageService->markPaid($subscription, ['payment_method' => 'offline_payment', 'transaction_id' => $request->get('payment_reference') ?: 'unified-review-'.$subscription->id, 'payment_amount' => (float) $subscription->paid_package_price, 'currency_code' => getCurrencyCode(type: 'default')], auth('admin')->id(), $request->get('review_note'));
                if (($result['status'] ?? 0) !== 1) throw new DomainException($result['message'] ?? 'seller_package_approval_failed');
            } elseif ($type === 'customer_initial_order') {
                app(\App\Services\CustomerPurchaseEligibilityService::class)->reviewInitialConfirmedPayment((int) $id, true, (int) auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'customer_order') {
                $order = Order::query()->where('payment_method', 'offline_payment')->findOrFail($id);
                $this->reviewOrder($order, true, $request);
            } else {
                throw new DomainException('offline_payment_review_not_found');
            }
            ToastMagic::success(translate('offline_payment_approved_successfully'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            abort(404);
        } catch (DomainException $exception) {
            return back()->withErrors(['payment'=>translate($exception->getMessage())]);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['payment'=>__('payments.review_error')]);
        }
        return back();
    }

    public function reject(Request $request, string $type, int|string $id): RedirectResponse
    {
        abort_if(in_array($type, ['vendor_insurance', 'vendor_order_insurance'], true), 410, translate('seller_insurance_retired'));
        $this->authorizeType($type);
        $request->validate(['review_note' => 'required|string|max:1000']);
        try {
            if ($type === 'customer_balance_deposit') {
                app(\App\Services\CustomerBalanceDepositService::class)->review(\App\Models\CustomerBalanceDeposit::where('wallet_type', 'purchase')->findOrFail($id), 'reject', (int) auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'customer_subscription') {
                abort_unless(\App\Utils\Helpers::module_permission_check('people'),403);
                app(\App\Services\CustomerMonthlySubscriptionService::class)->review((int)$id,'reject',(int)auth('admin')->id(),$request->get('review_note'));
            } elseif ($type === 'vendor_balance_deposit') {
                $deposit = SellerBalanceDeposit::query()->where('source', SellerBalanceDeposit::SOURCE_OFFLINE)->where('status', SellerBalanceDeposit::STATUS_PENDING)->where(fn ($q) => $q->whereNull('metadata->wallet_target')->orWhere('metadata->wallet_target', '!=', 'insurance')->orWhereNotNull('metadata->insurance_retirement'))->findOrFail($id);
                $this->balanceDeposits->reject($deposit, auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'vendor_package') {
                $subscription = SellerPackageSubscription::query()->where('payment_method', 'offline_payment')->where('payment_status', 'unpaid')->where('status', SellerPackageSubscription::STATUS_PENDING_REVIEW)->findOrFail($id);
                $this->packageService->rejectOfflinePayment($subscription, auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'customer_initial_order') {
                app(\App\Services\CustomerPurchaseEligibilityService::class)->reviewInitialConfirmedPayment((int) $id, false, (int) auth('admin')->id(), $request->get('review_note'));
            } elseif ($type === 'customer_order') {
                $order = Order::query()->where('payment_method', 'offline_payment')->where('payment_status', 'unpaid')->findOrFail($id);
                $this->reviewOrder($order, false, $request);
            } else {
                throw new DomainException('offline_payment_review_not_found');
            }
            ToastMagic::success(translate('offline_payment_rejected_successfully'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            abort(404);
        } catch (DomainException $exception) {
            return back()->withErrors(['payment'=>translate($exception->getMessage())]);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['payment'=>__('payments.review_error')]);
        }
        return back();
    }

    private function detailRow(array $row): array
    {
        $row['proof_urls'] = [];
        $information = in_array($row['type'], ['vendor_balance_deposit','vendor_package','vendor_extra_ad','customer_order'], true) ? ($row['offline'] ?? []) : [];
        $proofs = $row['type'] === 'customer_order' ? $this->orderProofFiles($information) : $this->proofFiles($information);
        foreach ($proofs as $index => $file) {
            $row['proof_urls'][] = route('admin.payment-reviews.proof', ['type'=>$row['type'], 'id'=>$row['id'], 'file'=>$index]);
        }
        if (!empty($row['proof_url'])) $row['proof_urls'][] = $row['proof_url'];
        if ($row['type'] === 'customer_order' && data_get($row, 'offline.private_retry_proof')) $row['proof_urls'][] = route('admin.payment-reviews.customer-order.retry-proof', $row['id']);
        if (isset($row['_order'])) $row['customer_state'] = app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($row['_order']);
        if (isset($row['_deposit'])) {
            $intent = $row['_deposit']->funding_intent;
            $row['note'] = $intent?->message ?: $row['_deposit']->payment_note;
            $row['purpose'] = $intent?->kind;
        }
        unset($row['_order'], $row['_deposit']);
        return $row;
    }

    private function authorizeType(string $type): void
    {
        abort_unless(in_array($type, ['customer_balance_deposit', 'customer_subscription', 'vendor_balance_deposit', 'vendor_package', 'customer_initial_order', 'customer_order'], true), 404);
        abort_unless(\App\Utils\Helpers::module_permission_check(in_array($type, ['customer_order', 'customer_initial_order'], true) ? 'orders' : 'people'), 403);
    }

    public function proof(Request $request, string $type, int $id)
    {
        $this->authorizeType($type === 'vendor_extra_ad' ? 'vendor_package' : $type);
        $request->validate(['file'=>'nullable|integer|min:0|max:20']);
        [$information, $directory] = match ($type) {
            'vendor_balance_deposit' => [SellerBalanceDeposit::findOrFail($id)->payment_proof, 'seller-balance/payment-proof'],
            'vendor_package' => [data_get(SellerPackageSubscription::findOrFail($id)->metadata, 'offline_payment'), 'seller-package/payment-proof'],
            'vendor_extra_ad' => [\App\Models\VendorExtraAdPurchase::findOrFail($id)->payment_details, 'seller-package/payment-proof'],
            'customer_order' => [Order::findOrFail($id)->offlinePayments?->payment_info, 'offline-payment/order-proof'],
            default => abort(404),
        };
        $proofs = $type === 'customer_order' ? $this->orderProofFiles($information) : $this->proofFiles($information);
        $proof = $proofs[$request->integer('file')] ?? null;
        abort_unless($proof && in_array($proof['storage'] ?? 'public', ['public', 's3'], true), 404);
        $name = $proof['image_name'];
        abort_unless(is_string($name) && basename($name) === $name && !str_contains($name, '\\') && !str_contains($name, '..'), 404);
        $disk = \Illuminate\Support\Facades\Storage::disk($proof['storage'] ?? 'public');
        $path = $directory.'/'.$name;
        abort_unless($disk->exists($path), 404);
        $bytes = $disk->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true), 404);
        return response($bytes, 200, ['Content-Type'=>$mime, 'Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff']);
    }

    private function proofFiles($information): array
    {
        if (!is_array($information)) return [];
        if (isset($information['image_name'])) return [$information];
        $files = [];
        foreach ($information as $value) $files = array_merge($files, $this->proofFiles($value));
        return $files;
    }

    private function orderProofFiles($information): array
    {
        if (!is_array($information)) return [];
        if (isset($information['image_name'])) return $this->proofFiles($information);
        return array_merge($this->proofFiles($information['payment_proof'] ?? []), $this->proofFiles($information['payment_screenshot'] ?? []));
    }

    private function reviewOrder(Order $order, bool $approve, Request $request): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $approve, $request) {
            // Match the existing financial service's lock order, including legacy rejection.
            \App\Models\User::lockForUpdate()->findOrFail($order->customer_id);
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ($order->payment_status !== 'unpaid' || $order->activation_status === 'offline_payment_rejected') {
                throw new DomainException('deposit_already_reviewed');
            }
            if ($approve) {
                app(\App\Services\CustomerPurchaseEligibilityService::class)->approveOrderPayment((int)$order->id, $request->get('payment_reference') ?: 'offline-review-'.$order->id);
            } elseif ($order->customer_checkout_operation_id) {
                app(\App\Services\CustomerPurchaseEligibilityService::class)->rejectOrderPayment((int)$order->id, $request->get('review_note'));
            } else {
                $order->update(['order_status'=>'canceled', 'activation_status'=>'offline_payment_rejected',
                    'order_note'=>trim(($order->order_note ? $order->order_note."\n" : '').'Offline payment rejected: '.$request->get('review_note'))]);
            }
        }, 3);
    }

    private function rows(Request $request): Collection
    {
        $rows = collect();
        $audience = $request->get('audience');
        $kind = $request->get('kind');
        $status = $request->get('status');
        $search = trim((string) $request->get('searchValue'));
        $recordId = $request->integer('record_id');
        $customerHistory = $recordId && $request->input('record_type') === 'customer_balance_deposit';
        $vendorHistory = $recordId && $request->input('record_type') === 'vendor_balance_deposit';

        if (\App\Utils\Helpers::module_permission_check('people') && (!$audience || $audience === 'customer') && (!$kind || $kind === 'balance_deposit')) {
            \App\Models\CustomerBalanceDeposit::with('customer:id,f_name,l_name,email,phone')->whereIn('wallet_type', $customerHistory ? ['purchase','insurance'] : ['purchase'])->whereIn('status', $customerHistory ? ['pending','paid','rejected','legacy_review'] : ['pending', 'paid', 'rejected'])->when($recordId, fn ($q) => $q->where('id', $recordId))->latest('id')->get()->each(function ($deposit) use (&$rows) {
                $rows->push(['id'=>$deposit->id, 'type'=>'customer_balance_deposit', 'kind'=>'balance_deposit', 'audience'=>'customer',
                    'status'=>$deposit->wallet_type === 'insurance' ? 'reconciliation' : ($deposit->status === 'paid' ? 'approved' : $deposit->status), 'actor'=>$deposit->customer,
                    'label'=>$deposit->wallet_type === 'insurance' ? __('payments.historical_record') : __('payments.customer_wallet'),
                    'destination'=>$deposit->wallet_type === 'insurance' ? __('payments.retired_balance') : __('payments.customer_wallet'),
                    'read_only'=>$deposit->wallet_type === 'insurance',
                    'amount'=>(float)$deposit->amount, 'submitted_amount'=>$deposit->submitted_amount, 'currency'=>$deposit->currency_code,
                    'request_number'=>$deposit->transaction_number, 'reference'=>$deposit->payment_reference,
                    'method'=>$deposit->method_name, 'offline'=>$deposit->method_information, 'note'=>$deposit->payment_note,
                    'proof_url'=>$deposit->payment_proof ? route('admin.customer.wallet.deposits.proof', $deposit->id) : null,
                    'review_note'=>$deposit->review_note, 'reviewed_at'=>$deposit->reviewed_at, 'reviewer'=>$deposit->reviewed_by_admin_id,
                    'created_at'=>$deposit->created_at]);
            });
        }

        if (\App\Utils\Helpers::module_permission_check('orders') && (!$audience || $audience === 'customer') && (!$kind || $kind === 'order')
            && \Illuminate\Support\Facades\Schema::hasTable('post_purchase_invoices')) {
            \App\Models\PostPurchaseInvoice::with('customer')->where('status', 'awaiting_review')->when($recordId, fn ($q) => $q->where('id', $recordId))->latest('id')->get()->each(function ($invoice) use (&$rows) {
                $rows->push(['id'=>$invoice->id, 'type'=>'customer_legacy_invoice', 'kind'=>'order', 'audience'=>'customer',
                    'status'=>'reconciliation', 'actor'=>$invoice->customer, 'label'=>__('payments.legacy_invoice'),
                    'amount'=>(float)$invoice->total_amount, 'currency'=>getCurrencyCode(type:'default'),
                    'reference'=>$invoice->payment_reference, 'request_number'=>$invoice->invoice_number,
                    'method'=>$invoice->payment_method ?: translate('offline_payment'), 'offline'=>[],
                    'related_url'=>route('admin.orders.details', $invoice->order_id), 'created_at'=>$invoice->created_at]);
            });
        }

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
                ->when($recordId, fn ($q) => $q->where('id', $recordId))->latest('id')->get()->each(function($payment) use(&$rows) {
                    $rows->push(['id'=>$payment->id,'type'=>'customer_subscription','kind'=>'subscription','audience'=>'customer','status'=>['pending_review'=>'pending', 'paid'=>'approved'][$payment->status] ?? $payment->status,'actor'=>$payment->customer,'label'=>($payment->terms_name ?: 'اشتراك سابق').' · الحد الممنوح '.$payment->granted_allowance.' ج.م · '.($payment->method_name ?: $payment->payment_method),'amount'=>(float)$payment->quoted_amount,'currency'=>$payment->currency_code,'reference'=>$payment->payment_reference,'offline'=>$payment->method_information,'method'=>$payment->method_name ?: $payment->payment_method,'proof_url'=>$payment->payment_proof ? route('admin.customer.monthly-subscriptions.proof', $payment->id) : null,'review_note'=>$payment->review_note,'reviewed_at'=>$payment->reviewed_at,'reviewer'=>$payment->reviewed_by,'created_at'=>$payment->submitted_at ?: $payment->created_at]);
                });
        }

        if (\App\Utils\Helpers::module_permission_check('people') && (!$audience || $audience === 'vendor')) {
            if (!$kind || $kind === 'balance_deposit') {
                SellerBalanceDeposit::query()->with(['seller:id,f_name,l_name,email,phone', 'offlinePaymentMethod:id,method_name'])
                    ->when(!$vendorHistory, fn ($q) => $q->where('source', SellerBalanceDeposit::SOURCE_OFFLINE)
                        ->where(fn ($q) => $q->whereNull('metadata->wallet_target')->orWhere('metadata->wallet_target', '!=', 'insurance')->orWhereNotNull('metadata->insurance_retirement')))
                    ->when($status === 'pending', fn ($q) => $q->where('status', SellerBalanceDeposit::STATUS_PENDING))
                    ->when($status === 'approved', fn ($q) => $q->where('status', SellerBalanceDeposit::STATUS_PAID))
                    ->when($status === 'rejected', fn ($q) => $q->where('status', SellerBalanceDeposit::STATUS_REJECTED))
                    ->when(!$status, fn ($q) => $q->whereIn('status', [SellerBalanceDeposit::STATUS_PENDING, SellerBalanceDeposit::STATUS_PAID, SellerBalanceDeposit::STATUS_REJECTED, SellerBalanceDeposit::STATUS_REVERSED]))
                    ->when($recordId, fn ($q) => $q->where('id', $recordId))->latest('id')->get()->each(function (SellerBalanceDeposit $deposit) use (&$rows, $search) {
                        $text = strtolower(implode(' ', [$deposit->id, $deposit->transaction_reference, $deposit->seller?->email, $deposit->seller?->phone]));
                        if ($search !== '' && !str_contains($text, strtolower($search))) return;
                        $target = __('payments.vendor_balance');
                        $historical = data_get($deposit->metadata, 'wallet_target') === 'insurance' && !data_get($deposit->metadata, 'insurance_retirement');
                        $rows->push([
                            'id' => $deposit->id, 'type' => 'vendor_balance_deposit', 'kind' => 'balance_deposit', 'audience' => 'vendor',
                            'status' => $historical ? 'reconciliation' : ($deposit->status === SellerBalanceDeposit::STATUS_PAID ? 'approved' : $deposit->status),
                            'actor' => $deposit->seller, 'label' => $target, 'amount' => (float) $deposit->amount,
                            'method' => $deposit->offlinePaymentMethod?->method_name ?: ($deposit->payment_method ?: translate('offline_payment')),
                            'currency' => $deposit->currency_code, 'reference' => $deposit->transaction_reference ?: 'DEP-'.$deposit->id,
                            'request_number' => $deposit->transaction_number, 'destination' => $historical ? __('payments.retired_balance') : $target,
                            'read_only' => $historical || $deposit->source !== SellerBalanceDeposit::SOURCE_OFFLINE,
                            '_deposit' => $deposit,
                            'review_note' => $deposit->rejection_reason ?: $deposit->review_note, 'reviewed_at' => $deposit->reviewed_at, 'reviewer' => $deposit->reviewed_by_admin_id,
                            'offline' => array_merge(['method_name' => $deposit->offlinePaymentMethod?->method_name], $deposit->offline_information ?: [], ['payment_proof' => $deposit->payment_proof, 'payment_note' => $deposit->payment_note]),
                            'created_at' => $deposit->created_at,
                        ]);
                    });
            }
            if ((!$kind || $kind === 'package') && \Illuminate\Support\Facades\Schema::hasTable('vendor_extra_ad_purchases')) {
                \App\Models\VendorExtraAdPurchase::with('seller:id,f_name,l_name,email,phone')
                    ->where('payment_method', 'offline_payment')
                    ->whereIn('status', ['pending_review', 'paid', 'manual_settlement', 'payment_rejected'])
                    ->when($status, fn ($q) => $q->where('status', ['pending'=>'pending_review', 'approved'=>'paid', 'rejected'=>'payment_rejected', 'reconciliation'=>'manual_settlement'][$status] ?? 'unknown'))
                    ->when($recordId, fn ($q) => $q->where('id', $recordId))->latest('id')->get()->each(function ($purchase) use (&$rows, $search) {
                        $text = strtolower(implode(' ', [$purchase->admin_reference, $purchase->seller?->email, $purchase->seller?->phone]));
                        if ($search !== '' && !str_contains($text, strtolower($search))) return;
                        $rows->push(['id'=>$purchase->id, 'type'=>'vendor_extra_ad', 'kind'=>'package', 'audience'=>'vendor',
                            'status'=>['pending_review'=>'pending', 'paid'=>'approved', 'manual_settlement'=>'reconciliation', 'payment_rejected'=>'rejected'][$purchase->status],
                            'actor'=>$purchase->seller, 'label'=>'مساحات إضافية × '.$purchase->quantity, 'amount'=>(float)$purchase->total_price,
                            'currency'=>$purchase->currency, 'reference'=>$purchase->admin_reference, 'offline'=>$purchase->payment_details, 'review_note'=>$purchase->review_note, 'reviewer'=>$purchase->reviewed_by, 'created_at'=>$purchase->created_at]);
                    });
            }
            if (!$kind || $kind === 'package') {
                SellerPackageSubscription::query()->with(['seller:id,f_name,l_name,email,phone', 'package:id,name'])->where('payment_method', 'offline_payment')->when($status === 'pending', fn ($q) => $q->where('status', SellerPackageSubscription::STATUS_PENDING_REVIEW))->when($status === 'approved', fn ($q) => $q->where('payment_status', 'paid'))->when($status === 'rejected', fn ($q) => $q->where('status', SellerPackageSubscription::STATUS_REJECTED))->when(!$status, fn ($q) => $q->whereIn('status', [SellerPackageSubscription::STATUS_PENDING_REVIEW, SellerPackageSubscription::STATUS_ACTIVE, SellerPackageSubscription::STATUS_SCHEDULED, SellerPackageSubscription::STATUS_EXPIRED, SellerPackageSubscription::STATUS_REJECTED]))->when($recordId, fn ($q) => $q->where('id', $recordId))->latest('id')->get()->each(function ($subscription) use (&$rows, $search) {
                    $text = strtolower(implode(' ', [$subscription->package_name, $subscription->payment_reference, $subscription->seller?->email, $subscription->seller?->phone]));
                    if ($search !== '' && !str_contains($text, strtolower($search))) return;
                    $rows->push($this->normalize($subscription, 'vendor_package', 'package', 'vendor', $subscription->seller, $subscription->package_name, $subscription->paid_package_price, $subscription->metadata['offline_payment'] ?? null));
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
                ->when($recordId, fn ($q) => $q->where('id', $recordId))
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
                        '_order' => $order,
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
                ->when($recordId, fn ($q) => $q->where('id', $recordId))->latest('id')->get()->each(function (Order $order) use (&$rows, $search) {
                    $text = implode(' ', [$order->id, $order->transaction_ref, $order->customer?->phone, $order->customer?->f_name]);
                    if ($search !== '' && !str_contains(strtolower($text), strtolower($search))) return;
                    $rows->push(['id' => $order->id, 'type' => 'customer_initial_order', 'kind' => 'order', 'audience' => 'customer',
                        'status' => $order->customer_initial_payment_review_status, 'actor' => $order->customer,
                        'label' => 'مراجعة دفع الطلب الأول #'.$order->id, 'amount' => (float) $order->order_amount,
                        'currency' => getCurrencyCode(type: 'default'), 'reference' => $order->transaction_ref ?: 'ORD-'.$order->id,
                        'offline' => ['method_name' => $order->payment_method, 'ملاحظة' => 'الدفع مؤكد ماليًا. الموافقة لا تخصم مرة أخرى، والرفض يبقي السجل المالي للمراجعة.'],
                        '_order' => $order, 'created_at' => $order->created_at]);
                });
        }
        return $rows;
    }

    private function normalize($record, string $type, string $kind, string $audience, $actor, string $label, float $amount, ?array $offline): array
    {
        $pending = in_array($record->status, ['pending_offline_review', SellerPackageSubscription::STATUS_PENDING_REVIEW], true);
        return ['id' => $record->id, 'type' => $type, 'kind' => $kind, 'audience' => $audience, 'status' => $pending ? 'pending' : (($record->status === 'rejected') ? 'rejected' : 'approved'), 'actor' => $actor, 'label' => $label, 'amount' => $amount, 'currency' => $record->currency_code ?? getCurrencyCode(type: 'default'), 'reference' => $record->invoice_no ?? $record->payment_reference ?? $record->transaction_id, 'offline' => $offline, 'review_note'=>data_get($record->metadata, 'offline_payment_review.note'), 'reviewer'=>data_get($record->metadata, 'offline_payment_review.reviewed_by_admin_id'), 'reviewed_at'=>data_get($record->metadata, 'offline_payment_review.reviewed_at'), 'created_at' => $record->created_at];
    }

}
