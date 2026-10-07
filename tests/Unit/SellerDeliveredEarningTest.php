<?php
namespace Tests\Unit;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerWallet;
use App\Services\SellerDeliveredEarningService;
use App\Services\SellerLedgerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class SellerDeliveredEarningTest extends SellerSettlementServiceTest {
 protected function setUp():void {parent::setUp();Schema::create('order_transactions',function(Blueprint $t){$t->id();$t->unsignedBigInteger('order_id');$t->string('status');});}
 protected function tearDown():void {Schema::dropIfExists('order_transactions');parent::tearDown();}
 public function test_first_delivered_earning_is_held_without_legacy_opening_credit_or_duplicate():void {
  $seller=Seller::create();$order=Order::create(['seller_id'=>$seller->id,'seller_is'=>'seller']);$calls=0;
  $callback=function()use($seller,&$calls){$calls++;SellerWallet::create(['seller_id'=>$seller->id,'total_earning'=>100]);return true;};
  $service=app(SellerDeliveredEarningService::class);$service->record($order,$callback);$service->record($order,$callback);
  $summary=app(SellerLedgerService::class)->summary($seller->id);$this->assertSame(1,$calls);$this->assertSame(100.0,$summary['pending']);$this->assertSame(100.0,$summary['sales_total']);$this->assertSame(0.0,$summary['available']);
 }
 public function test_existing_available_balance_is_preserved_while_new_earning_waits():void {
  $seller=Seller::create();SellerWallet::create(['seller_id'=>$seller->id,'total_earning'=>80]);$order=Order::create(['seller_id'=>$seller->id,'seller_is'=>'seller']);
  app(SellerDeliveredEarningService::class)->record($order,function()use($seller){SellerWallet::where('seller_id',$seller->id)->first()->increment('total_earning',20);return true;});
  $summary=app(SellerLedgerService::class)->summary($seller->id);$this->assertSame(80.0,$summary['available']);$this->assertSame(20.0,$summary['pending']);$this->assertSame(100.0,$summary['sales_total']);
 }
}
