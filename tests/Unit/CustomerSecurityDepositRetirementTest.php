<?php

namespace Tests\Unit;

use App\Services\CustomerInsuranceDepositService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerSecurityDepositRetirementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_10_10_200000_create_customer_monthly_subscriptions.php'))->up();
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value');$t->timestamps();});
        Schema::create('customer_activation_invoices',function(Blueprint $t){$t->id();$t->string('status');$t->string('payment_status');$t->decimal('insurance_amount',18,3);$t->decimal('total_amount',18,3);$t->json('metadata')->nullable();$t->timestamps();});
        Schema::create('post_purchase_invoices',function(Blueprint $t){$t->id();$t->string('status');$t->decimal('insurance_amount',18,3);$t->decimal('tax_amount',18,3);$t->decimal('total_amount',18,3);$t->json('metadata')->nullable();$t->json('insurance_snapshot')->nullable();$t->timestamps();});
        Schema::create('customer_balance_deposits',function(Blueprint $t){$t->id();$t->string('wallet_type');$t->string('status');$t->decimal('amount',18,3);$t->timestamps();});
        Schema::create('seller_insurances',function(Blueprint $t){$t->id();$t->decimal('amount',18,3);$t->string('status');});
        DB::table('business_settings')->insert(['type'=>'currency_model','value'=>'single_currency']);
        DB::table('customer_activation_invoices')->insert(['id'=>1,'status'=>'paid','payment_status'=>'paid','insurance_amount'=>50,'total_amount'=>120]);
        DB::table('post_purchase_invoices')->insert(['id'=>1,'status'=>'pending','insurance_amount'=>50,'tax_amount'=>14,'total_amount'=>64]);
        DB::table('post_purchase_invoices')->insert(['id'=>2,'status'=>'pending','insurance_amount'=>20,'tax_amount'=>0,'total_amount'=>20]);
        DB::table('customer_balance_deposits')->insert(['id'=>1,'wallet_type'=>'purchase','status'=>'pending','amount'=>5000]);
        DB::table('customer_balance_deposits')->insert(['id'=>2,'wallet_type'=>'insurance','status'=>'pending','amount'=>50]);
        DB::table('seller_insurances')->insert(['id'=>1,'status'=>'paid','amount'=>200]);
    }

    public function test_audit_changes_nothing(): void {
        $this->artisan('customers:retire-security-deposits')->assertSuccessful();
        $this->assertSame(0,DB::table('customer_financial_archives')->count());$this->assertEquals(50,DB::table('customer_activation_invoices')->value('insurance_amount'));
    }
    public function test_archive_is_repeatable_and_preserves_tax_paid_totals_purchase_and_vendor_funds(): void {
        $this->artisan('customers:retire-security-deposits',['--apply'=>true])->assertSuccessful();
        $this->artisan('customers:retire-security-deposits',['--apply'=>true])->assertSuccessful();
        $this->assertSame(4,DB::table('customer_financial_archives')->count());
        $this->assertEquals(0,DB::table('customer_activation_invoices')->value('insurance_amount'));$this->assertEquals(120,DB::table('customer_activation_invoices')->value('total_amount'));
        $this->assertEquals(14,DB::table('post_purchase_invoices')->where('id',1)->value('tax_amount'));$this->assertEquals(14,DB::table('post_purchase_invoices')->where('id',1)->value('total_amount'));
        $this->assertSame('cancelled',DB::table('post_purchase_invoices')->where('id',2)->value('status'));
        $this->assertSame('pending',DB::table('customer_balance_deposits')->where('id',1)->value('status'));$this->assertEquals(5000,DB::table('customer_balance_deposits')->where('id',1)->value('amount'));
        $this->assertSame('legacy_review',DB::table('customer_balance_deposits')->where('id',2)->value('status'));$this->assertEquals(200,DB::table('seller_insurances')->value('amount'));$this->assertSame('paid',DB::table('seller_insurances')->value('status'));
        $snapshot=json_decode(DB::table('customer_financial_archives')->where('source_table','customer_activation_invoices')->value('snapshot'),true);$this->assertEquals(50,$snapshot['insurance_amount']);
    }
    public function test_late_verified_callback_records_once_without_creating_insurance_or_subscription(): void {
        $payment=['is_paid'=>1,'attribute'=>'customer_insurance_deposit','payer_id'=>1,'id'=>'legacy-42','payment_amount'=>50,'currency_code'=>'EGP','payment_method'=>'wallet'];
        $s=app(CustomerInsuranceDepositService::class);$s->complete($payment);$s->complete($payment);
        $this->assertSame(1,DB::table('customer_legacy_payment_receipts')->count());$this->assertEquals(50,DB::table('customer_legacy_payment_receipts')->sum('amount'));$this->assertSame(0,DB::table('customer_monthly_subscriptions')->count());
    }
    public function test_unconfirmed_callback_cannot_record_funds(): void {
        app(CustomerInsuranceDepositService::class)->complete(['is_paid'=>0,'attribute'=>'customer_insurance_deposit']);$this->assertSame(0,DB::table('customer_legacy_payment_receipts')->count());
    }

    public function test_old_guest_order_callback_keeps_verified_money_without_creating_or_matching_an_account(): void {
        Schema::create('customer_checkout_operations',function(Blueprint $t){$t->uuid('id')->primary();});
        $payment=['id'=>'old-order-42','is_paid'=>1,'payment_amount'=>250,'currency_code'=>'EGP','transaction_id'=>'verified-ref',
            'additional_data'=>json_encode(['customer_id'=>999,'is_guest_in_order'=>1,'new_customer_info'=>['phone'=>'01012345678','password'=>'must-not-be-stored']])];
        digital_payment_success($payment);digital_payment_success($payment);
        $this->assertSame(1,DB::table('customer_legacy_payment_receipts')->count());
        $receipt=DB::table('customer_legacy_payment_receipts')->first();$this->assertEquals(250,$receipt->amount);
        $this->assertNull($receipt->customer_id);$this->assertStringNotContainsString('password',$receipt->snapshot);
        $this->assertStringNotContainsString('01012345678',$receipt->snapshot);
        $this->assertSame(0,DB::table('customer_monthly_subscriptions')->count());
    }
}
