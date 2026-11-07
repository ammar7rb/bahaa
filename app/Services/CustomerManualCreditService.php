<?php
namespace App\Services;
use App\Models\{User,WalletTransaction};
use Illuminate\Support\Facades\DB;
use DomainException;
class CustomerManualCreditService
{
    public function credit(int $id,string $amount,string $reason,int $admin,string $key,string $kind,string $reference): WalletTransaction
    {
        if($kind==='adjustment')return app(CustomerAccountAdjustmentService::class)->credit($id,$amount,$reason,$admin,$key);
        if(!in_array($kind,['confirmed_payment','return_credit'],true)||!trim($reference)||!trim($reason)||$admin<=0||!is_numeric($amount)||(float)$amount<=0||(float)$amount>10000000)throw new DomainException('راجع نوع الإضافة والمبلغ والسبب ومرجع الاعتماد.');
        $units=(int)round((float)$amount*1000);
        if(!is_finite((float)$amount)||$units<1)throw new DomainException('المبلغ غير صالح.');
        $signature=hash('sha256',json_encode([$id,$units,$kind,trim($reference),trim($reason)]));
        // A single approval reference has one globally unique request key, including concurrent requests.
        $hash=hash('sha256',$kind.':'.trim($reference));$key=substr($hash,0,8).'-'.substr($hash,8,4).'-'.substr($hash,12,4).'-'.substr($hash,16,4).'-'.substr($hash,20,12);
        return DB::transaction(function()use($id,$amount,$reason,$admin,$key,$kind,$reference,$signature){
            $user=User::lockForUpdate()->findOrFail($id);
            $prior=DB::table('customer_account_actions')->where('request_key',$key)->first();
            if($prior){if($prior->signature!==$signature)throw new DomainException('مرجع الاعتماد مستخدم لعملية مختلفة.');return WalletTransaction::where('user_id',$id)->where('reference',$prior->financial_reference)->firstOrFail();}
            if($user->account_deleted_at||getWebConfig('wallet_status')!=1)throw new DomainException('الحساب مغلق أو المحفظة غير متاحة.');
            $financial='admin-'.$kind.'-'.$key;
            $allocation=$kind==='confirmed_payment'?app(CustomerAccountDebtService::class)->allocate($id,$amount,$financial):['gross'=>(float)$amount,'net'=>(float)$amount,'recovered'=>0,'remaining_debt'=>(float)DB::table('customer_account_debts')->where('customer_id',$id)->selectRaw('COALESCE(SUM(amount-paid_amount),0) as remaining')->value('remaining')];
            $before=(float)$user->wallet_balance;$user->wallet_balance=round($before+$allocation['net'],3);$user->save();
            $entry=WalletTransaction::create(['user_id'=>$id,'transaction_id'=>$financial,'reference'=>$financial,'transaction_type'=>$kind==='confirmed_payment'?'add_fund':'return_order_amount_by_admin','payment_method'=>$kind==='confirmed_payment'?'verified_external_payment':'administrative_return','credit'=>$allocation['net'],'debit'=>0,'admin_bonus'=>0,'balance'=>$user->wallet_balance]);
            DB::table('customer_account_actions')->insert(['customer_id'=>$id,'admin_id'=>$admin,'request_key'=>$key,'operation'=>$kind,'signature'=>$signature,'source'=>'admin','reason'=>$reason,'financial_reference'=>$financial,'amount'=>$amount,'details'=>json_encode($allocation+['approval_reference'=>trim($reference),'wallet_before'=>$before,'wallet_after'=>$user->wallet_balance]),'created_at'=>now(),'updated_at'=>now()]);
            $message=$allocation['recovered']>0?'تم تأكيد شحن '.$allocation['gross'].'؛ سداد المديونية '.$allocation['recovered'].'؛ المضاف للمحفظة '.$allocation['net'].'؛ الدين المتبقي '.$allocation['remaining_debt'].'.':($kind==='return_credit'?'تمت إضافة مبلغ المرتجع ':'تمت إضافة مبلغ ').$allocation['net'].' إلى رصيدكم بنجاح.';
            app(CustomerPaymentNotificationService::class)->notify($id,$financial,$message);return $entry;
        },3);
    }
}
