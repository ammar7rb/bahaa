<?php
namespace Tests\Unit;
use App\Models\WalletTransaction;
use App\Services\CustomerWalletStatementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Http\Request;
class CustomerWalletStatementTest extends \Tests\TestCase {
    protected function setUp():void {
        parent::setUp();
        Schema::create('wallet_transactions',function(Blueprint $t){$t->id();$t->integer('user_id');$t->string('transaction_id');$t->string('transaction_type');$t->string('reference')->nullable();$t->string('payment_method')->nullable();$t->integer('customer_subscription_payment_id')->nullable();foreach(['credit','debit','balance','admin_bonus']as$c)$t->decimal($c,18,3)->default(0);$t->timestamps();});
        (require database_path('migrations/2026_10_14_180000_customer_wallet_statement.php'))->up();
        Schema::create('customer_subscription_payments',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->string('purchase_kind');$t->string('terms_name');$t->string('payment_reference');});
        Schema::create('customer_balance_deposits',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->string('wallet_type');$t->string('method_name');});
        Schema::create('orders',function(Blueprint $t){$t->id();$t->integer('customer_id');});
        Schema::create('post_purchase_invoices',function(Blueprint $t){$t->id();$t->integer('order_id');$t->integer('customer_id');$t->string('invoice_number');});
    }
    public function test_shared_statement_preserves_types_orders_notes_and_ownership():void {
        foreach(['activation','upgrade','renew_limit','increase']as$i=>$kind){
            DB::table('customer_subscription_payments')->insert(['id'=>$i+1,'customer_id'=>1,'purchase_kind'=>$kind,'terms_name'=>'Silver','payment_reference'=>'package-'.$i]);
            WalletTransaction::create(['user_id'=>1,'transaction_id'=>'txn-'.$i,'transaction_type'=>'monthly_subscription','customer_subscription_payment_id'=>$i+1,'debit'=>100,'balance'=>900]);
        }
        DB::table('orders')->insert([['id'=>10,'customer_id'=>1],['id'=>11,'customer_id'=>2]]);
        DB::table('post_purchase_invoices')->insert(['id'=>5,'order_id'=>10,'customer_id'=>1,'invoice_number'=>'INV-10']);
        $purchase=WalletTransaction::create(['user_id'=>1,'transaction_id'=>'buy','transaction_type'=>'order_place','order_ids'=>[10,11],'debit'=>200,'balance'=>700,'customer_note'=>'تمت المطابقة','note_updated_at'=>now()]);
        $rows=app(CustomerWalletStatementService::class)->decorate(WalletTransaction::all());
        $this->assertSame(['subscription','upgrade','renew_limit','increase','purchase'],array_column($rows,'entry_type'));
        $this->assertSame([['order_id'=>10,'invoice_id'=>5,'invoice_number'=>'INV-10']],$rows[4]['orders']);
        $this->assertSame('تمت المطابقة',$rows[4]['customer_note']);
        $this->assertEquals(200,$rows[4]['debit']);$this->assertEquals(700,$rows[4]['balance']);
        foreach(['subscription','upgrade','renew_limit','increase']as$type){
            $this->assertSame(1,app(CustomerWalletStatementService::class)->query(1,Request::create('/','GET',['entry_type'=>$type]))->count());
        }
    }
    public function test_filters_and_pages_do_not_drop_legacy_records_or_mix_customers():void {
        foreach(range(1,25)as$id)WalletTransaction::create(['user_id'=>1,'transaction_id'=>'x-'.$id,'transaction_type'=>'monthly_subscription','created_at'=>'2026-10-14 10:00:00']);
        WalletTransaction::create(['user_id'=>2,'transaction_id'=>'other','transaction_type'=>'monthly_subscription']);
        $q=app(CustomerWalletStatementService::class)->query(1,Request::create('/','GET',['entry_type'=>'subscription','to'=>'2026-10-14']));
        $first=(clone$q)->paginate(20,['*'],'page',1);$second=(clone$q)->paginate(20,['*'],'page',2);
        $this->assertSame(25,$first->total());$this->assertCount(20,$first);$this->assertCount(5,$second);
        $this->assertSame(0,$first->getCollection()->pluck('id')->intersect($second->getCollection()->pluck('id'))->count());
    }
    public function test_offline_deposit_method_and_reference_are_derived_from_its_own_record():void {
        DB::table('customer_balance_deposits')->insert(['id'=>8,'customer_id'=>1,'wallet_type'=>'purchase','method_name'=>'InstaPay']);
        $entry=WalletTransaction::create(['user_id'=>1,'transaction_id'=>'deposit','reference'=>'customer-offline-deposit-8','transaction_type'=>'add_fund','credit'=>300,'balance'=>300]);
        $row=app(CustomerWalletStatementService::class)->decorate([$entry])[0];
        $this->assertSame('deposit',$row['entry_type']);$this->assertSame('InstaPay',$row['payment_method']);$this->assertNotEmpty($row['reference_label']);
    }
    public function test_actual_statement_blade_renders_notes_and_references():void {
        if(!defined('DOMAIN_POINTED_DIRECTORY'))define('DOMAIN_POINTED_DIRECTORY','public');
        Schema::create('currencies',function(Blueprint $t){$t->id();$t->string('code');$t->string('symbol');$t->decimal('exchange_rate',12,3);});
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value');});
        DB::table('currencies')->insert(['id'=>1,'code'=>'EGP','symbol'=>'ج.م','exchange_rate'=>1]);
        foreach(['currency_model'=>'single_currency','system_default_currency'=>'1']as$key=>$value)DB::table('business_settings')->insert(['type'=>$key,'value'=>$value]);
        session()->put(['local'=>'ar','direction'=>'rtl']);
        $entry=WalletTransaction::create(['user_id'=>1,'transaction_id'=>'QA-REF','transaction_type'=>'order_place','debit'=>800,'balance'=>1200,'customer_note'=>'تمت مطابقة شراء المستلزمات','payment_method'=>'wallet_balance']);
        $rows=app(CustomerWalletStatementService::class)->decorate([$entry]);
        $html=view('customer-wallet.statement',['purchaseEntries'=>new \Illuminate\Pagination\LengthAwarePaginator($rows,1,10)])->render();
        $this->assertStringContainsString('شراء منتجات',$html);$this->assertStringContainsString('تمت مطابقة شراء المستلزمات',$html);$this->assertStringContainsString('QA-REF',$html);
        $directory=base_path('output/customer-stage68');if(!is_dir($directory))mkdir($directory,0777,true);
        file_put_contents($directory.'/wallet.html','<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/back-end/css/bootstrap.min.css"><style>body{padding:24px;font-family:Tahoma;background:#f4f7fb}main{background:white;padding:24px;max-width:1200px;margin:auto}</style><main>'.$html.'</main></html>');
    }
    public function test_admin_note_changes_only_note_and_is_returned_by_statement():void {
        $entry=WalletTransaction::create(['user_id'=>1,'transaction_id'=>'note-test','transaction_type'=>'order_place','debit'=>200,'balance'=>900]);
        $admin=new \App\Models\Admin();$admin->id=7;$admin->admin_role_id=1;$admin->setRelation('role',null);auth('admin')->setUser($admin);
        $controller=\Mockery::mock(\App\Http\Controllers\Admin\Customer\CustomerWalletController::class)->makePartial();
        $controller->updateNote(Request::create('/','POST',['customer_note'=>'تأكيد المطابقة']),$entry);
        $fresh=$entry->fresh();$this->assertEquals(200,$fresh->debit);$this->assertEquals(900,$fresh->balance);$this->assertEquals(7,$fresh->note_admin_id);
        $this->assertNotNull($fresh->note_updated_at);$this->assertSame('تأكيد المطابقة',app(CustomerWalletStatementService::class)->decorate([$fresh])[0]['customer_note']);
        auth('admin')->logout();
        try{$controller->updateNote(Request::create('/','POST',['customer_note'=>'unauthorized']),$entry);$this->fail('Unauthorized note update');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
    }
}
