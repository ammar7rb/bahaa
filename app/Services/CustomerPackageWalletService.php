<?php
namespace App\Services;

use App\Models\{User,CustomerMonthlySubscription,CustomerSubscriptionPayment,WalletTransaction};
use DomainException;
use Illuminate\Support\Facades\{DB,Schema};

class CustomerPackageWalletService
{
    public function transact(int $customerId,array $input): CustomerSubscriptionPayment
    {
        if(!Schema::hasColumn('customer_subscription_payments','wallet_policy_version'))throw new DomainException('تحديث معاملات الباقات غير جاهز بعد.');
        $signature=hash('sha256',json_encode([
            (int)($input['package_id']??0),(int)($input['offer_id']??0),(int)($input['quantity']??1),
            $input['operation'],(int)($input['expected_subscription_id']??0),(int)$input['expected_revision'],
            $input['quote_token'],$this->units($input['expected_amount']),
        ]));
        return DB::transaction(function()use($customerId,$input,$signature){
            $customer=User::lockForUpdate()->findOrFail($customerId);
            $prior=CustomerSubscriptionPayment::where('customer_id',$customerId)->where('request_key',$input['request_key'])->first();
            if($prior){
                if((int)$prior->wallet_policy_version!==2||$prior->operation_signature!==$signature)throw new DomainException('مرجع العملية مستخدم لطلب مختلف. حدّث الصفحة.');
                if($prior->status!=='approved')throw new DomainException('حالة العملية تحتاج مراجعة الدعم.');
                return $prior;
            }
            if(!$customer->is_active||$customer->account_deleted_at)throw new DomainException('الحساب موقوف. تواصل مع الدعم.');
            if(getWebConfig('wallet_status')!=1)throw new DomainException('محفظة المنصة غير متاحة حاليًا.');
            $active=app(CustomerMonthlyAllowanceService::class)->active($customerId);
            if($active)$active=CustomerMonthlySubscription::lockForUpdate()->findOrFail($active->id);
            if((int)($active?->id??0)!==(int)($input['expected_subscription_id']??0)||(int)($active?->limit_revision??0)!==(int)$input['expected_revision'])throw new DomainException('تغيرت حالة الباقة منذ عرضها. حدّث الصفحة قبل الدفع.');
            $terms=$this->terms($input,$active);
            if($active&&$active->expires_at->lte(now()))throw new DomainException('انتهت الباقة أثناء تجهيز العملية. حدّث الصفحة.');
            if($this->units($terms['amount'])!==$this->units($input['expected_amount']))throw new DomainException('تغير السعر المعروض. حدّث الصفحة قبل الدفع.');
            $debit=$this->units(\App\Utils\Convert::usdPaymentModule($terms['amount'],'EGP'));
            $balance=$this->units($customer->wallet_balance);
            if($debit<=0||$balance<$debit)throw new DomainException('رصيد محفظة المنصة غير كافٍ. اشحن المحفظة ثم أعد المحاولة.');
            $before=$active?max(0,(float)$active->granted_allowance+(float)$active->additional_allowance-(float)$active->committed_allowance):0;
            $payment=CustomerSubscriptionPayment::create([
                'customer_id'=>$customerId,'open_customer_id'=>null,'request_key'=>$input['request_key'],
                'wallet_policy_version'=>2,'operation_signature'=>$signature,'status'=>'draft',
                'package_id'=>$terms['package_id'],'offer_id'=>$terms['offer_id'],'offer_quantity'=>$input['quantity']??1,
                'target_subscription_id'=>$active?->id,'purchase_kind'=>$terms['kind'],'terms_name'=>$terms['name'],
                'quoted_amount'=>$terms['amount'],'currency_code'=>'EGP','wallet_debit_amount'=>$debit/1000,
                'granted_allowance'=>$terms['allowance'],'terms_snapshot'=>$terms,'allowance_before'=>$before,
            ]);
            $sub=$this->apply($customerId,$payment,$active);
            $customer->wallet_balance=($balance-$debit)/1000;$customer->save();
            $reference='customer-package-'.$payment->purchase_kind.'-'.$payment->id;
            WalletTransaction::create(['user_id'=>$customerId,'transaction_id'=>$reference,'reference'=>$reference,
                'transaction_type'=>'monthly_subscription','payment_method'=>'wallet_balance','credit'=>0,'debit'=>$debit/1000,
                'admin_bonus'=>0,'balance'=>$customer->wallet_balance,'customer_subscription_payment_id'=>$payment->id]);
            $after=max(0,(float)$sub->granted_allowance+(float)$sub->additional_allowance-(float)$sub->committed_allowance);
            $payment->update(['status'=>'approved','payment_method'=>'wallet_balance','payment_reference'=>$reference,
                'submitted_at'=>now(),'approved_at'=>now(),'reviewed_at'=>now(),'review_note'=>'خصم وتفعيل تلقائي من محفظة المنصة',
                'target_subscription_id'=>$sub->id,'allowance_after'=>$after,'wallet_balance_after'=>$customer->wallet_balance,
                'subscription_expires_at'=>$sub->expires_at]);
            app(CustomerPurchaseEligibilityService::class)->reevaluateCustomer($customerId);
            app(CustomerPaymentNotificationService::class)->notify($customerId,'package-wallet-'.$payment->id,'تمت معاملة '.$payment->terms_name.' من محفظتك. تنتهي الباقة '.$sub->expires_at->format('Y/m/d').'.');
            return $payment->refresh();
        },3);
    }

    private function terms(array $input,?CustomerMonthlySubscription $active): array
    {
        $catalog=app(CustomerPackageCatalogService::class);
        $quantity=(int)($input['quantity']??1);
        if(!empty($input['offer_id'])){
            if(!$active||!empty($input['package_id'])||$input['operation']!=='purchase'||$quantity<1||$quantity>5)throw new DomainException('اختر زيادة صالحة لباقة نشطة.');
            $offer=DB::table('customer_credit_offers')->where('active',1)->lockForUpdate()->find($input['offer_id']);
            if(!$offer||!hash_equals($catalog->token($offer,'offer'),$input['quote_token']))throw new DomainException('تغير عرض زيادة الحد. حدّث الصفحة.');
            return ['kind'=>'increase','amount'=>round((float)$offer->price*$quantity,3),'allowance'=>round((float)$offer->allowance*$quantity,3),'name'=>$offer->name.' × '.$quantity,'package_id'=>$active->package_id,'offer_id'=>$offer->id];
        }
        if($quantity!==1)throw new DomainException('المضاعف متاح لعروض زيادة الحد فقط.');
        $package=$catalog->available()->lockForUpdate()->find($input['package_id']??0);
        if(!$package||!hash_equals($catalog->token($package,'package'),$input['quote_token']))throw new DomainException('تغيرت الباقة أو رسومها. حدّث الصفحة.');
        if($input['operation']==='renew_limit'){
            if(!$active||$active->package_id!==$package->id)throw new DomainException('تجديد الحد متاح للباقة الحالية قبل انتهائها فقط.');
            if(!$package->renewal_price||$package->renewal_price<=0)throw new DomainException('لم تحدد الإدارة رسوم تجديد الحد بعد.');
            $available=max(0,round((float)$active->granted_allowance+(float)$active->additional_allowance-(float)$active->committed_allowance,3));
            $restored=round((float)$active->granted_allowance-$available,3);
            if($restored<=0)throw new DomainException('حد الباقة متاح بالكامل بالفعل؛ لا توجد حاجة لخصم رسوم تجديد.');
            return ['kind'=>'renew_limit','amount'=>$package->renewal_price,'allowance'=>$restored,'base_limit'=>(float)$active->granted_allowance,'name'=>'تجديد حد '.$active->package_name,'package_id'=>$package->id,'offer_id'=>null];
        }
        if($input['operation']!=='purchase')throw new DomainException('معاملة باقة غير صالحة.');
        if($active&&($active->package_id===$package->id||(float)$package->purchase_limit<=(float)$active->granted_allowance))throw new DomainException('اختر باقة أعلى للترقية، أو إجراء تجديد الحد لباقة الحالية.');
        return ['kind'=>$active?'upgrade':'activation','amount'=>$package->package_price,'allowance'=>$package->purchase_limit,'name'=>$package->name,'package_id'=>$package->id,'offer_id'=>null];
    }

    private function apply(int $customerId,CustomerSubscriptionPayment $payment,?CustomerMonthlySubscription $active): CustomerMonthlySubscription
    {
        if(in_array($payment->purchase_kind,['increase','renew_limit'],true)){
            $active->additional_allowance=(float)$active->additional_allowance+(float)$payment->granted_allowance;
            $active->limit_revision++;$active->save();return $active;
        }
        $start=now()->startOfSecond();
        $end=$active ? $active->expires_at->copy() : $start->copy()->utc()->addSeconds(30*86400)->setTimezone($start->timezone);
        // Upgrade replaces the entitlement; its end remains the original monthly expiry.
        if($active)$active->update(['expires_at'=>$start]);
        return CustomerMonthlySubscription::create(['customer_id'=>$customerId,'payment_id'=>$payment->id,
            'package_id'=>$payment->package_id,'package_name'=>$payment->terms_name,'package_price'=>$payment->quoted_amount,
            'granted_allowance'=>$payment->granted_allowance,'starts_at'=>$start,
            'expires_at'=>$end,'limit_revision'=>0]);
    }

    private function units(mixed $amount): int
    {
        if(!is_numeric($amount)||!is_finite((float)$amount)||(float)$amount<0||(float)$amount>PHP_INT_MAX/1000)throw new DomainException('قيمة مالية غير صالحة.');
        return (int)round((float)$amount*1000);
    }
}
