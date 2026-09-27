<?php
namespace Tests\Unit;

use App\Models\Order;
use App\Models\PostPurchaseInvoice;
use App\Services\PostPurchaseInvoiceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfflineSecondStageTest extends TestCase
{
    public function test_submitted_offline_order_gets_one_second_invoice_without_confirming_first_payment(): void
    {
        Schema::create('business_settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->text('value'); });
        foreach (['feature_post_purchase_tax_invoice_v3'=>'1','commerce_contract_version'=>'post_purchase_v3',
            'customer_order_insurance_status'=>'1','customer_order_insurance_threshold'=>'0',
            'customer_order_insurance_type'=>'fixed','customer_order_insurance_value'=>'50'] as $type=>$value) {
            DB::table('business_settings')->insert(compact('type','value'));
        }
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->decimal('order_amount'); $t->decimal('total_tax_amount');
            foreach (['payment_method','payment_status','commerce_flow_version','order_status','post_purchase_status','tax_type','tax_model',
                'commerce_flow_status','admin_order_review_status','operational_blocked_by','operational_block_reason'] as $key) $t->string($key)->nullable();
            $t->timestamp('commerce_flow_status_updated_at')->nullable(); $t->timestamps();
        });
        Schema::create('offline_payments', function (Blueprint $t) { $t->id(); $t->integer('order_id'); });
        Schema::create('post_purchase_invoices', function (Blueprint $t) {
            $t->id(); $t->integer('order_id')->unique(); $t->integer('customer_id');
            foreach (['contract_version','status','tax_snapshot','insurance_snapshot','metadata'] as $key) $t->text($key)->nullable();
            foreach (['taxable_amount','tax_rate','tax_amount','insurance_amount','total_amount','paid_amount'] as $key) $t->decimal($key,18,4)->default(0);
            $t->timestamp('payment_due_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->timestamps();
        });
        try {
            $id=DB::table('orders')->insertGetId(['customer_id'=>1,'order_amount'=>100,'total_tax_amount'=>14,
                'payment_method'=>'offline_payment','payment_status'=>'unpaid','commerce_flow_version'=>'post_purchase_v3']);
            $service=app(PostPurchaseInvoiceService::class);
            $this->assertSame([], $service->createForPaidOrderGroup([$id], true));
            DB::table('offline_payments')->insert(['order_id'=>$id]);
            $invoices=$service->createForPaidOrderGroup([$id], true);
            $this->assertCount(1,$invoices);
            $this->assertEquals(64,$invoices[0]->total_amount);
            $this->assertSame('unpaid',Order::find($id)->payment_status);
            $service->createForPaidOrderGroup([$id], true);
            $this->assertSame(1,PostPurchaseInvoice::count());
            $invoices[0]->update(['status'=>'paid','paid_amount'=>64]);
            $this->expectException(\DomainException::class);
            $service->releaseToSeller($invoices[0],1);
        } finally {
            foreach (['post_purchase_invoices','offline_payments','orders','business_settings'] as $table) Schema::dropIfExists($table);
        }
    }
}
