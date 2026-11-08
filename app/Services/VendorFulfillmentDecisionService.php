<?php

namespace App\Services;

use App\Events\OrderStatusEvent;
use App\Models\{Notification, Order, OrderDetail, OrderOperationalAlert, Product, Seller, VendorFulfillmentRequest};
use DomainException;
use Illuminate\Support\Facades\{DB, Schema};

class VendorFulfillmentDecisionService
{
    public const LABELS=['locked'=>'مقيد باختيار باقة','pending_vendor'=>'معلق من جهة التاجر','available'=>'متاح للمراجعة',
        'accepted'=>'تم القبول','preparing'=>'قيد التجهيز','ready'=>'جاهز','shipping'=>'قيد الشحن','delivered'=>'تم التسليم','rejected'=>'مرفوض قبل القبول','canceled'=>'ملغى بعد القبول','reassigned'=>'أعيد تعيين المنتج'];

    public function assertLegacyMutationAllowed(Order $order): void
    {
        if($this->managed($order))throw new DomainException('استخدم إجراءات جزء التنفيذ؛ لا يمكن تغيير الطلب أو مخزونه أو تسويته عبر المسار القديم.');
    }

    public function managed(Order $order): bool
    {
        return Schema::hasColumn('vendor_fulfillment_requests','accepted_at')
            && VendorFulfillmentRequest::where('order_id',$order->id)->where('seller_id',$order->seller_id)->exists();
    }

    public function actions(VendorFulfillmentRequest $row, Order $order): array
    {
        if(in_array($order->order_status,['canceled','returned','failed','delivered'],true)) return [];
        return match($row->status){
            'available'=>['accept','reject'],'accepted'=>['preparing','cancel'],'preparing'=>['ready','cancel'],
            'ready'=>$order->shipping_fulfillment_mode==='seller_shipping'?['shipping','cancel']:['cancel'],
            'shipping'=>['cancel'], default=>[],
        };
    }

    public function assertAggregateProgress(Order $order,string $to): void
    {
        if(!$this->managed($order)||$order->order_status===$to)return;
        $allowed=match($to){'processing'=>['accepted','preparing','ready','shipping','delivered'],
            'out_for_delivery'=>['shipping','delivered'],'delivered'=>['delivered'],default=>null};
        if($allowed===null)return;
        $active=VendorFulfillmentRequest::where('order_id',$order->id)->where('seller_id',$order->seller_id)->whereNotIn('status',['rejected','canceled','reassigned'])->get();
        if($active->isEmpty()||$active->contains(fn($row)=>!in_array($row->status,$allowed,true)))throw new DomainException('لا تتقدم حالة الطلب الكاملة قبل استيفاء قرارات وحالات جميع أجزاء التنفيذ.');
    }

    public function present(VendorFulfillmentRequest $row, Order $order): array
    {
        $locked=app(VendorFulfillmentEligibilityService::class)->restricted($row);
        $dto=['id'=>$row->id,'status'=>$row->status,'status_label'=>self::LABELS[$row->status]??$row->status,
            'amount'=>round((float)$row->vendor_amount+(float)$row->shipping_amount,8),
            'accepted_at'=>$row->accepted_at?->toIso8601String(),'rejected_at'=>$row->rejected_at?->toIso8601String(),
            'canceled_at'=>$row->canceled_at?->toIso8601String(),'unlocked_at'=>$row->unlocked_at?->toIso8601String(),
            'delivered_at'=>$row->delivered_at?->toIso8601String(),'decision_reason'=>$row->decision_reason,
            'actions'=>$this->actions($row,$order),'delivery_proof_required'=>$row->status==='shipping'&&$order->shipping_fulfillment_mode==='seller_shipping',
            'can_request_unlock'=>$locked&&!in_array($order->order_status,['canceled','returned','failed','delivered'],true),'acceptance_required'=>true];
        if(!$locked){
            $dto['line_ids']=DB::table('vendor_fulfillment_request_items')->where('request_id',$row->id)->pluck('order_detail_id')->all();
            $dto['vendor_merchandise_amount']=$row->vendor_amount; $dto['vendor_shipping_amount']=$row->shipping_amount;
            $dto['cancellation_preview']=$row->accepted_at?app(VendorCancellationPenaltyService::class)->quote($row->vendor_amount):null;
            $dto['penalty']=$row->penalty_rule_snapshot;
            $dto['unlock_receipt']=$row->unlock_snapshot;
        }
        return $dto;
    }

    public function decide(Seller $seller,int $id,string $action,?string $reason=null,?int $proofId=null,?int $expectedPenaltyRevision=null): VendorFulfillmentRequest
    {
        return $this->transition((int)$seller->id,$id,$action,$reason,'seller',(int)$seller->id,$proofId,$expectedPenaltyRevision);
    }

    public function adminTransition(int $id,string $action,string $reason,int $adminId): VendorFulfillmentRequest
    {
        if(!in_array($action,['shipping','delivered','cancel'],true))throw new DomainException('إجراء الإدارة غير متاح لهذا الجزء.');
        $sellerId=(int)VendorFulfillmentRequest::findOrFail($id)->seller_id;
        return $this->transition($sellerId,$id,$action,$reason,'admin',$adminId);
    }

    public function customerCancel(int $id, string $reason, int $customerId): VendorFulfillmentRequest
    {
        $sellerId = (int) VendorFulfillmentRequest::findOrFail($id)->seller_id;
        return $this->transition($sellerId, $id, 'cancel', $reason, 'customer', $customerId);
    }

    private function transition(int $sellerId,int $id,string $action,?string $reason,string $actor,int $actorId,?int $proofId=null,?int $expectedPenaltyRevision=null): VendorFulfillmentRequest
    {
        if(!in_array($action,['accept','reject','preparing','ready','shipping','delivered','cancel'],true))throw new DomainException('إجراء التنفيذ غير صحيح.');
        $reason=trim((string)$reason);
        if(in_array($action,['reject','cancel'],true)&&($reason===''||mb_strlen($reason)>1000))throw new DomainException('اكتب سبب القرار بما لا يتجاوز 1000 حرف.');
        return DB::transaction(function()use($sellerId,$id,$action,$reason,$actor,$actorId,$proofId,$expectedPenaltyRevision){
            if(DB::getDriverName()==='sqlite')app(SellerLedgerService::class)->lockSellerFinancialState($sellerId);
            $initial=VendorFulfillmentRequest::where('seller_id',$sellerId)->findOrFail($id);
            $order=Order::where('seller_id',$sellerId)->where('seller_is','seller')->lockForUpdate()->findOrFail($initial->order_id);
            if($actor==='customer'&&((int)$order->customer_id!==$actorId||$order->is_guest))abort(404);
            app(SellerLedgerService::class)->lockSellerFinancialState($sellerId);
            $row=VendorFulfillmentRequest::where('seller_id',$sellerId)->lockForUpdate()->findOrFail($id);
            if($actor==='seller'&&!app(SellerOrderVisibilityService::class)->canSellerView($order))abort(404);
            $target=['accept'=>'accepted','reject'=>'rejected','cancel'=>'canceled'][$action]??$action;
            if($row->status===$target){
                if(in_array($action,['reject','cancel'],true)&&$row->decision_reason!==$reason)throw new DomainException('القرار مسجل بالفعل بسبب مختلف.');
                return $row;
            }
            if(in_array($order->order_status,['canceled','returned','failed','delivered'],true))throw new DomainException('الطلب مغلق ولا يقبل هذا الإجراء.');
            if(in_array($row->status,['locked','pending_vendor'],true))throw new DomainException(VendorFulfillmentEligibilityService::MESSAGE);
            $allowed=$this->actions($row,$order);
            if($actor==='admin')$allowed=match($row->status){'ready'=>['shipping','cancel'],'shipping'=>['delivered','cancel'],'available'=>['cancel'],'accepted','preparing'=>['cancel'],default=>[]};
            if($actor==='customer')$allowed=in_array($row->status,['available','accepted','preparing','ready'],true)?['cancel']:[];
            if($actor==='seller'&&$action==='delivered'&&$row->status==='shipping'&&$order->shipping_fulfillment_mode==='seller_shipping'&&$proofId
                && \App\Models\OrderShippingProof::whereKey($proofId)->where('seller_id',$sellerId)->where('order_id',$order->id)->where('fulfillment_request_id',$id)->where('shipping_status','delivered')->exists())$allowed[]='delivered';
            if(!in_array($action,$allowed,true))throw new DomainException('راجع تفاصيل الجزء واقبله صراحة قبل تقدم التنفيذ؛ هذا الانتقال غير مسموح.');
            if($action==='delivered'){
                $lineIds=DB::table('vendor_fulfillment_request_items')->where('request_id',$id)->pluck('order_detail_id');
                foreach(OrderDetail::where('order_id',$order->id)->whereIn('id',$lineIds)->get() as $line){
                    $content=json_decode($line->getRawOriginal('product_details')?:'{}',true)?:[];
                    if(($content['product_type']??null)==='digital'&&($content['digital_product_type']??null)==='ready_after_sell'&&!$line->digital_file_after_sell)
                        throw new DomainException('أرفق ملفات المنتجات الرقمية الخاصة بهذا الجزء قبل تأكيد تسليمه.');
                }
            }
            $updates=['status'=>$target]; $rule=null;
            if($action==='accept')$updates['accepted_at']=now();
            if($action==='reject')$updates+=['rejected_at'=>now(),'decision_reason'=>$reason];
            if($action==='delivered')$updates['delivered_at']=now();
            if($action==='cancel'){
                $updates+=['canceled_at'=>now(),'decision_reason'=>$reason];
                $rule=$actor==='seller'&&$row->accepted_at?app(VendorCancellationPenaltyService::class)->quote($row->vendor_amount)
                    :['enabled'=>false,'assessed_amount'=>'0.00','base_amount'=>$row->vendor_amount,'trigger'=>'admin_or_customer_cancellation_excluded'];
                if($actor==='seller'&&$expectedPenaltyRevision!==null&&$expectedPenaltyRevision!==$rule['revision'])throw new DomainException('تغير إعداد الغرامة؛ راجع المبلغ قبل تأكيد الإلغاء.');
                if((float)$rule['assessed_amount']>0){
                    $charge=app(VendorDebtService::class)->assess($sellerId,'cancellation_penalty','fulfillment_request',(string)$id,
                        'fulfillment-cancellation-'.$id,$rule['assessed_amount'],$rule+['reason_code'=>'vendor_canceled_after_acceptance','reason'=>$reason,'accepted_at'=>$row->accepted_at->toIso8601String()]);
                    $updates['penalty_charge_id']=$charge->id;
                    $rule+=['charge_id'=>$charge->id,'wallet_collected'=>$charge->wallet_collected,'debt_assessed'=>$charge->debt_assessed];
                }
                $updates['penalty_rule_snapshot']=$rule;
            }
            $row->update($updates);
            $event=['accept'=>'accepted','reject'=>'rejected','cancel'=>'canceled'][$action]??$action;
            DB::table('vendor_fulfillment_events')->insert(['request_id'=>$id,'event_type'=>$event,'metadata'=>json_encode(['actor'=>$actor,'actor_id'=>$actorId,'reason'=>$reason,'penalty'=>$rule,'proof_id'=>$proofId],JSON_THROW_ON_ERROR),'created_at'=>now()]);
            $lineIds=DB::table('vendor_fulfillment_request_items')->where('request_id',$id)->pluck('order_detail_id');
            if(in_array($target,['rejected','canceled'],true))$this->closePortion($order,$row,$lineIds->all(),$reason);
            else OrderDetail::where('order_id',$order->id)->whereIn('id',$lineIds)->update(['delivery_status'=>match($target){'accepted'=>'confirmed','preparing','ready'=>'processing','shipping'=>'out_for_delivery','delivered'=>'delivered',default=>'confirmed'}]);
            if(Schema::hasTable('order_work_items')){
                $workStatus=match($target){'accepted'=>'accepted','rejected'=>'rejected','canceled'=>'canceled','delivered'=>'completed',default=>null};
                if($workStatus)foreach(\App\Models\OrderWorkItem::whereIn('order_detail_id',$lineIds)->get() as $workItem){
                    if($workItem->status===$workStatus)continue;
                    app(OrderWorkItemService::class)->transition($workItem,$workStatus,$actorId,$actor,
                        'vendor_fulfillment_'.$event,['request_id'=>$row->id,'reason'=>$reason]);
                }
            }
            $this->record($order,$row,$event,$reason,$actor,$actorId);
            $this->aggregate($order, $actor, $actorId);
            return $row->fresh();
        },3);
    }

    private function closePortion(Order $order,VendorFulfillmentRequest $row,array $lineIds,string $reason): void
    {
        // Restore only this portion; the established whole-order helper would restore
        // the other portion and the customer's entire purchase limit as well.
        $lines=OrderDetail::where('order_id',$order->id)->whereIn('id',$lineIds)->orderBy('product_id')->lockForUpdate()->get();
        foreach($lines as $line){
            if($line->is_stock_decreased){
                $product=Product::withoutGlobalScopes()->whereKey($line->product_id)->lockForUpdate()->first();
                if($product){
                    $variations=json_decode($product->getRawOriginal('variation')?:'[]',true)?:[];
                    foreach($variations as &$variant)if(($variant['type']??null)===$line->variant)$variant['qty']=(int)($variant['qty']??0)+(int)$line->qty;
                    unset($variant);
                    DB::table('products')->where('id',$product->id)->update(['current_stock'=>(int)$product->current_stock+(int)$line->qty,'variation'=>json_encode($variations)]);
                }
            }
            $line->update(['is_stock_decreased'=>0,'delivery_status'=>'canceled']);
        }
        DB::table('vendor_fulfillment_financial_reviews')->insertOrIgnore(['request_id'=>$row->id,'order_id'=>$order->id,'status'=>'pending',
            'line_ids'=>json_encode($lineIds),'reason'=>$reason,'created_at'=>now(),'updated_at'=>now()]);
        // Customer refunds stay in the established administration-owned workflow.
        // No customer-price amount is charged against vendor receivables here.
        app(OrderFinancialAuditService::class)->holdForReview($order,'vendor_portion_'.$row->status);
        if(Schema::hasTable('order_operational_alerts'))OrderOperationalAlert::firstOrCreate(['idempotency_key'=>'vendor-portion-review-'.$row->id],
            ['order_id'=>$order->id,'seller_id'=>$order->seller_id,'alert_type'=>'vendor_portion_financial_review','level'=>'escalation',
                'order_status'=>$order->order_status,'due_at'=>now(),'notified_at'=>now(),'metadata'=>['request_id'=>$row->id,'line_ids'=>$lineIds,'reason'=>$reason]]);
    }

    public function record(Order $order,VendorFulfillmentRequest $row,string $event,string $note,string $actor='seller',?int $actorId=null): void
    {
        if(Schema::hasTable('order_logistics_events'))app(OrderLogisticsService::class)->record($order,'vendor_fulfillment_'.$event,$actor,$actorId??$row->seller_id,$note,$order->shipping_responsible_party,
            ['event_key'=>'vendor-fulfillment-'.$row->id.'-'.$event,'metadata'=>['request_id'=>$row->id,'portion'=>$row->portion,'status'=>$row->status]]);
        if(Schema::hasColumn('notifications','customer_event_key'))DB::table('notifications')->insertOrIgnore([
            'customer_event_key'=>'vendor-fulfillment-'.$row->id.'-'.$event,'sent_by'=>$actor,'sent_to'=>'admin','seller_id'=>$row->seller_id,
            'title'=>'تحديث تنفيذ التاجر','description'=>'طلب #'.$order->id.'، جزء #'.$row->id.' — '.(self::LABELS[$row->status]??$event),
            'image'=>'','status'=>1,'notification_count'=>1,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function aggregate(Order $order, string $actor = 'system', ?int $actorId = null): void
    {
        $rows=VendorFulfillmentRequest::where('order_id',$order->id)->where('seller_id',$order->seller_id)->get();
        $active=$rows->whereNotIn('status',['rejected','canceled','reassigned']);
        $next=$order->order_status;
        if($active->isEmpty())$next='canceled';
        elseif($active->every(fn($r)=>$r->status==='delivered'))$next='delivered';
        elseif($active->every(fn($r)=>in_array($r->status,['shipping','delivered'],true)))$next='out_for_delivery';
        elseif($active->every(fn($r)=>in_array($r->status,['accepted','preparing','ready','shipping','delivered'],true)))$next='processing';
        if($next===$order->order_status)return;
        $before=$order->order_status;
        $updates=['order_status'=>$next,'commerce_flow_status'=>match($next){
            'delivered'=>\App\Support\Commerce\OrderCommerceState::COMPLETED,
            'canceled'=>\App\Support\Commerce\OrderCommerceState::CANCELLED,
            default=>\App\Support\Commerce\OrderCommerceState::FULFILLMENT_IN_PROGRESS},
            'commerce_flow_status_updated_at'=>now(),'customer_fulfillment_status'=>match($next){
                'processing'=>'preparing','out_for_delivery'=>'shipping',default=>$next},
            'shipping_workflow_status'=>match($next){'processing'=>'seller_preparing','out_for_delivery'=>'shipped',default=>$next}];
        $order->forceFill(array_intersect_key($updates,array_flip(Schema::getColumnListing('orders'))))->save();
        if(Schema::hasTable('order_logistics_events')){
            app(OrderLogisticsService::class)->recordOrderStatus($order->fresh(),$next,
                $actor==='seller'&&$next==='canceled'?'system':$actor,$actorId,$before);
            if($next==='canceled')app(OrderLogisticsService::class)->record($order->fresh(),'vendor_fulfillment_order_canceled',
                $actor,$actorId,null,$order->shipping_responsible_party,
                ['previous_order_status'=>$before,'order_status'=>$next]);
        }
        if($next==='delivered'){
            // The approved vendor entitlement is separate from customer prices/margins.
            $amount=(float)$active->sum('vendor_amount')+(float)$active->sum('shipping_amount');
            if($amount>0&&Schema::hasTable('seller_settlements')){
                app(SellerLedgerService::class)->post((int)$order->seller_id,'seller_delivered_sales','delivered-sales:'.$order->id,
                    [['bucket'=>SellerLedgerService::SALES_TOTAL,'direction'=>'credit','amount'=>$amount]],Order::class,$order->id);
                $settlement=app(SellerSettlementService::class)->holdDeliveredOrder($order->fresh(),$amount,$order->sales_settlement_due_at);
                if($settlement && DB::table('vendor_fulfillment_financial_reviews')->where('order_id',$order->id)->where('status','pending')->exists()) {
                    app(SellerSettlementService::class)->dispute($settlement,'Pending review of canceled/rejected vendor fulfillment portions.');
                }
            }
        }
        if(Schema::hasTable('order_operational_alerts')&&$next==='canceled')app(OrderFinancialAuditService::class)->holdForReview($order,'vendor_order_canceled');
        DB::afterCommit(fn()=>OrderStatusEvent::dispatch($next,'customer',$order->fresh()));
    }

    public function synchronizeExternalCancellation(Order $order): void
    {
        if(!$this->managed($order))return;
        DB::transaction(function()use($order){
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            app(SellerLedgerService::class)->lockSellerFinancialState((int)$order->seller_id);
            foreach(VendorFulfillmentRequest::where('order_id',$order->id)->whereNotIn('status',['canceled','rejected','delivered','reassigned'])->lockForUpdate()->get() as $row){
                $row->update(['status'=>'canceled','canceled_at'=>now(),'decision_reason'=>'إلغاء الطلب بواسطة الإدارة أو العميل',
                    'penalty_rule_snapshot'=>['enabled'=>false,'assessed_amount'=>'0.00','trigger'=>'admin_or_customer_cancellation_excluded']]);
                DB::table('vendor_fulfillment_events')->insertOrIgnore(['request_id'=>$row->id,'event_type'=>'canceled','metadata'=>json_encode(['actor'=>'admin_or_customer','penalty_excluded'=>true]),'created_at'=>now()]);
                $this->record($order,$row,'canceled','إلغاء الإدارة أو العميل دون غرامة تلقائية.','system',null);
            }
        },3);
    }
}
