<?php

namespace App\Services;

use App\Models\{Order, Product, Seller, SellerPackageSubscription, SellerPackageTransaction, VendorAdvertisingSlot, VendorExtraAdPurchase, VendorFulfillmentRequest};
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\{Crypt, DB};

class VendorFulfillmentUnlockService
{
    /** True when the seller owns a paid, running package subscription. */
    public function hasActivePackage(int $sellerId): bool
    {
        return SellerPackageSubscription::where('seller_id', $sellerId)->where('payment_status', 'paid')
            ->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now())->exists();
    }

    /** Self-heals restricted orders of a seller who already owns a running package (e.g. before this rule existed). */
    public function releaseForSeller(int $sellerId): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_fulfillment_requests')) return;
        if (! VendorFulfillmentRequest::where('seller_id', $sellerId)->whereIn('status', ['locked', 'pending_vendor'])->exists()) return;
        $subscription = SellerPackageSubscription::where('seller_id', $sellerId)->where('payment_status', 'paid')->where('status', 'active')
            ->where('starts_at', '<=', now())->where('expires_at', '>', now())->orderByDesc('id')->first();
        if ($subscription) $this->notifyPackageConfirmed($subscription);
    }

    /**
     * Buying a package opens every restricted order of the seller at once: no
     * slot choice and no extra charge. Closed orders stay as they were.
     */
    public function notifyPackageConfirmed(SellerPackageSubscription $subscription): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_fulfillment_requests')
            || ! \Illuminate\Support\Facades\Schema::hasTable('vendor_fulfillment_events')) return;
        if (! $this->hasActivePackage((int) $subscription->seller_id)) return;
        $metadata = \Illuminate\Support\Facades\Schema::hasColumn('vendor_fulfillment_events', 'metadata');
        $hasKey = \Illuminate\Support\Facades\Schema::hasColumn('notifications', 'customer_event_key');
        $ids = VendorFulfillmentRequest::where('seller_id', $subscription->seller_id)
            ->whereIn('status', ['locked', 'pending_vendor'])->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $subscription, $metadata, $hasKey) {
                $initial = VendorFulfillmentRequest::find($id);
                if (! $initial) return;
                $order = Order::whereKey($initial->order_id)->lockForUpdate()->first();
                $row = VendorFulfillmentRequest::lockForUpdate()->find($id);
                if (! $order || ! $row || ! in_array($row->status, ['locked', 'pending_vendor'], true)) return;
                if (in_array($order->order_status, ['canceled', 'returned', 'failed', 'delivered'], true)) return;
                $snapshot = ['auto_unlock' => 'package_purchase', 'subscription_id' => $subscription->id];
                $row->update(['status' => 'available', 'unlocked_at' => now(), 'unlock_snapshot' => $snapshot]);
                DB::table('vendor_fulfillment_events')->insert(array_filter([
                    'request_id' => $id, 'event_type' => 'unlocked',
                    'metadata' => $metadata ? json_encode($snapshot) : null, 'created_at' => now(),
                ], fn ($v, $k) => $k !== 'metadata' || $v !== null, ARRAY_FILTER_USE_BOTH));
                app(VendorFulfillmentDecisionService::class)->record($order, $row, 'unlocked', 'تم فتح تفاصيل الطلب تلقائيًا بعد تفعيل الباقة.', 'system');
                if ($hasKey) DB::table('notifications')->insertOrIgnore([
                    'customer_event_key' => 'fulfillment-package-unlocked-'.$id, 'sent_by' => 'admin', 'sent_to' => 'seller', 'seller_id' => $row->seller_id,
                    'title' => 'تم فتح تفاصيل طلبك', 'description' => 'تم تفعيل باقتك، وأصبحت تفاصيل طلب #'.$row->order_id.' متاحة الآن.',
                    'image' => '', 'status' => 1, 'notification_count' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        }
    }

    private function locked(Seller $seller, int $id): array
    {
        if(DB::getDriverName()==='sqlite')app(SellerLedgerService::class)->lockSellerFinancialState((int)$seller->id);
        $initial=VendorFulfillmentRequest::where('seller_id',$seller->id)->findOrFail($id);
        $order=Order::where('seller_id',$seller->id)->where('seller_is','seller')->lockForUpdate()->findOrFail($initial->order_id);
        app(SellerLedgerService::class)->lockSellerFinancialState((int)$seller->id);
        $request=VendorFulfillmentRequest::where('seller_id',$seller->id)->lockForUpdate()->findOrFail($id);
        if (!app(SellerOrderVisibilityService::class)->canSellerView($order)) abort(404);
        if (in_array($order->order_status,['canceled','returned','failed','delivered'],true)) throw new DomainException('الطلب مغلق؛ راجع الإدارة.');
        return [$order,$request];
    }

    private function plan(Seller $seller, VendorFulfillmentRequest $request): array
    {
        app(VendorAdvertisingSlotService::class)->synchronize($seller);
        $sub=SellerPackageSubscription::where('seller_id',$seller->id)->where('payment_status','paid')->where('status','active')
            ->where('starts_at','<=',now())->where('expires_at','>',now())->orderByDesc('id')->lockForUpdate()->first();
        if (!$sub) throw new DomainException('يلزم اشتراك مدفوع وسارٍ قبل تخصيص المساحة وعرض التفاصيل.');
        app(VendorAdvertisingSlotService::class)->provision($sub);
        $products=DB::table('vendor_fulfillment_request_items')->where('request_id',$request->id)->orderBy('product_id')->distinct()->pluck('product_id')->map(fn($id)=>(int)$id)->all();
        if (!$products || Product::withoutGlobalScopes()->where('user_id',$seller->id)->where('added_by','seller')->whereIn('id',$products)->count()!==count($products)) {
            throw new DomainException('بعض المنتجات غير متاحة للتخصيص؛ راجع الإدارة.');
        }
        $slots=VendorAdvertisingSlot::where('seller_package_subscription_id',$sub->id)->where('seller_id',$seller->id)
            ->where('starts_at','<=',now())->where('expires_at','>',now())->orderBy('position')->lockForUpdate()->get();
        $assigned=$slots->whereIn('product_id',$products)->keyBy('product_id');
        $needed=array_values(array_filter($products,fn($id)=>!isset($assigned[$id])));
        $free=$slots->filter(fn($slot)=>$slot->product_id===null && !app(VendorSlotReservationService::class)->isReserved($slot))->values();
        $extra=max(0,count($needed)-$free->count()); $price='0.00'; $settings=app(VendorExtraAdService::class)->settings();
        if ($extra) {
            if (!$settings['enabled'] || !is_numeric($settings['unit_price']) || (float)$settings['unit_price']<=0) throw new DomainException('المساحات مشغولة، ولم تضبط الإدارة سعر المساحة الإضافية بعد. تواصل مع الإدارة.');
            $reserved=(int)VendorExtraAdPurchase::where('seller_package_subscription_id',$sub->id)->whereIn('status',['paid','pending_review','allocated_with_debt'])->sum('quantity');
            if ($reserved+$extra>$settings['max_slots']) throw new DomainException('بلغ الاشتراك الحد الأقصى للمساحات الإضافية؛ راجع الإدارة.');
            $price=(string)BigDecimal::of((string)$settings['unit_price'])->toScale(2,RoundingMode::HalfUp);
        }
        $total=BigDecimal::of($price)->multipliedBy($extra)->toScale(2);
        $balance=BigDecimal::of((string)max(0,app(SellerLedgerService::class)->summary($seller->id,initializeLegacy:false)['operating'] ?? 0))->toScale(8,RoundingMode::HalfUp);
        $paid=$balance->isLessThan($total)?$balance:$total;
        $data=['request_id'=>$request->id,'seller_id'=>$seller->id,'subscription_id'=>$sub->id,'products'=>$products,
            'extra_count'=>$extra,'unit_price'=>$price,'total'=>(string)$total,'wallet_debit'=>(string)$paid,'debt'=>(string)$total->minus($paid),
            'expires_at'=>$sub->expires_at->toIso8601String(),'max_slots'=>$settings['max_slots']];
        return compact('sub','products','slots','assigned','needed','free','extra','data');
    }

    public function quote(Seller $seller, int $id): array
    {
        return DB::transaction(function() use($seller,$id){
            [$order,$request]=$this->locked($seller,$id);
            if (!in_array($request->status,['locked','pending_vendor'],true)) return ['already_unlocked'=>true,'request_id'=>$id];
            $plan=$this->plan($seller,$request); $data=$plan['data'];
            return array_intersect_key($data,array_flip(['request_id','extra_count','unit_price','total','wallet_debit','debt','expires_at']))+[
                'available_slots'=>min(count($plan['needed']),$plan['free']->count()),'currency'=>'EGP',
                'message'=>$plan['extra']?'المساحات المتاحة مشغولة. ستضاف مساحة إعلانية برسوم؛ يخصم المتاح من رصيد المدفوعات ويسجل الباقي دينًا يسدد من الإيداع التالي.':'سيستخدم تخصيص المنتج المساحة المتاحة دون رسوم إضافية.',
                'token'=>Crypt::encryptString(json_encode(['data'=>$data,'valid_until'=>now()->addMinutes(10)->timestamp],JSON_THROW_ON_ERROR)),
                'acceptance_required'=>true,
            ];
        },3);
    }

    public function unlock(Seller $seller, int $id, string $token): VendorFulfillmentRequest
    {
        try {$quote=json_decode(Crypt::decryptString($token),true,512,JSON_THROW_ON_ERROR);} catch(\Throwable){throw new DomainException('عرض التخصيص غير صحيح؛ أعد المراجعة.');}
        if (($quote['data']['request_id']??null)!==$id || ($quote['data']['seller_id']??null)!==$seller->id) abort(404);
        return DB::transaction(function()use($seller,$id,$quote){
            [$order,$request]=$this->locked($seller,$id);
            if (!in_array($request->status,['locked','pending_vendor'],true)) return $request;
            if (($quote['valid_until']??0)<now()->timestamp) throw new DomainException('انتهت معاينة التخصيص؛ راجع السعر مجددًا.');
            $plan=$this->plan($seller,$request);
            if ($quote['data']!==$plan['data']) throw new DomainException('تغيرت المساحات أو التكلفة أو الرصيد. راجع التوزيع الحالي قبل التأكيد.');
            $charge=null; $purchase=null; $sub=$plan['sub'];
            if ($plan['extra']) {
                $purchase=VendorExtraAdPurchase::create([
                    'admin_reference'=>'VAD-FR-'.$request->id,'seller_id'=>$seller->id,'seller_package_subscription_id'=>$sub->id,
                    'request_key'=>'fulfillment-unlock-'.$request->id,'quantity'=>$plan['extra'],'unit_price'=>$plan['data']['unit_price'],
                    'total_price'=>$plan['data']['total'],'currency'=>'EGP','payment_method'=>'operating_balance',
                    'status'=>'allocated_with_debt','expires_at'=>$sub->expires_at,'terms'=>['duration_policy'=>'subscription_expiry','fulfillment_request_id'=>$id,'price_is_final'=>true],
                ]);
                $charge=app(VendorDebtService::class)->assess($seller->id,'additional_slot','extra_ad_purchase',(string)$purchase->id,
                    'fulfillment-extra-'.$id,$plan['data']['total'],['unit_price'=>$plan['data']['unit_price'],'quantity'=>$plan['extra'],
                        'duration_policy'=>'subscription_expiry','expires_at'=>$plan['data']['expires_at'],'subscription_id'=>$sub->id,'request_id'=>$id]);
                $purchase->update(['status'=>(float)$charge->debt_assessed>0?'allocated_with_debt':'paid','paid_at'=>(float)$charge->debt_assessed>0?null:now(),
                    'payment_reference'=>'vendor-debt-charge-'.$charge->id,'terms'=>$purchase->terms+['debt_charge_id'=>$charge->id,'wallet_collected'=>$charge->wallet_collected,'debt_assessed'=>$charge->debt_assessed]]);
                $position=(int)$plan['slots']->max('position');
                for($i=1;$i<=$plan['extra'];$i++)$plan['free']->push(VendorAdvertisingSlot::create([
                    'seller_id'=>$seller->id,'seller_package_subscription_id'=>$sub->id,'position'=>$position+$i,
                    'extra_ad_purchase_id'=>$purchase->id,'starts_at'=>now(),'expires_at'=>$sub->expires_at,
                ]));
                SellerPackageTransaction::create(['seller_id'=>$seller->id,'seller_package_subscription_id'=>$sub->id,'seller_package_id'=>$sub->seller_package_id,
                    'transaction_type'=>'extra_ad_purchase','quota_type'=>'product','credit'=>$plan['extra'],'debit'=>0,
                    'balance_after'=>$plan['slots']->count()+$plan['extra'],'paid_amount'=>$charge->wallet_collected,
                    'transaction_id'=>'fulfillment-extra-'.$id,'reference'=>$purchase->admin_reference,
                    'metadata'=>['extra_ad_purchase_id'=>$purchase->id,'debt_charge_id'=>$charge->id,'assessed_amount'=>$charge->amount,'debt_assessed'=>$charge->debt_assessed,'currency'=>'EGP']]);
            }
            $freeIndex=0;
            foreach($plan['products'] as $productId){
                $slot=$plan['assigned'][$productId]??null;
                if (!$slot) $slot=app(VendorAdvertisingSlotService::class)->assign($seller,$plan['free'][$freeIndex++]->id,$productId);
                DB::table('vendor_fulfillment_request_items')->where('request_id',$id)->where('product_id',$productId)->update(['advertising_slot_id'=>$slot->id]);
            }
            $snapshot=$plan['data']; unset($snapshot['products']);
            $snapshot+=['extra_ad_purchase_id'=>$purchase?->id,'debt_charge_id'=>$charge?->id];
            $request->update(['status'=>'available','unlocked_at'=>now(),'unlock_snapshot'=>$snapshot]);
            DB::table('vendor_fulfillment_events')->insert(['request_id'=>$id,'event_type'=>'unlocked','metadata'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'created_at'=>now()]);
            app(VendorFulfillmentDecisionService::class)->record($order,$request,'unlocked','تم تخصيص المساحات وإتاحة التفاصيل؛ لم يقبل التاجر الطلب.');
            return $request->fresh();
        },3);
    }
}
