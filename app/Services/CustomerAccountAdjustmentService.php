<?php

namespace App\Services;

use App\Models\{User, WalletTransaction};
use DomainException;
use Illuminate\Support\Facades\DB;

class CustomerAccountAdjustmentService
{
    /** Administrative adjustment only: verified external payments use deposit review. Amount is base currency. */
    public function credit(int $customerId, string $amount, string $reason, int $adminId, string $key): WalletTransaction
    {
        $units=(int)round((float)$amount*1000);
        if (!is_numeric($amount) || !is_finite((float)$amount) || $units<=0 || $units>10000000000 || !trim($reason) || $adminId<=0) throw new DomainException('أدخل مبلغًا موجبًا وسبب الإضافة.');
        $signature=hash('sha256',json_encode([$customerId,$units,trim($reason)]));
        $entry = DB::transaction(function () use ($customerId,$amount,$reason,$adminId,$key,$units,$signature) {
            $customer=User::lockForUpdate()->findOrFail($customerId);
            $prior=DB::table('customer_account_actions')->where('request_key',$key)->first();
            if ($prior) {
                if ($prior->signature!==$signature || $prior->operation!=='wallet_adjustment' || (int)$prior->admin_id!==$adminId) throw new DomainException('مرجع الطلب مستخدم لعملية مختلفة.');
                return WalletTransaction::where('user_id',$customerId)->where('reference',$prior->financial_reference)->firstOrFail();
            }
            if ($customer->account_deleted_at) throw new DomainException('لا يمكن تعديل رصيد حساب مغلق.');
            if (getWebConfig('wallet_status')!=1) throw new DomainException('المحفظة غير متاحة حاليًا.');
            $reference='customer-admin-adjustment-'.$key;
            $allocation=app(CustomerAccountDebtService::class)->allocate($customerId,$units/1000,$reference);
            $netUnits=(int)round(((float)$allocation['net'])*1000);
            $before=(int)round((float)$customer->wallet_balance*1000);
            $customer->wallet_balance=($before+$netUnits)/1000;$customer->save();
            $entry=WalletTransaction::create(['user_id'=>$customerId,'transaction_id'=>$reference,'reference'=>$reference,
                'transaction_type'=>'add_fund_by_admin','payment_method'=>'administrative_adjustment','credit'=>$netUnits/1000,
                'debit'=>0,'admin_bonus'=>0,'balance'=>$customer->wallet_balance]);
            DB::table('customer_account_actions')->insert(['customer_id'=>$customerId,'admin_id'=>$adminId,'request_key'=>$key,
                'signature'=>$signature,'operation'=>'wallet_adjustment','source'=>'admin','reason'=>trim($reason),
                'financial_reference'=>$reference,'amount'=>$units/1000,'details'=>json_encode(['wallet_before'=>$before/1000,'wallet_after'=>(float)$customer->wallet_balance]),'created_at'=>now(),'updated_at'=>now()]);
            app(CustomerPaymentNotificationService::class)->notify($customerId,'admin-adjustment-'.$key,'تمت إضافة مبلغ '.number_format($netUnits/1000,3).' إلى رصيدكم بنجاح.');
            return $entry;
        },3);
        app(CustomerPackageAutoRenewalService::class)->renewIfDue($customerId);
        return $entry;
    }
}
