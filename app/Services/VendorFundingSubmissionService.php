<?php
namespace App\Services;

use App\Models\{OfflinePaymentMethod, Seller, SellerBalanceDeposit};
use App\Traits\FileManagerTrait;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class VendorFundingSubmissionService
{
    use FileManagerTrait;
    public function submit(Seller $seller, Request $request): SellerBalanceDeposit
    {
        $data = $request->validate([
            'amount'=>'required|numeric|min:0.01', 'method_id'=>'required|integer|min:1', 'request_key'=>'nullable|uuid',
            'payment_proof'=>'required|image|mimes:jpg,jpeg,png,webp|max:5120', 'payment_note'=>'nullable|string|max:1000',
            'wallet_target'=>'nullable|in:operating', 'purchase_kind'=>'nullable|in:none,package,extra',
            'package_id'=>'required_if:purchase_kind,package|nullable|integer|min:1',
            'expected_purchase_amount'=>'required_if:purchase_kind,package|nullable|numeric|min:0.01',
            'subscription_id'=>'required_if:purchase_kind,extra|nullable|integer|min:1',
            'quantity'=>'required_if:purchase_kind,extra|nullable|integer|min:1|max:10000',
            'expected_unit_price'=>'required_if:purchase_kind,extra|nullable|numeric|min:0.01',
        ]);
        $method = OfflinePaymentMethod::whereKey($data['method_id'])->where('status',1)->whereIn('payment_channel',['wallet','instapay'])->firstOrFail();
        $funding = app(SellerBalanceFundingSettingsService::class)->get();
        if (!$funding['offline_enabled'] || !(getWebConfig(name:'offline_payment')['status'] ?? 0)) throw new DomainException('offline_payment_method_not_found');
        $input = app(VendorOfflinePaymentInputService::class);
        $information = $input->information($request,$method); $reference = $input->reference($request,$information);
        $amount = (float)\App\Utils\Convert::usd($data['amount']);
        app(SellerBalanceFundingSettingsService::class)->assertAmountAllowed($amount);
        $identity = ['amount'=>$amount,'method_id'=>$method->id,'transfer_reference'=>$reference,'purchase_kind'=>$data['purchase_kind'] ?? 'none',
            'package_id'=>$data['package_id'] ?? null,'subscription_id'=>$data['subscription_id'] ?? null,'quantity'=>$data['quantity'] ?? null];
        $fingerprint = hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));
        $key = 'offline-deposit:'.$seller->id.':'.($data['request_key'] ?? (string)Str::uuid());
        $proof = null; $retained = false;
        try {
            $deposit = DB::transaction(function () use ($seller,$request,$data,$method,$information,$reference,$amount,$fingerprint,$key,&$proof) {
                app(SellerLedgerService::class)->lockSellerFinancialState($seller->id);
                $existing = SellerBalanceDeposit::where('idempotency_key',$key)->first();
                if ($existing) {
                    if (($existing->metadata['request_fingerprint'] ?? null) !== $fingerprint) throw new DomainException('مفتاح الطلب مستخدم لشحن مختلف.');
                    return $existing;
                }
                $proof = $this->upload(dir:'seller-balance/payment-proof/',format:'webp',image:$request->file('payment_proof'));
                $deposit = app(SellerBalanceDepositService::class)->createOffline($seller,[
                    'amount'=>$amount,'currency_code'=>getCurrencyCode(type:'default'),'payment_method'=>'offline_payment',
                    'offline_payment_method_id'=>$method->id,'offline_information'=>$information,
                    'payment_proof'=>['image_name'=>$proof,'storage'=>config('filesystems.disks.default') ?? 'public'],
                    'payment_note'=>$data['payment_note'] ?? null,'wallet_target'=>'operating',
                    'metadata'=>['transfer_reference'=>$reference,'request_fingerprint'=>$fingerprint,'funding_version'=>6],
                ]);
                $deposit->update(['idempotency_key'=>$key]);
                app(VendorTransferReferenceService::class)->reserve($method->id,$reference,'balance_deposit',$deposit->id);
                app(VendorFundingIntentService::class)->attach($deposit,$data);
                return $deposit->fresh();
            });
            $retained = $proof && ($deposit->payment_proof['image_name'] ?? null) === $proof;
            return $deposit;
        } finally {
            if ($proof && !$retained) Storage::disk(config('filesystems.disks.default') ?? 'public')->delete('seller-balance/payment-proof/'.$proof);
        }
    }
}
