<?php

namespace Tests\Unit;

use App\Http\Middleware\RecordPrivacySafePlatformMetric;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Services\FinancialReconciliationService;
use App\Services\SellerFinancePresentationService;
use App\Services\SellerLedgerService;
use App\Services\SellerSettlementService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdminReportingBatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('business_settings', fn (Blueprint $t) => [$t->id(), $t->string('type')->unique(), $t->text('value')->nullable(), $t->timestamps()]);
        Schema::create('sellers', fn (Blueprint $t) => [$t->id(), $t->string('app_language')->nullable(), $t->timestamps()]);
        Schema::create('seller_wallets', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('seller_id'), $t->decimal('total_earning',40,20)->default(0), $t->decimal('pending_withdraw',40,20)->default(0), $t->decimal('withdrawn',40,20)->default(0), $t->timestamps()]);
        Schema::create('seller_ledger_entries', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->string('bucket'); $t->string('direction'); $t->decimal('amount',40,20); $t->string('event_type'); $t->string('reporting_category')->nullable(); $t->string('group_key'); $t->char('idempotency_key',64)->unique(); $t->string('reference_type')->nullable(); $t->unsignedBigInteger('reference_id')->nullable(); $t->string('reference_code')->nullable(); $t->json('metadata')->nullable(); $t->char('previous_hash',64)->nullable(); $t->char('entry_hash',64); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps(); });
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('customer_id')->nullable(); $t->unsignedBigInteger('seller_id')->nullable(); $t->string('seller_is')->nullable(); $t->string('payment_status')->nullable(); $t->string('order_status')->nullable(); $t->decimal('order_amount',20,3)->default(0); $t->decimal('admin_commission',20,3)->default(0); $t->decimal('shipping_customer_cost',20,3)->default(0); $t->decimal('shipping_seller_cost',20,3)->default(0); $t->decimal('shipping_seller_entitlement',20,3)->default(0); $t->string('shipment_reference')->nullable(); $t->string('shipping_operational_status')->nullable(); $t->timestamps(); });
        Schema::create('seller_settlements', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('order_id')->unique(); $t->decimal('amount',40,20); $t->string('status'); $t->timestamp('delivered_at'); $t->timestamp('dispute_until')->nullable(); $t->timestamp('due_at')->nullable(); $t->string('timezone_snapshot')->nullable(); $t->unsignedTinyInteger('pre_due_days_snapshot')->default(5); $t->timestamp('pre_due_notified_at')->nullable(); $t->timestamp('overdue_notified_at')->nullable(); $t->timestamp('escalated_at')->nullable(); $t->timestamp('released_at')->nullable(); $t->timestamp('reversed_at')->nullable(); $t->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $t->text('review_note')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); });
        Schema::create('notifications', fn (Blueprint $t) => [$t->id(), $t->string('sent_by')->nullable(), $t->string('sent_to')->nullable(), $t->unsignedBigInteger('seller_id')->nullable(), $t->string('title')->nullable(), $t->text('description')->nullable(), $t->integer('notification_count')->default(1), $t->boolean('status')->default(1), $t->timestamps()]);
        Schema::create('platform_daily_metrics', fn (Blueprint $t) => [$t->id(), $t->date('metric_date'), $t->string('channel'), $t->unsignedBigInteger('page_views')->default(0), $t->unsignedBigInteger('requests')->default(0), $t->timestamps(), $t->unique(['metric_date','channel'])]);
        Schema::create('seller_package_transactions', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('seller_id'), $t->decimal('paid_amount',20,3)->default(0), $t->timestamps()]);
        Schema::create('order_insurances', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('customer_id'), $t->unsignedBigInteger('order_id'), $t->decimal('amount',20,3), $t->string('payment_status'), $t->timestamps()]);
        Schema::create('seller_order_insurances', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('seller_id'), $t->unsignedBigInteger('order_id'), $t->decimal('amount',20,3), $t->string('payment_status'), $t->timestamps()]);
        Schema::create('refund_requests', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('seller_id'), $t->unsignedBigInteger('order_id'), $t->decimal('amount',20,3), $t->string('status'), $t->timestamps()]);
        Cache::flush(); Carbon::setTestNow('2026-08-11 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); Cache::flush();
        foreach (['refund_requests','seller_order_insurances','order_insurances','seller_package_transactions','platform_daily_metrics','notifications','seller_settlements','orders','seller_ledger_entries','seller_wallets','sellers','business_settings'] as $table) Schema::dropIfExists($table);
        parent::tearDown();
    }

    public function test_financial_tabs_use_same_immutable_ledger_and_stable_order_reference(): void
    {
        $seller = Seller::create();
        $order = Order::create(['seller_id'=>$seller->id,'seller_is'=>'seller','shipment_reference'=>'SHP-1','shipping_operational_status'=>'assigned']);
        app(SellerSettlementService::class)->holdDeliveredOrder($order, 100, now()->addDay());
        $overview = app(SellerFinancePresentationService::class)->overview($seller->id);
        $this->assertSame(100.0, $overview['summary'][SellerLedgerService::PENDING]);
        $this->assertCount(1, $overview['tabs']['shipping']);
        $this->assertSame('ORD-'.$order->id, $overview['tabs']['shipping'][0]['reference_code']);
        $this->assertSame('SHP-1', $overview['tabs']['shipping'][0]['shipment_reference']);
    }

    public function test_settlement_keeps_timezone_and_alert_snapshots_and_escalates_once(): void
    {
        foreach (['timezone'=>'Africa/Cairo','seller_settlement_pre_due_alert_days'=>'5','seller_settlement_overdue_escalation_days'=>'2'] as $type=>$value) BusinessSetting::create(compact('type','value'));
        $seller = Seller::create(['app_language'=>'en']);
        $order = Order::create(['seller_id'=>$seller->id,'seller_is'=>'seller']);
        $settlement = app(SellerSettlementService::class)->holdDeliveredOrder($order, 20, now()->subDays(3));
        $this->assertSame('Africa/Cairo', $settlement->timezone_snapshot);
        $this->assertSame(5, $settlement->pre_due_days_snapshot);
        $first = app(SellerSettlementService::class)->sendDueAlerts();
        $second = app(SellerSettlementService::class)->sendDueAlerts();
        $this->assertSame(1, $first['escalated']);
        $this->assertArrayNotHasKey('escalated', $second);
        $this->assertNotNull($settlement->fresh()->escalated_at);
    }

    public function test_reconciliation_covers_sales_shipping_packages_insurance_refunds_and_ledger(): void
    {
        $seller = Seller::create();
        $order = Order::create(['seller_id'=>$seller->id,'seller_is'=>'seller','payment_status'=>'paid','order_amount'=>1000,'admin_commission'=>100,'shipping_customer_cost'=>50,'shipping_seller_cost'=>30,'shipping_seller_entitlement'=>10]);
        app(SellerLedgerService::class)->post($seller->id,'order_earning','order:'.$order->id,[['bucket'=>'available','direction'=>'credit','amount'=>900]],Order::class,$order->id,['order_id'=>$order->id]);
        DB::table('seller_package_transactions')->insert(['seller_id'=>$seller->id,'paid_amount'=>75,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('order_insurances')->insert(['customer_id'=>1,'order_id'=>$order->id,'amount'=>20,'payment_status'=>'paid','created_at'=>now(),'updated_at'=>now()]);
        DB::table('seller_order_insurances')->insert(['seller_id'=>$seller->id,'order_id'=>$order->id,'amount'=>25,'payment_status'=>'paid','created_at'=>now(),'updated_at'=>now()]);
        DB::table('refund_requests')->insert(['seller_id'=>$seller->id,'order_id'=>$order->id,'amount'=>40,'status'=>'approved','created_at'=>now(),'updated_at'=>now()]);
        $result = app(FinancialReconciliationService::class)->summary($seller->id);
        $this->assertSame(1000.0, $result['sales_revenue']); $this->assertSame(50.0, $result['shipping_customer_revenue']);
        $this->assertSame(75.0, $result['seller_package_revenue']); $this->assertSame(20.0, $result['customer_insurance_received']);
        $this->assertSame(25.0, $result['seller_insurance_received']); $this->assertSame(40.0, $result['refunds']);
        $this->assertSame(0, $result['missing_references']); $this->assertSame(0, $result['duplicate_idempotency_keys']);
    }

    public function test_visit_metrics_are_aggregated_without_identity_data(): void
    {
        $middleware = new RecordPrivacySafePlatformMetric();
        $middleware->handle(Request::create('/home','GET'), fn () => new Response('ok',200));
        $middleware->handle(Request::create('/home','GET'), fn () => new Response('ok',200));
        $row = DB::table('platform_daily_metrics')->where('channel','web')->first();
        $this->assertSame(2, (int) $row->page_views);
        $this->assertSame(['id','metric_date','channel','page_views','requests','created_at','updated_at'], Schema::getColumnListing('platform_daily_metrics'));
    }
}
