<?php

namespace App\Services;

use App\Models\{User, Order, RefundRequest, WalletTransaction};
use App\Http\Requests\Admin\RefundStatusRequest;
use DomainException;
use Illuminate\Support\Facades\DB;

class CustomerAccountRefundService
{
    public function credit(int $customerId, int $refundId, int $adminId, string $reason, string $key): WalletTransaction
    {
        return DB::transaction(function () use ($customerId,$refundId,$adminId,$reason,$key) {
            $initial=RefundRequest::where('customer_id',$customerId)->findOrFail($refundId);
            $order=Order::where('customer_id',$customerId)->findOrFail($initial->order_id);
            if (\Illuminate\Support\Facades\Schema::hasTable('order_vendor_pricings') && \App\Models\OrderVendorPricing::where('order_id',$order->id)->exists()) app(SellerLedgerService::class)->lockSellerFinancialState((int)$order->seller_id);
            User::lockForUpdate()->findOrFail($customerId);
            $refund=RefundRequest::where('customer_id',$customerId)->lockForUpdate()->findOrFail($refundId);
            $reference='customer-return-refund-'.$refundId;
            $existing=WalletTransaction::where('user_id',$customerId)->where('reference',$reference)->first();
            if ($existing && $refund->status==='refunded') return $existing;
            if ($refund->status!=='approved' || $refund->amount<=0 || !trim($reason)) throw new DomainException('يلزم مرتجع معتمد بمبلغ موجب ولم يُرد سابقًا.');
            if (DB::table('refund_transactions')->where('refund_id',$refundId)->exists()) throw new DomainException('يوجد رد مسجل لهذا المرتجع؛ راجع سجله قبل التنفيذ.');
            // Preserve the incumbent settlement, stock, loyalty, refund status and reporting workflow.
            $legacy=RefundStatusRequest::create('/admin/refund-section/refund/refund-status-update','POST',[
                'id'=>$refundId,'refund_status'=>'refunded','payment_method'=>'purchase_wallet','payment_info'=>$reference,'approved_note'=>$reason]);
            $response=app(\App\Http\Controllers\Admin\Order\RefundController::class)->updateRefundStatus($legacy,app(RefundStatusService::class),app(RefundTransactionService::class));
            $result=$response->getData(true);
            if ($response->getStatusCode()!==200 || isset($result['error']) || isset($result['errors'])) throw new DomainException((string)($result['error']??'تعذر استكمال مسار المرتجع.'));
            if ($refund->fresh()->status!=='refunded') throw new DomainException('لم يكتمل توثيق رد المرتجع؛ لم يضف رصيد.');
            $entry=app(CustomerPurchaseWalletPolicyService::class)->creditPurchaseRefundBaseCurrency($customerId,(float)$refund->amount,$reference);
            DB::table('customer_account_actions')->insert(['customer_id'=>$customerId,'admin_id'=>$adminId,'request_key'=>$key,
                'signature'=>hash('sha256',json_encode([$customerId,$refundId,$reason])),'operation'=>'refund_credit','source'=>'approved_return','reason'=>$reason,
                'financial_reference'=>$reference,'amount'=>$refund->amount,'details'=>json_encode(['refund_id'=>$refundId,'order_id'=>$refund->order_id]),'created_at'=>now(),'updated_at'=>now()]);
            app(CustomerPaymentNotificationService::class)->notify($customerId,'approved-return-'.$refundId,'تمت إضافة قيمة المرتجع '.number_format($refund->amount,3).' إلى محفظتكم بنجاح.');
            return $entry;
        },3);
    }
}
