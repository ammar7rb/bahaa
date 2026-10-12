<?php
namespace Tests\Unit;
use App\Models\Seller;
use App\Models\SellerPackage;
use App\Models\SellerPackageSubscription;
use App\Services\SellerLedgerService;
use App\Services\SellerPackagePurchaseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class SellerPackagePaymentsRetryTest extends SellerPackagePurchaseServiceTest {
 protected function setUp():void {parent::setUp();Schema::create('currencies',function(Blueprint $t){$t->id();$t->string('code');$t->decimal('exchange_rate',12,4)->default(1);});\Illuminate\Support\Facades\DB::table('currencies')->insert(['id'=>1,'code'=>'EGP']);\Illuminate\Support\Facades\DB::table('business_settings')->insert(['type'=>'system_default_currency','value'=>'1']);Schema::create('seller_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->decimal('total_earning', 40, 20)->default(0);
            $table->decimal('pending_withdraw', 40, 20)->default(0);
            $table->decimal('withdrawn', 40, 20)->default(0);
            $table->decimal('commission_given', 40, 20)->default(0);
            $table->decimal('delivery_charge_earned', 40, 20)->default(0);
            $table->decimal('collected_cash', 40, 20)->default(0);
            $table->decimal('total_tax_collected', 40, 20)->default(0);
            $table->timestamps();
        });
Schema::create('seller_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('bucket', 32);
            $table->string('direction', 8);
            $table->decimal('amount', 40, 20);
            $table->string('event_type', 64);
            $table->string('group_key', 191);
            $table->char('idempotency_key', 64)->unique();
            $table->string('reference_type', 128)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('metadata')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('entry_hash', 64);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });}
 protected function tearDown():void {Schema::dropIfExists('currencies');Schema::dropIfExists('seller_ledger_entries');Schema::dropIfExists('seller_wallets');parent::tearDown();}
 private function plan():SellerPackage {return SellerPackage::create(['name'=>'Retry plan','slug'=>'retry-plan','package_price'=>250,'product_limit'=>0,'product_duration_days'=>30,'search_promotion_limit'=>2,'search_promotion_duration_days'=>7,'homepage_promotion_limit'=>1,'homepage_promotion_duration_days'=>7,'package_validity_days'=>60,'status'=>true]);}
 public function test_lost_response_retry_does_not_purchase_another_package_or_change_price():void {
  $ledger=app(SellerLedgerService::class);$ledger->post(1,'funding','funding-test',[['bucket'=>'operating','direction'=>'credit','amount'=>500]]);
  $plan=$this->plan();$service=app(SellerPackagePurchaseService::class);$first=$service->purchaseWithPaymentsBalance(Seller::findOrFail(1),$plan,'purchase-key');$plan->update(['package_price'=>999]);$again=$service->purchaseWithPaymentsBalance(Seller::findOrFail(1),$plan,'purchase-key');
  $this->assertSame($first['subscription']->id,$again['subscription']->id);$this->assertSame(1,SellerPackageSubscription::count());$this->assertSame(250.0,$ledger->summary(1)['operating']);$this->assertSame(250.0,(float)$again['subscription']->paid_package_price);
 }
 public function test_restricted_sales_and_security_credit_cannot_buy_packages():void {
  $ledger=app(SellerLedgerService::class);$ledger->post(1,'funding','other-funds',[['bucket'=>'available','direction'=>'credit','amount'=>500],['bucket'=>'order_insurance_credit','direction'=>'credit','amount'=>500]]);
  try {app(SellerPackagePurchaseService::class)->purchaseWithPaymentsBalance(Seller::findOrFail(1),$this->plan(),'no-payments-credit');$this->fail('Only payments credit can buy a package');}catch(\DomainException $e){$this->assertSame('seller_operating_balance_insufficient',$e->getMessage());}
  $this->assertSame(0,SellerPackageSubscription::count());$this->assertSame(500.0,$ledger->summary(1)['available']);$this->assertSame(500.0,$ledger->summary(1)['order_insurance_credit']);
 }
}
