<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\OrderWorkflowService;
use App\Services\SellerOrderVisibilityService;
use App\Services\VendorOrderPresentationService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** One ownership and fulfillment boundary for current and legacy vendor clients. */
class VendorOrderBoundary
{
    public function handle(Request $request, Closure $next, int $sellerId): mixed
    {
        try {
            app(\App\Services\VendorFulfillmentUnlockService::class)->releaseForSeller($sellerId);
        } catch (\Throwable $exception) {
            report($exception);
        }
        $action = (string) ($request->route()?->getAction('controller') ?? '');
        [$controller, $method] = array_pad(explode('@', $action), 2, '');
        if (in_array(class_basename($controller), ['ShippingProofController', 'SellerShippingResponseController', 'SellerShippingSupportController'], true)) {
            $reference = $request->route('order') ?? $request->route('id') ?? $request->input('order_id');
            $auxiliaryOrder = $reference instanceof Order ? $reference : Order::whereKey($reference)->where('seller_id', $sellerId)->first();
            abort_unless($auxiliaryOrder && (int) $auxiliaryOrder->seller_id === $sellerId, 404);
            if(app(\App\Services\VendorFulfillmentDecisionService::class)->managed($auxiliaryOrder)){
                if(class_basename($controller)==='ShippingProofController' && $request->isMethod('GET')){
                    $ids=app(\App\Services\VendorFulfillmentEligibilityService::class)->requests($auxiliaryOrder)
                        ->filter(fn($row)=>!app(\App\Services\VendorFulfillmentEligibilityService::class)->restricted($row))->pluck('id');
                    $proofs=\App\Models\OrderShippingProof::where('order_id',$auxiliaryOrder->id)->where('seller_id',$sellerId)->whereIn('fulfillment_request_id',$ids);
                    if($method==='index')return response()->json(['order_id'=>$auxiliaryOrder->id,'proofs'=>$proofs->latest('id')->get()->map(fn($proof)=>[
                        'id'=>$proof->id,'fulfillment_request_id'=>$proof->fulfillment_request_id,'shipping_status'=>$proof->shipping_status,
                        'review_status'=>$proof->review_status,'mime_type'=>$proof->mime_type,'created_at'=>$proof->created_at?->toIso8601String(),
                    ])]);
                    $proof=$request->route('proof'); $proofId=$proof instanceof \App\Models\OrderShippingProof?$proof->id:$proof;
                    abort_unless($proofs->whereKey($proofId)->exists(),404);
                    return $next($request);
                }
                if(!$request->isMethod('GET'))abort(422,'استخدم إجراءات جزء التنفيذ لتسجيل القبول أو إثبات التسليم.');
            }
            abort_if(app(\App\Services\VendorFulfillmentEligibilityService::class)->hasRestrictions($auxiliaryOrder), 423, \App\Services\VendorFulfillmentEligibilityService::MESSAGE);
        }
        if (class_basename($controller) === 'ProductReportController') {
            $query = \App\Models\VendorProductWorkspace::where('seller_id', $sellerId)->latest('id');
            $pagination = (clone $query)->paginate(25)->withQueryString();
            $products = $pagination->getCollection()->map(fn($state)=>[
                'reference'=>$state->private_reference,'name'=>$state->seller_content['name']??'',
                'unit_price'=>$state->seller_content['unit_price']??null,'stock'=>$state->seller_content['current_stock']??null,
                'active'=>app(\App\Services\VendorProductWorkspaceService::class)->vendorActive($state),
            ]);
            if (str_contains(strtolower($method), 'export')) return response()->streamDownload(function()use($query){
                $file=fopen('php://output','w');fwrite($file,"\xEF\xBB\xBF");fputcsv($file,['مرجع الإعلان','اسم منتجك','سعر التاجر','المخزون المسجل']);
                foreach($query->cursor() as $state){$name=(string)($state->seller_content['name']??'');if(preg_match('/^[=+@\-]/',$name))$name="'".$name;
                    fputcsv($file,[$state->private_reference,$name,$state->seller_content['unit_price']??null,$state->seller_content['current_stock']??null]);}fclose($file);
            },'vendor-inventory.csv');
            return response()->view('vendor-views.order.procurement-report',['products'=>$products,'orders'=>collect(),'pagination'=>$pagination]);
        }
        if (in_array(class_basename($controller), ['OrderReportController', 'TransactionReportController'], true)) {
            $query = Order::where('seller_id', $sellerId)->where('seller_is', 'seller')->sellerAccessible()->latest('id');
            $pagination = (clone $query)->paginate(25)->withQueryString();
            $rows = $pagination->getCollection()->filter(fn ($row) => app(SellerOrderVisibilityService::class)->canSellerView($row))
                ->map(fn ($row) => app(VendorOrderPresentationService::class)->order($row));
            if (str_contains(strtolower($method), 'export')) {
                return response()->streamDownload(function () use ($query) {
                    $file=fopen('php://output','w');fwrite($file,"\xEF\xBB\xBF");fputcsv($file,['الطلب','الحالة','المبلغ المتاح','المبلغ المقيد']);
                    foreach($query->cursor() as $order) {
                        if(!app(SellerOrderVisibilityService::class)->canSellerView($order))continue;
                        $row=app(VendorOrderPresentationService::class)->order($order,false);
                        fputcsv($file,[$row['id'],$row['order_status'],($row['details_locked'] ?? false) ? 0 : $row['order_amount'],$row['restricted_amount'] ?? 0]);
                    }fclose($file);
                },'vendor-procurement-report.csv');
            }
            return response()->view('vendor-views.order.procurement-report',['orders'=>$rows,'pagination'=>$pagination]);
        }
        if (str_ends_with($controller, '\\RefundController')) return $this->returns($request, $sellerId, $method);
        if (str_ends_with($controller, '\\POSOrderController') && in_array($method, ['index', 'getOrderDetails'], true)) {
            $pos = Order::where('seller_id', $sellerId)->where('seller_is', 'seller')->where('order_type', 'POS')->find($request->route('id'));
            abort_unless($pos, 404);
        }
        $orderController = str_ends_with($controller, '\\OrderController') && !str_contains($controller, '\\POS\\');
        $orderEdit = str_ends_with($controller, '\\OrderEditController');
        $invoice = str_ends_with($controller, '\\VendorFinanceWorkspaceController') && $method === 'invoiceDocument';
        if (!$orderController && !$orderEdit && !$invoice) {
            $response = $next($request);
            // Nested order DTOs in notifications, dashboards and finance endpoints use the same allowlist.
            if ($response instanceof JsonResponse) $response->setData(app(VendorOrderPresentationService::class)->sanitize($response->getData(true), $sellerId));
            return $response;
        }
        if ($orderEdit) return $this->denied($request);
        $reference = $request->route('id') ?? $request->route('order') ?? $request->input('order_id') ?? $request->input('id');
        $order = null;
        if ($reference !== null) {
            if (in_array($method, ['digital_file_upload_after_sell', 'uploadDigitalFileAfterSell'], true)) {
                $reference = OrderDetail::whereKey($reference)->where('seller_id', $sellerId)->value('order_id');
            }
            abort_unless(is_scalar($reference) && (string)$reference !== '', 404);
            $order = Order::where('seller_id', $sellerId)->where('seller_is', 'seller')
                ->where(fn ($q) => ctype_digit((string) $reference) ? $q->whereKey($reference) : $q->where('seller_restricted_access_token', $reference))->first();
            abort_unless($order && app(SellerOrderVisibilityService::class)->canSellerView($order), 404);
        }
        if ($request->isMethod('GET') || ($request->is('api/*') && in_array($method, ['list', 'recent', 'history'], true))) {
            if ($request->is('api/*') && $method === 'list' && $request->input('status') === 'pending_vendor') {
                $query = Order::where('seller_id', $sellerId)->where('seller_is', 'seller')->sellerAccessible();
                if (app(\App\Services\VendorFulfillmentEligibilityService::class)->available()) {
                    $query->whereExists(fn ($requests) => $requests->selectRaw('1')->from('vendor_fulfillment_requests as r')
                        ->whereColumn('r.order_id', 'orders.id')->whereColumn('r.seller_id', 'orders.seller_id')->where('r.status', 'pending_vendor'));
                } else $query->whereRaw('1 = 0');
                $limit = min(50, max(1, (int) $request->input('limit', 10)));
                $offset = max(1, (int) $request->input('offset', 1));
                $page = $query->latest('id')->paginate($limit, ['*'], 'offset', $offset);
                return response()->json(['total_size' => $page->total(), 'limit' => $limit, 'offset' => $offset,
                    'orders' => $page->getCollection()->map(fn ($row) => app(VendorOrderPresentationService::class)->order($row))->all()]);
            }
            if ($invoice || $method === 'generateInvoice') {
                abort_unless($order, 404);
                abort_if(app(\App\Services\VendorFulfillmentEligibilityService::class)->summary($order)['details_locked'] ?? false, 423, \App\Services\VendorFulfillmentEligibilityService::MESSAGE);
                $row = app(VendorOrderPresentationService::class)->order($order);
                $pdf = new \Mpdf\Mpdf(['default_font' => 'FreeSerif', 'mode' => 'utf-8', 'autoLangToFont' => true]);
                $pdf->autoScriptToLang = true;
                $pdf->WriteHTML(view('vendor-views.order.procurement-document', compact('row'))->render());
                return response($pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN), 200,
                    ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="vendor-procurement-'.$order->id.'.pdf"', 'Cache-Control' => 'private, no-store']);
            }
            if ($request->is('api/*') && $method === 'details' && $order) {
                abort_if(app(\App\Services\VendorFulfillmentEligibilityService::class)->summary($order)['details_locked'] ?? false, 423, \App\Services\VendorFulfillmentEligibilityService::MESSAGE);
                return response()->json($order->details
                    ->filter(fn ($line) => app(\App\Services\VendorFulfillmentEligibilityService::class)->lineAllowed($line))
                    ->map(fn ($line) => app(VendorOrderPresentationService::class)->line($line))->values());
            }
            if (!$request->is('api/*')) {
                $query = Order::where('seller_id', $sellerId)->where('seller_is', 'seller')->sellerAccessible()->latest('id');
                $status = $request->route('status');
                if ($request->input('fulfillment') === 'pending_vendor' && app(\App\Services\VendorFulfillmentEligibilityService::class)->available()) {
                    $query->whereExists(fn ($requests) => $requests->selectRaw('1')->from('vendor_fulfillment_requests as r')
                        ->whereColumn('r.order_id', 'orders.id')->whereColumn('r.seller_id', 'orders.seller_id')->where('r.status', 'pending_vendor'));
                }
                if (in_array($status, array_keys(OrderWorkflowService::TRANSITIONS), true)) $query->where('order_status', $status);
                $pagination = (!$order && $method !== 'exportList') ? $query->paginate(25)->withQueryString() : null;
                $orders = $order ? collect([$order]) : ($pagination ? $pagination->getCollection() : $query->get());
                $data = $orders->filter(fn ($row) => app(SellerOrderVisibilityService::class)->canSellerView($row))
                    ->map(fn ($row) => app(VendorOrderPresentationService::class)->order($row));
                if ($method === 'exportList') return response()->streamDownload(function () use ($data) {
                    $file = fopen('php://output', 'w'); fwrite($file, "\xEF\xBB\xBF");
                    fputcsv($file, ['الطلب', 'الحالة', 'المبلغ المتاح', 'المبلغ المقيد']);
                    foreach ($data as $row) fputcsv($file, [$row['id'], $row['order_status'], ($row['details_locked'] ?? false) ? 0 : $row['order_amount'], $row['restricted_amount'] ?? 0]);
                    fclose($file);
                }, 'vendor-fulfillment.csv');
                return response()->view('vendor-views.order.fulfillment', ['orders' => $data, 'pagination' => $pagination, 'document' => $invoice || $method === 'generateInvoice']);
            }
            $response = $next($request);
            if ($response instanceof JsonResponse) $response->setData(app(VendorOrderPresentationService::class)->sanitize($response->getData(true), $sellerId));
            return $response;
        }
        // Customer payments, prices, addresses, refunds and agreed shipping are admin-owned.
        if (!in_array($method, ['order_detail_status', 'updateStatus', 'updateOrderDetails', 'digital_file_upload_after_sell', 'uploadDigitalFileAfterSell'], true)) return $this->denied($request);
        abort_unless($order, 404);
        $digitalUpload=in_array($method,['digital_file_upload_after_sell','uploadDigitalFileAfterSell'],true);
        $managed=app(\App\Services\VendorFulfillmentDecisionService::class)->managed($order);
        abort_if($managed&&!$digitalUpload, 422, 'استخدم إجراءات جزء التنفيذ لعدم تغيير أجزاء الطلب الأخرى.');
        if($managed&&$digitalUpload){
            $lineId=$request->input('order_id')??$request->input('id');
            abort_unless(DB::table('vendor_fulfillment_request_items as i')->join('vendor_fulfillment_requests as r','r.id','=','i.request_id')
                ->where('i.order_detail_id',$lineId)->where('r.order_id',$order->id)->where('r.seller_id',$sellerId)
                ->whereIn('r.status',['accepted','preparing','ready','shipping'])->exists(),422,'اقبل جزء التنفيذ المعني قبل رفع ملفه.');
        }
        abort_if((!$digitalUpload||!$managed)&&app(\App\Services\VendorFulfillmentEligibilityService::class)->hasRestrictions($order), 423, \App\Services\VendorFulfillmentEligibilityService::MESSAGE);
        return DB::transaction(function () use ($request, $next, $order, $sellerId, $method) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless((int) $locked->seller_id === $sellerId && app(SellerOrderVisibilityService::class)->canSellerView($locked), 404);
            if(in_array($method,['digital_file_upload_after_sell','uploadDigitalFileAfterSell'],true)&&app(\App\Services\VendorFulfillmentDecisionService::class)->managed($locked)){
                abort_unless(DB::table('vendor_fulfillment_request_items as i')->join('vendor_fulfillment_requests as r','r.id','=','i.request_id')
                    ->where('i.order_detail_id',$request->input('order_id')??$request->input('id'))->where('r.order_id',$locked->id)
                    ->where('r.seller_id',$sellerId)->whereIn('r.status',['accepted','preparing','ready','shipping'])->exists(),422,'تغيرت أهلية رفع ملف هذا الجزء.');
            }
            if (in_array($method, ['order_detail_status', 'updateStatus', 'updateOrderDetails'], true)) {
                $to = (string) $request->input('order_status');
                if ($to === $locked->order_status) return response()->json(['success' => 1, 'message' => translate('order_status_updated_successfully'), 'already_applied' => true]);
                try { app(OrderWorkflowService::class)->assertTransition($locked->order_status, $to, 'seller'); }
                catch (\DomainException) { return $this->denied($request, 'order_status_transition_not_allowed'); }
                if ($locked->shipping_assignment_status !== 'assigned'
                    || in_array($locked->seller_shipping_response_status, ['pending', 'overdue', 'rejected'], true)
                    || ($locked->shipping_fulfillment_mode === 'admin_shipping' && $to !== 'processing')) {
                    return $this->denied($request, 'accept_or_reject_shipping_terms_before_processing');
                }
            }
            $before = $locked->order_status;
            $lastEvent = (int) \App\Models\OrderLogisticsEvent::where('order_id', $locked->id)->max('id');
            $response = $method === 'updateOrderDetails'
                ? app(\App\Http\Controllers\RestAPI\v3\seller\OrderController::class)->order_detail_status($request->merge(['id' => $locked->id]))
                : $next($request);
            $fresh = $locked->fresh();
            if ($before !== $fresh->order_status && !\App\Models\OrderLogisticsEvent::where('order_id', $locked->id)->where('id', '>', $lastEvent)->where('order_status', $fresh->order_status)->exists()) {
                app(\App\Services\OrderLogisticsService::class)->recordOrderStatus($fresh, $fresh->order_status, 'seller', $sellerId, $before);
            }
            if ($response instanceof JsonResponse) $response->setData(app(VendorOrderPresentationService::class)->sanitize($response->getData(true), $sellerId));
            return $response;
        });
    }

    private function denied(Request $request, string $message = 'هذا الإجراء من صلاحيات الإدارة.'): mixed
    {
        return $request->is('api/*') || $request->expectsJson()
            ? response()->json(['success' => 0, 'message' => translate($message)], 422)
            : back()->withErrors(['order' => translate($message)]);
    }

    private function returns(Request $request, int $sellerId, string $method): mixed
    {
        if (!$request->isMethod('GET')) return $this->denied($request, 'المرتجعات والقرارات المالية تُدار بواسطة الإدارة.');
        if ($request->is('api/*') && $request->header('X-Vendor-Order-Contract') !== 'procurement-v1') {
            return response()->json(['message'=>'يرجى تحديث تطبيق التاجر لعرض متابعة المرتجعات.'],409);
        }
        $query = \App\Models\RefundRequest::whereHas('order', fn ($q) => $q->where('seller_id', $sellerId)->where('seller_is', 'seller')->sellerAccessible())->latest('id');
        $id = $request->route('id') ?? $request->input('id');
        if ($id !== null) $query->whereKey($id);
        if ($request->filled('order_details_id')) $query->where('order_details_id', $request->input('order_details_id'));
        $rows = $query->get()->filter(fn ($row) => app(SellerOrderVisibilityService::class)->canSellerView($row->order)
            && app(\App\Services\VendorFulfillmentEligibilityService::class)->lineAllowed($row->orderDetails))->map(function ($row) {
            $line = app(VendorOrderPresentationService::class)->line($row->orderDetails, false);
            return ['id'=>$row->id,'order_id'=>$row->order_id,'order_details_id'=>$row->order_details_id,
                'status'=>$row->status,'refund_status'=>[],'amount'=>null,'financial_details_withheld'=>true,
                'product'=>$line['product_details'],'order_details'=>$line,'images'=>[],
                'created_at'=>$row->created_at?->toIso8601String(),'updated_at'=>$row->updated_at?->toIso8601String()];
        })->values();
        if ($id !== null || $request->filled('order_details_id')) abort_if($rows->isEmpty(),404);
        if ($request->is('api/*')) {
            if ($method === 'refund_details') {
                $line=$rows->first()['order_details'];
                return response()->json(['product_price'=>$line['price'],'quntity'=>$line['qty'],
                    'product_total_discount'=>0,'product_total_tax'=>0,'subtotal'=>null,'coupon_discount'=>0,'referral_discount'=>0,
                    'refund_amount'=>null,'financial_details_withheld'=>true,'refund_request'=>$rows->all()]);
            }
            return response()->json($id !== null ? $rows->first() : $rows->all());
        }
        if ($method === 'exportList') return response()->streamDownload(function () use ($rows) {
            $file=fopen('php://output','w');fwrite($file,"\xEF\xBB\xBF");fputcsv($file,['طلب الإرجاع','الطلب','الحالة']);
            foreach($rows as $row)fputcsv($file,[$row['id'],$row['order_id'],$row['status']]);fclose($file);
        },'vendor-returns.csv');
        return response()->view('vendor-views.order.returns',['returns'=>$rows]);
    }
}
