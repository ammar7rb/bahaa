<?php
namespace App\Services;

use App\Models\{WalletTransaction, CustomerSubscriptionPayment, CustomerBalanceDeposit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};

class CustomerWalletStatementService
{
    public const LABELS = ['deposit'=>'إيداع','purchase'=>'شراء منتجات','subscription'=>'اشتراك في باقة',
        'upgrade'=>'ترقية الباقة','renew_limit'=>'تجديد الحد الائتماني','increase'=>'زيادة الحد',
        'refund'=>'استرداد مشتريات','adjustment'=>'تسوية الرصيد','loyalty'=>'تحويل نقاط'];

    public function query(int $customerId, Request $request) {
        $filters=$request->validate(['entry_type'=>'nullable|in:'.implode(',',array_keys(self::LABELS)),
            'from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($request->filled('from')?'|after_or_equal:from':'')]);
        $q=WalletTransaction::where('user_id',$customerId);
        if(!empty($filters['from']))$q->where('created_at','>=',$filters['from'].' 00:00:00');
        if(!empty($filters['to']))$q->where('created_at','<=',$filters['to'].' 23:59:59');
        $type=$filters['entry_type']??null;
        if($type) {
            if(in_array($type,['subscription','upgrade','renew_limit','increase'],true)) {
                $kinds=$type==='subscription'?['activation','subscription']:[$type];
                $q->where('transaction_type','monthly_subscription')->where(function($query)use($customerId,$kinds,$type){
                    $query->whereIn('customer_subscription_payment_id',CustomerSubscriptionPayment::where('customer_id',$customerId)->whereIn('purchase_kind',$kinds)->select('id'));
                    if($type==='subscription')$query->orWhereNull('customer_subscription_payment_id');
                });
            } elseif($type==='adjustment') {
                $q->whereNotIn('transaction_type',['monthly_subscription','add_fund','add_fund_by_admin','order_place','due_payment_for_order','order_refund','return_order_amount_by_admin','loyalty_point']);
            } else {
                $q->whereIn('transaction_type',match($type){
                    'deposit'=>['add_fund','add_fund_by_admin'], 'purchase'=>['order_place','due_payment_for_order'],
                    'refund'=>['order_refund','return_order_amount_by_admin'], 'loyalty'=>['loyalty_point'], default=>['adjustment']});
            }
        }
        return $q->orderByDesc('id');
    }

    /** Decorate one page in batches. Never infer an order from a timestamp or amount. */
    public function decorate($entries): array {
        $entries=collect($entries);
        $paymentIds=$entries->pluck('customer_subscription_payment_id')->filter()->unique();
        $payments=Schema::hasTable('customer_subscription_payments') ? CustomerSubscriptionPayment::whereIn('id',$paymentIds)->get()->keyBy('id') : collect();
        $depositIds=$entries->map(fn($e)=>preg_match('/^customer-offline-deposit-(\d+)$/',(string)$e->reference,$m)?(int)$m[1]:null)->filter();
        $deposits=Schema::hasTable('customer_balance_deposits') ? CustomerBalanceDeposit::whereIn('id',$depositIds)->get()->keyBy('id') : collect();
        $orderIds=$entries->flatMap(fn($e)=>$e->order_ids??[])->unique();
        $orders=Schema::hasTable('orders') ? DB::table('orders')->whereIn('id',$orderIds)->get()->keyBy('id') : collect();
        $invoices=Schema::hasTable('post_purchase_invoices') ? DB::table('post_purchase_invoices')->whereIn('order_id',$orderIds)->get()->keyBy('order_id') : collect();
        return $entries->map(function($entry)use($payments,$deposits,$orders,$invoices) {
            $payment=$payments->get($entry->customer_subscription_payment_id);
            if($payment && (int)$payment->customer_id!==(int)$entry->user_id)$payment=null;
            preg_match('/^customer-offline-deposit-(\d+)$/',(string)$entry->reference,$m);
            $deposit=$deposits->get((int)($m[1]??0));
            if($deposit && (int)$deposit->customer_id!==(int)$entry->user_id)$deposit=null;
            $kind=$payment ? (in_array($payment->purchase_kind,['activation','subscription',null],true)?'subscription':$payment->purchase_kind) : match($entry->transaction_type) {
                'monthly_subscription'=>'subscription','add_fund','add_fund_by_admin'=>'deposit',
                'order_place','due_payment_for_order'=>'purchase','order_refund','return_order_amount_by_admin'=>'refund','loyalty_point'=>'loyalty',default=>'adjustment'};
            $links=collect($entry->order_ids??[])->map(function($id)use($entry,$orders,$invoices){
                $order=$orders->get($id);if(!$order || (int)$order->customer_id!==(int)$entry->user_id)return null;
                $invoice=$invoices->get($id);if($invoice && (int)$invoice->customer_id!==(int)$entry->user_id)$invoice=null;
                return ['order_id'=>(int)$id,'invoice_id'=>$invoice?->id,'invoice_number'=>$invoice?->invoice_number];
            })->filter()->values()->all();
            $method=$deposit?->method_name ?: ($entry->payment_method ?: ($kind==='purchase' || $payment?'محفظة المنصة':null));
            $method=['wallet_balance'=>'محفظة المنصة','pay_by_wallet'=>'محفظة المنصة','purchase_wallet'=>'محفظة المنصة','offline'=>'تحويل خارجي'][$method]??$method;
            return array_merge($entry->toArray(),['entry_type'=>$kind,'entry_label'=>self::LABELS[$kind]??'حركة رصيد',
                'admin_bonus'=>(float)$entry->admin_bonus,
                'credited_amount'=>(float)$entry->credit+(float)$entry->admin_bonus,
                'payment_method'=>$method,
                'reference_label'=>$payment?->payment_reference ?: ($deposit?->transaction_number ?: ($entry->reference ?: $entry->transaction_id)),
                'package_name'=>$payment?->terms_name,'orders'=>$links,
                'customer_note'=>$entry->customer_note,'note_updated_at'=>$entry->note_updated_at?->toIso8601String()]);
        })->all();
    }
}
