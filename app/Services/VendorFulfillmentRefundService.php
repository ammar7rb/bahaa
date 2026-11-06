<?php

namespace App\Services;

use App\Models\{Order, OrderDetail, RefundRequest, RefundStatus, RefundTransaction, VendorFulfillmentRequest};
use DomainException;
use Illuminate\Support\Facades\{DB, Schema};

/** Administration-owned customer refunds. Vendor fees and vendor receivables are separate ledgers. */
class VendorFulfillmentRefundService
{
    public function prepare(int $requestId,int $lineId,float $amount,int $adminId,string $reason): RefundRequest
    {
        if(!is_finite($amount)||$amount<=0||trim($reason)==='')throw new DomainException('حدد مبلغ رد العميل وسبب المراجعة.');
        return DB::transaction(function()use($requestId,$lineId,$amount,$adminId,$reason){
            $initial=VendorFulfillmentRequest::findOrFail($requestId);$order=Order::whereKey($initial->order_id)->lockForUpdate()->firstOrFail();
            $row=VendorFulfillmentRequest::lockForUpdate()->findOrFail($requestId);
            if(!in_array($row->status,['canceled','rejected'],true)||$order->payment_status!=='paid')throw new DomainException('رد العميل متاح بعد إغلاق الجزء المدفوع ومراجعة الإدارة.');
            if(!DB::table('vendor_fulfillment_request_items')->where('request_id',$requestId)->where('order_detail_id',$lineId)->exists())abort(404);
            $line=OrderDetail::where('order_id',$order->id)->lockForUpdate()->findOrFail($lineId);
            $amount=round($amount,2);
            // Administration applies the existing discount/tax/refund rules. The
            // explicit amount cannot exceed the paid customer line or order balance.
            $maximum=max(0,(float)$line->price*(int)$line->qty-(float)$line->discount+($line->tax_model==='include'?0:(float)$line->tax));
            if($amount>$maximum+0.00000001)throw new DomainException('مبلغ الرد يتجاوز قيمة بند العميل. راجع الخصومات والضرائب قبل الاعتماد.');
            $existing=RefundRequest::where('order_details_id',$lineId)->lockForUpdate()->first();
            if($existing){
                if((int)$existing->fulfillment_request_id!==$requestId||abs((float)$existing->amount-$amount)>0.00000001)throw new DomainException('يوجد طلب رد سابق للبند بشروط مختلفة؛ راجعه بدل إنشاء رد ثانٍ.');
                return $existing;
            }
            $this->assertNoOrderWideRefund($order);
            $reserved=(float)RefundRequest::where('order_id',$order->id)->where('status','!=','rejected')->sum('amount');
            if($reserved+$amount>(float)$order->order_amount+0.00000001)throw new DomainException('إجمالي الردود يتجاوز مبلغ العميل المدفوع.');
            $refund=RefundRequest::create(['fulfillment_request_id'=>$requestId,'order_details_id'=>$lineId,'customer_id'=>$order->customer_id,
                'order_id'=>$order->id,'product_id'=>$line->product_id,'amount'=>$amount,'status'=>'pending','refund_reason'=>$reason,'images'=>[],'change_by'=>'admin']);
            $line->update(['refund_request'=>1]);
            app(OrderFinancialAuditService::class)->record($order,'vendor_portion_refund_prepared','refund_requests',$refund->id,['request_id'=>$requestId,'customer_refund_amount'=>$amount],$adminId);
            return $refund;
        },3);
    }

    public function process(int $id,array $data,int $adminId): RefundRequest
    {
        validator($data,['refund_status'=>'required|in:pending,approved,rejected,refunded','payment_method'=>'nullable|string|max:100',
            'payment_info'=>'nullable|string|max:1000','approved_note'=>'nullable|string|max:2000','rejected_note'=>'nullable|string|max:2000'])->validate();
        return DB::transaction(function()use($id,$data,$adminId){
            $initial=RefundRequest::findOrFail($id);$order=Order::whereKey($initial->order_id)->lockForUpdate()->firstOrFail();
            $refund=RefundRequest::lockForUpdate()->findOrFail($id);$row=VendorFulfillmentRequest::findOrFail($refund->fulfillment_request_id);
            if((int)$row->order_id!==(int)$order->id||!in_array($row->status,['canceled','rejected'],true))throw new DomainException('طلب رد العميل لا يطابق جزءًا مغلقًا.');
            $status=$data['refund_status'];
            if($refund->status===$status)return $refund;
            if($refund->status==='refunded')throw new DomainException('تم رد هذا المبلغ بالفعل؛ لا يمكن تكراره أو تعديل تاريخه.');
            if(!in_array($status,['pending','approved','rejected','refunded'],true))throw new DomainException('حالة الاسترداد غير صحيحة.');
            $update=['status'=>$status,'change_by'=>'admin'];
            if($status==='refunded'){
                if($order->payment_status!=='paid')throw new DomainException('لم يؤكد دفع العميل.');
                $this->assertNoOrderWideRefund($order);
                $paid=(float)RefundRequest::where('order_id',$order->id)->where('status','refunded')->sum('amount');
                if($paid+(float)$refund->amount>(float)$order->order_amount+0.00000001)throw new DomainException('إجمالي الاسترداد يتجاوز المبلغ المدفوع.');
                $method=(string)($data['payment_method']??'');$reference=trim((string)($data['payment_info']??''));
                if($method===''||$reference==='')throw new DomainException('حدد وسيلة رد العميل ومرجع تأكيدها.');
                if(in_array($method,['customer_wallet','purchase_wallet'],true)){
                    if((int)getWebConfig('wallet_status')!==1||(int)getWebConfig('wallet_add_refund')!==1)throw new DomainException('رد العميل إلى المحفظة غير مفعل في إعدادات الاسترداد الحالية.');
                    $transaction=app(CustomerPurchaseWalletPolicyService::class)->creditPurchaseRefundBaseCurrency((int)$refund->customer_id,(float)$refund->amount,'vendor-fulfillment-refund-'.$refund->id);
                    if(abs((float)$transaction->credit-(float)$refund->amount)>0.00000001||(int)$transaction->user_id!==(int)$refund->customer_id)throw new DomainException('مرجع المحفظة لا يطابق رد العميل.');
                }
                RefundTransaction::create(['order_id'=>$order->id,'payment_for'=>'Refund Request','payer_id'=>$adminId,'payment_receiver_id'=>$refund->customer_id,
                    'paid_by'=>'admin','paid_to'=>'customer','payment_method'=>$method,'payment_status'=>'paid','amount'=>$refund->amount,
                    'transaction_type'=>'Refund','order_details_id'=>$refund->order_details_id,'refund_id'=>$refund->id]);
                $update['payment_info']=$reference;
                app(CustomerPurchaseLimitService::class)->returnRefundedOrderDetailLimit($order,OrderDetail::findOrFail($refund->order_details_id),$refund->id);
                app(OrderFinancialAuditService::class)->record($order,'vendor_portion_customer_refunded','refund_requests',$refund->id,
                    ['request_id'=>$row->id,'customer_refund_amount'=>$refund->amount,'vendor_debit'=>0,'method'=>$method,'reference'=>$reference],$adminId);
            }
            foreach(['approved_note','rejected_note'] as $field)if(isset($data[$field]))$update[$field]=$data[$field];
            $refund->update($update);OrderDetail::whereKey($refund->order_details_id)->update(['refund_request'=>['pending'=>1,'approved'=>2,'rejected'=>3,'refunded'=>4][$status]]);
            RefundStatus::create(['refund_request_id'=>$id,'change_by'=>'admin','change_by_id'=>$adminId,'status'=>$status,'message'=>$data['approved_note']??$data['rejected_note']??$data['payment_info']??null]);
            DB::afterCommit(fn()=>\App\Events\RefundEvent::dispatch($status,$order,$refund,OrderDetail::findOrFail($refund->order_details_id)));
            return $refund->fresh();
        },3);
    }

    private function assertNoOrderWideRefund(Order $order): void
    {
        foreach(['order_admin_purchase_refunds','order_refund_ledgers'] as $table)if(Schema::hasTable($table)&&DB::table($table)->where('order_id',$order->id)->where('status','!=','rejected')->exists())
            throw new DomainException('يوجد مسار استرداد آخر للطلب؛ راجعه لمنع رد المبلغ مرتين.');
    }
}
