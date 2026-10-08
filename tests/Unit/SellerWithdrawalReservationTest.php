<?php
namespace Tests\Unit;
use App\Models\Seller;
use App\Models\SellerWallet;
use App\Models\VendorWithdrawMethodInfo;
use App\Models\WithdrawalMethod;
use App\Models\WithdrawRequest;
use App\Services\SellerLedgerService;
use App\Services\SellerWithdrawalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class SellerWithdrawalReservationTest extends SellerLedgerServiceTest {
 protected function setUp():void {
  parent::setUp();
  Schema::create('withdrawal_methods',function(Blueprint $t){$t->id();$t->string('method_name');$t->json('method_fields')->nullable();$t->boolean('is_active')->default(true);$t->timestamps();});
  Schema::create('vendor_withdraw_method_infos',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->unsignedBigInteger('withdraw_method_id');$t->string('method_name');$t->string('method_type');$t->string('approval_status');$t->json('method_info');$t->boolean('is_active');$t->boolean('is_default')->default(false);$t->timestamps();});
  Schema::create('withdraw_requests',function(Blueprint $t){$t->id();$t->unsignedBigInteger('seller_id');$t->unsignedBigInteger('delivery_man_id')->nullable();$t->decimal('amount',40,20);$t->integer('approved')->default(0);$t->string('request_method_type');$t->unsignedBigInteger('withdrawal_method_id');$t->unsignedBigInteger('vendor_withdraw_method_info_id');$t->json('withdrawal_method_fields');$t->string('request_key')->nullable();$t->text('transaction_note')->nullable();$t->unique(['seller_id','request_key']);$t->timestamps();});
 }
 protected function tearDown():void {foreach(['withdraw_requests','vendor_withdraw_method_infos','withdrawal_methods']as $t)Schema::dropIfExists($t);parent::tearDown();}
 private function method(int $seller):VendorWithdrawMethodInfo { $definition=WithdrawalMethod::create(['method_name'=>'InstaPay']);return VendorWithdrawMethodInfo::create(['user_id'=>$seller,'withdraw_method_id'=>$definition->id,'method_name'=>'Saved recipient','method_type'=>'instapay','approval_status'=>'approved','is_active'=>true,'method_info'=>['recipient_name'=>'Original','instapay_address'=>'original@instapay']]); }
 public function test_pending_requests_reserve_funds_and_snapshot_recipient():void {
  $seller=Seller::create();SellerWallet::create(['seller_id'=>$seller->id,'total_earning'=>100]);$method=$this->method($seller->id);$service=app(SellerWithdrawalService::class);
  $request=$service->submit($seller->id,$method->id,70,'retry-one');$this->assertSame($request->id,$service->submit($seller->id,$method->id,70,'retry-one')->id);$this->assertSame(1,WithdrawRequest::count());
  $method->update(['method_info'=>['recipient_name'=>'Changed']]);$this->assertSame('Original',$request->fresh()->withdrawal_method_fields['recipient_name']);
  $summary=app(SellerLedgerService::class)->summary($seller->id);$this->assertSame(30.0,$summary['available']);$this->assertSame(70.0,$summary['pending_withdraw']);
  try {$service->submit($seller->id,$method->id,31,'retry-two');$this->fail('Reserved funds cannot be withdrawn again');}catch(\InvalidArgumentException $e){$this->assertSame(1,WithdrawRequest::count());}
  $service->finish($request->id,3,$seller->id);$service->finish($request->id,3,$seller->id);$this->assertSame(100.0,app(SellerLedgerService::class)->summary($seller->id)['available']);$this->assertSame(3,$request->fresh()->approved);
 }
 public function test_approval_is_once_and_rejection_of_processed_request_cannot_refund():void {
  $seller=Seller::create();SellerWallet::create(['seller_id'=>$seller->id,'total_earning'=>100]);$method=$this->method($seller->id);$service=app(SellerWithdrawalService::class);$request=$service->submit($seller->id,$method->id,40,'approval');$service->finish($request->id,1);$service->finish($request->id,1);$this->assertSame(40.0,app(SellerLedgerService::class)->summary($seller->id)['withdrawn']);$this->expectException(\DomainException::class);$service->finish($request->id,2);
 }
 public function test_method_of_another_seller_cannot_be_used():void {$a=Seller::create();$b=Seller::create();$method=$this->method($a->id);$this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);app(SellerWithdrawalService::class)->submit($b->id,$method->id,1);}
 public function test_held_and_payments_balances_are_not_withdrawable():void {$seller=Seller::create();$method=$this->method($seller->id);$ledger=app(SellerLedgerService::class);$ledger->post($seller->id,'test-funding','restricted-funds',[['bucket'=>'pending','direction'=>'credit','amount'=>100],['bucket'=>'operating','direction'=>'credit','amount'=>100],['bucket'=>'order_insurance_credit','direction'=>'credit','amount'=>100]]);$this->expectException(\InvalidArgumentException::class);app(SellerWithdrawalService::class)->submit($seller->id,$method->id,1);}
 public function test_wallet_history_is_owned_and_omits_private_order_references():void {
  $seller=Seller::create();$other=Seller::create();$ledger=app(SellerLedgerService::class);
  $ledger->post($seller->id,'order-payment','987654',[['bucket'=>'operating','direction'=>'credit','amount'=>50]],'order',987654,['order_id'=>987654,'customer_name'=>'Private customer']);
  $ledger->post($other->id,'funding','other',[['bucket'=>'operating','direction'=>'credit','amount'=>900]]);
  $rows=app(\App\Services\VendorFinancialWorkspaceService::class)->transactions($seller->id)->items();
  $this->assertCount(1,$rows);$this->assertSame(50.0,(float)$rows[0]->amount);
  $this->assertSame(['id','bucket','direction','amount','created_at','transaction_number'],array_keys($rows[0]->toArray()));
  $this->assertStringNotContainsString('987654',json_encode($rows));
  $this->assertStringNotContainsString('Private customer',json_encode($rows));
 }
 public function test_wallet_destination_history_separates_payments_and_security_funds():void {
  $seller=Seller::create();$ledger=app(SellerLedgerService::class);
  $ledger->post($seller->id,'funding','bucket-filter',[['bucket'=>'operating','direction'=>'credit','amount'=>50],['bucket'=>'order_insurance_credit','direction'=>'credit','amount'=>25]]);
  $service=app(\App\Services\VendorFinancialWorkspaceService::class);
  $payments=$service->transactions($seller->id,1,SellerLedgerService::OPERATING)->items();
  $security=$service->transactions($seller->id,1,SellerLedgerService::ORDER_INSURANCE_CREDIT)->items();
  $this->assertCount(1,$payments);$this->assertCount(1,$security);
  $this->assertSame(50.0,(float)$payments[0]->amount);$this->assertSame(25.0,(float)$security[0]->amount);
 }
}
