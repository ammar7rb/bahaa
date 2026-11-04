<?php
namespace Tests\Unit;

use App\Services\CustomerPackageCancellationService;
use App\Services\CustomerCancellationStockService;
use App\Services\CustomerPurchaseWalletPolicyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CustomerPackageCancellationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->decimal('wallet_balance', 18, 2)->default(0), $t->timestamps()]);
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->boolean('is_guest')->default(false); $t->string('payment_status');
            $t->string('order_status'); $t->decimal('order_amount',18,2); $t->decimal('init_order_amount',18,2)->nullable();
            $t->timestamp('customer_eligible_at')->nullable(); $t->text('customer_pending_reasons')->nullable();
            $t->string('customer_fulfillment_status')->nullable(); $t->string('commerce_flow_status')->nullable();
            $t->timestamp('commerce_flow_status_updated_at')->nullable(); $t->string('operational_blocked_by')->nullable();
            $t->string('operational_block_reason')->nullable(); $t->string('customer_checkout_operation_id')->nullable(); $t->timestamps();
        });
        Schema::create('order_admin_purchase_refunds', function (Blueprint $t) {
            $t->id(); $t->integer('order_id')->unique(); $t->integer('customer_id'); $t->decimal('amount',18,2); $t->string('status');
            $t->timestamp('due_at'); $t->timestamp('completed_at')->nullable(); $t->string('reference')->nullable()->unique();
            $t->text('reason'); $t->integer('admin_id')->nullable(); $t->string('refund_method')->nullable(); $t->text('admin_note')->nullable(); $t->timestamps();
        });
        DB::table('users')->insert([['id'=>1],['id'=>2]]);
        DB::table('orders')->insert(['id'=>1,'customer_id'=>1,'payment_status'=>'paid','order_status'=>'pending',
            'order_amount'=>115,'init_order_amount'=>115,'customer_checkout_operation_id'=>'op-1','customer_pending_reasons'=>'["monthly_subscription"]']);
    }

    public function test_customer_request_is_idempotent_and_deadline_starts_on_approval(): void
    {
        $this->travelTo(now()->startOfDay());
        $stock = Mockery::mock(CustomerCancellationStockService::class);
        $stock->shouldReceive('restore')->once(); $this->app->instance(CustomerCancellationStockService::class, $stock);
        $service = app(CustomerPackageCancellationService::class);
        $row = $service->request(1, 1); $retry = $service->request(1, 1);
        $this->assertSame($row->id, $retry->id);
        $this->assertSame('customer_requested', $row->status);
        $this->assertEquals(115, $row->amount);
        $this->travel(3)->days();
        $approved = $service->approve($row->id, 7, 15);
        $this->assertTrue($approved->due_at->equalTo(now()->addDays(15)));
        $this->assertSame('canceled', DB::table('orders')->where('id',1)->value('order_status'));
        $this->assertSame('paid', DB::table('orders')->where('id',1)->value('payment_status'));
        $this->assertSame(1, DB::table('order_admin_purchase_refunds')->count());
    }

    public function test_other_customer_cannot_cancel_order(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(CustomerPackageCancellationService::class)->request(2, 1);
    }

    public function test_unpaid_or_fulfillable_order_cannot_use_this_refund(): void
    {
        DB::table('orders')->where('id', 1)->update(['payment_status' => 'unpaid']);
        $this->expectException(ValidationException::class);
        app(CustomerPackageCancellationService::class)->request(1, 1);
    }

    public function test_stale_package_hold_cannot_cancel_an_order_after_subscription_activates(): void
    {
        Schema::create('customer_monthly_subscriptions', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->integer('package_id')->nullable(); $t->timestamp('starts_at'); $t->timestamp('expires_at');
        });
        DB::table('customer_monthly_subscriptions')->insert(['customer_id'=>1, 'package_id'=>1, 'starts_at'=>now()->subDay(), 'expires_at'=>now()->addDay()]);
        $this->expectException(ValidationException::class);
        app(CustomerPackageCancellationService::class)->request(1, 1);
    }

    public function test_stock_out_refund_cannot_duplicate_customer_cancellation_refund(): void
    {
        $stock = Mockery::mock(CustomerCancellationStockService::class);
        $stock->shouldReceive('restore')->once(); $this->app->instance(CustomerCancellationStockService::class, $stock);
        app(CustomerPackageCancellationService::class)->request(1, 1);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن إنشاء رد ثان');
        app(\App\Services\PostPaymentRefundService::class)->refundStockOut(\App\Models\Order::findOrFail(1), 115, 0, 7, 'test', now()->addDays(15));
    }

    public function test_admin_cannot_complete_if_another_refund_was_opened(): void
    {
        $stock = Mockery::mock(CustomerCancellationStockService::class);
        $stock->shouldReceive('restore')->once(); $this->app->instance(CustomerCancellationStockService::class, $stock);
        $service = app(CustomerPackageCancellationService::class); $row = $service->request(1, 1);
        $service->approve($row->id, 7, 15);
        Schema::create('order_refund_ledgers', fn (Blueprint $t) => [$t->id(), $t->integer('order_id')]);
        DB::table('order_refund_ledgers')->insert(['order_id'=>1]);
        try { $service->complete($row->id, 7, 'purchase_wallet', null, null); $this->fail('Competing refund accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('refund', $e->errors()); }
        $this->assertSame('wallet_approved', DB::table('order_admin_purchase_refunds')->value('status'));
    }

    public function test_failed_stock_restoration_rolls_back_request_and_cancellation(): void
    {
        $stock = Mockery::mock(CustomerCancellationStockService::class);
        $stock->shouldReceive('restore')->once()->andThrow(new \RuntimeException('stock unavailable'));
        $this->app->instance(CustomerCancellationStockService::class, $stock);
        try { app(CustomerPackageCancellationService::class)->request(1, 1); $this->fail('Expected failure'); }
        catch (\RuntimeException $e) { $this->assertSame('stock unavailable', $e->getMessage()); }
        $this->assertSame(0, DB::table('order_admin_purchase_refunds')->count());
        $this->assertSame('pending', DB::table('orders')->where('id', 1)->value('order_status'));
    }

    public function test_schema_upgrade_preserves_existing_refunds(): void
    {
        Schema::table('order_admin_purchase_refunds', fn (Blueprint $t) => $t->dropColumn(['refund_method', 'admin_note']));
        DB::table('order_admin_purchase_refunds')->insert(['order_id'=>1,'customer_id'=>1,'amount'=>115,
            'status'=>'scheduled','due_at'=>now()->addDays(7),'reason'=>'historical']);
        $migration = require database_path('migrations/2026_11_03_110000_add_customer_cancellation_refund_decisions.php');
        $migration->up();
        $this->assertDatabaseHas('order_admin_purchase_refunds', ['order_id'=>1,'status'=>'scheduled','reason'=>'historical']);
        $this->assertTrue(Schema::hasColumn('order_admin_purchase_refunds', 'refund_method'));
        $migration->down();
        $this->assertDatabaseHas('order_admin_purchase_refunds', ['order_id'=>1,'status'=>'scheduled','reason'=>'historical']);
    }

    public function test_routes_require_customer_or_orders_administration_permissions(): void
    {
        $routes = app('router')->getRoutes();
        $web = $routes->getByName('customer.order.package-cancellation');
        $admin = $routes->getByName('admin.orders.package-refunds.complete');
        $this->assertContains('customer', $web->gatherMiddleware());
        $this->assertContains('module:orders', $admin->gatherMiddleware());
        $this->assertContains('admin', $admin->gatherMiddleware());
    }

    public function test_external_refund_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(CustomerPackageCancellationService::class)->complete(1, 7, 'external', 'bank-123', null);
    }

    public function test_wallet_refund_waits_until_due_and_credits_once(): void
    {
        $stock = Mockery::mock(CustomerCancellationStockService::class);
        $stock->shouldReceive('restore')->once();
        $this->app->instance(CustomerCancellationStockService::class, $stock);
        $wallet = Mockery::mock(CustomerPurchaseWalletPolicyService::class);
        $wallet->shouldReceive('creditPurchaseRefundBaseCurrency')->once()->with(1, 115.0, 'customer-cancel-order-1')
            ->andReturn(new \App\Models\WalletTransaction([
                'user_id' => 1, 'transaction_type' => 'order_refund', 'credit' => 115, 'debit' => 0,
            ]));
        $this->app->instance(CustomerPurchaseWalletPolicyService::class, $wallet);
        $service = app(CustomerPackageCancellationService::class);
        $row = $service->request(1, 1);
        $service->approve($row->id, 7, 15);
        try {
            $service->complete($row->id, 7, 'purchase_wallet', null, null);
            $this->fail('Early refund accepted');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('refund', $error->errors());
        }
        $this->travel(16)->days();
        $service->complete($row->id, 7, 'purchase_wallet', null, null);
        $service->complete($row->id, 7, 'purchase_wallet', null, null);
        $this->assertDatabaseHas('order_admin_purchase_refunds', [
            'id' => $row->id, 'status' => 'completed', 'refund_method' => 'purchase_wallet',
        ]);
    }
}
