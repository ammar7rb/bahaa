<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\CustomerPurchaseEligibilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerPurchaseEligibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('onboarding_journey')->default('standard'); $t->string('activation_status')->default('pending');
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id'); $t->boolean('is_guest')->default(false);
            $t->string('order_status')->default('pending'); $t->string('payment_status')->default('unpaid');
            $t->string('payment_method')->default('offline_payment'); $t->string('activation_status')->nullable(); $t->text('order_note')->nullable();
            $t->boolean('is_pause')->default(false); $t->string('commerce_flow_status')->default('pending_admin_review'); $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_10_200000_create_customer_monthly_subscriptions.php'))->up();
        (require base_path('database/migrations/2026_10_10_300000_add_customer_purchase_eligibility.php'))->up();
        (require base_path('database/migrations/2026_10_10_310000_preserve_customer_checkout_snapshot.php'))->up();
        (require base_path('database/migrations/2026_10_12_150000_review_initial_confirmed_order_payments.php'))->up();
        Schema::create('offline_payments', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('order_id'); $t->json('payment_info'); $t->timestamps(); });
        DB::table('users')->insert(['id' => 1, 'onboarding_journey' => 'checkout_first', 'activation_status' => 'pending']);
        app()->instance(\App\Services\MonthlySubscriptionPricingService::class, new class extends \App\Services\MonthlySubscriptionPricingService {
            public function get(): array {return ['customer_price'=>100,'vendor_price'=>null,'currency_code'=>'EGP'];}
        });
    }

    private function service(): CustomerPurchaseEligibilityService {return app(CustomerPurchaseEligibilityService::class);}
    public function test_settled_initial_wallet_payment_waits_for_review_without_another_debit(): void
    {
        $this->initialOrders(); $this->subscribe();
        DB::table('users')->where('id', 1)->update(['activation_status' => 'active']);
        DB::table('orders')->update(['payment_method' => 'wallet', 'customer_initial_payment_review_status' => 'pending']);
        $this->service()->reevaluateCustomer(1);
        $this->assertSame(['order_payment_review'], json_decode(DB::table('orders')->where('id', 1)->value('customer_pending_reasons'), true));
        $this->assertNull(DB::table('orders')->where('id', 1)->value('customer_eligible_at'));
        $this->service()->reviewInitialConfirmedPayment(1, true, 7);
        $this->service()->reviewInitialConfirmedPayment(1, true, 7);
        $this->assertSame('paid', DB::table('orders')->where('id', 1)->value('payment_status'));
        $this->assertSame('preparing', DB::table('orders')->where('id', 1)->value('customer_fulfillment_status'));
        $this->assertSame(7, DB::table('orders')->where('id', 1)->value('customer_initial_payment_reviewed_by'));
        $this->assertNull(DB::table('orders')->where('id', 2)->value('customer_eligible_at'));
    }

    public function test_rejected_settled_initial_payment_retains_financial_payment_and_holds_order(): void
    {
        $this->initialOrders(); $this->subscribe();
        DB::table('users')->where('id', 1)->update(['activation_status' => 'active']);
        DB::table('orders')->update(['payment_method' => 'wallet', 'customer_initial_payment_review_status' => 'pending']);
        $this->service()->reviewInitialConfirmedPayment(1, false, 7, 'تحتاج مراجعة');
        $this->service()->reviewInitialConfirmedPayment(1, true, 7);
        $this->assertSame('paid', DB::table('orders')->where('id', 1)->value('payment_status'));
        $this->assertSame('rejected', DB::table('orders')->where('id', 1)->value('customer_initial_payment_review_status'));
        $this->assertNull(DB::table('orders')->where('id', 1)->value('customer_eligible_at'));
        $this->assertSame(['initial_order_review_rejected'], json_decode(DB::table('orders')->where('id', 1)->value('customer_pending_reasons'), true));
    }
    private function subscribe(): void {
        DB::table('customer_monthly_subscriptions')->insert(['customer_id'=>1,'payment_id'=>100,'starts_at'=>now(),'expires_at'=>now()->addDays(30)]);
    }
    private function initialOrders(bool $paid = true): string {
        $op = $this->service()->reserve(1, 'cart-a', null, 'offline_payment');
        foreach ([1,2] as $id) DB::table('orders')->insert(['id'=>$id,'customer_id'=>1,'payment_status'=>$paid ? 'paid':'unpaid','customer_checkout_operation_id'=>$op->id]);
        DB::table('customer_checkout_operations')->where('id',$op->id)->update(['consumed_at'=>now(),'order_ids'=>'[1,2]']);
        return $op->id;
    }
    private function preparing(): void {
        $this->service()->reevaluateCustomer(1);
        $this->assertSame(['preparing','preparing'],DB::table('orders')->orderBy('id')->pluck('customer_fulfillment_status')->all());
        $this->assertSame(['pending_admin_review','pending_admin_review'],DB::table('orders')->pluck('commerce_flow_status')->all());
    }

    public function test_new_standard_registration_can_prepay_initial_order_before_subscription(): void {
        DB::table('users')->where('id',1)->update(['onboarding_journey'=>'standard']);
        $this->assertTrue($this->service()->status(User::find(1))['can_checkout']);
        $operation = $this->service()->reserve(1,'cart-a',null,'offline_payment');
        $this->assertTrue((bool)$operation->first_exception);
        $this->expectException(ValidationException::class); $this->service()->reserve(1,'cart-b');
    }

    public function test_package_request_requires_review_and_is_sent_once_without_recharging(): void {
        Schema::create('notifications', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('customer_event_key')->unique();
            foreach (['sent_by','sent_to','title','description','image'] as $column) $t->string($column);
            $t->integer('status'); $t->integer('notification_count'); $t->timestamps();
        });
        $this->initialOrders();
        DB::table('orders')->update(['customer_initial_payment_review_status'=>'pending']);
        $this->service()->reevaluateCustomer(1);
        $this->assertFalse($this->service()->orderPayload(\App\Models\Order::find(1))['can_request_package']);
        $this->assertSame(0, DB::table('notifications')->count());
        try {
            $this->service()->requestPackageForOrder(1);
            $this->fail('Package request must wait for payment approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_eligibility',$exception->errors());
        }
        $this->service()->reviewInitialConfirmedPayment(1, true, 7);
        $this->service()->requestPackageForOrder(1);
        $this->service()->requestPackageForOrder(1);
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertStringContainsString('يلزم الاشتراك في باقة', DB::table('notifications')->value('description'));
        $this->assertSame('paid', DB::table('orders')->where('id',1)->value('payment_status'));
        $this->assertNull(DB::table('orders')->where('id',1)->value('customer_eligible_at'));
        Schema::drop('notifications');
    }

    public function test_frozen_new_customer_cannot_checkout(): void {
        DB::table('users')->where('id',1)->update(['is_active'=>0]);
        $this->assertFalse($this->service()->status(User::find(1))['can_checkout']);
        $this->expectException(ValidationException::class); $this->service()->reserve(1,'cart-a');
    }
    public function test_initial_exception_is_reserved_once_and_same_cart_retry_uses_same_operation(): void {
        $first=$this->service()->reserve(1,'cart-a',null,'offline_payment');
        $this->assertTrue((bool)$first->first_exception);
        $this->assertSame($first->id,$this->service()->reserve(1,'cart-a',null,'offline_payment')->id);
        $this->expectException(ValidationException::class); $this->service()->reserve(1,'different-cart');
    }
    public function test_paid_order_waits_until_activation_then_subscription(): void {
        $this->initialOrders();$this->service()->reevaluateCustomer(1);
        $this->assertSame('pending_requirements',DB::table('orders')->value('customer_fulfillment_status'));
        DB::table('users')->where('id',1)->update(['activation_status'=>'active']);$this->service()->reevaluateCustomer(1);
        $this->assertNull(DB::table('orders')->value('customer_eligible_at'));
        $this->subscribe();$this->preparing();
    }
    public function test_subscription_then_activation_has_same_result(): void {
        $this->initialOrders();$this->subscribe();$this->service()->reevaluateCustomer(1);
        $this->assertNull(DB::table('orders')->value('customer_eligible_at'));
        DB::table('users')->where('id',1)->update(['activation_status'=>'active']);$this->preparing();
    }
    public function test_unpaid_order_never_starts_preparing(): void {
        $this->initialOrders(false);$this->subscribe();DB::table('users')->where('id',1)->update(['activation_status'=>'active']);
        $this->service()->reevaluateCustomer(1);$this->assertNull(DB::table('orders')->value('customer_eligible_at'));
        $this->assertSame(['order_payment_review'],json_decode(DB::table('orders')->value('customer_pending_reasons'),true));
    }
    public function test_freeze_prevents_preparation_and_unfreeze_releases_paid_orders(): void {
        $this->initialOrders();$this->subscribe();DB::table('users')->where('id',1)->update(['activation_status'=>'active','is_active'=>0]);
        $this->service()->reevaluateCustomer(1);$this->assertNull(DB::table('orders')->value('customer_eligible_at'));
        DB::table('users')->where('id',1)->update(['is_active'=>1]);$this->preparing();
    }
    public function test_expiry_without_scheduler_prevents_new_purchases_without_relocking_preparing_orders(): void {
        $this->initialOrders();$this->subscribe();DB::table('users')->where('id',1)->update(['activation_status'=>'active']);$this->preparing();
        $eligibleAt=DB::table('orders')->value('customer_eligible_at');$this->travel(31)->days();
        $this->assertFalse($this->service()->status(User::find(1))['can_checkout']);$this->preparing();
        $this->assertSame($eligibleAt,DB::table('orders')->value('customer_eligible_at'));
    }
    public function test_canceling_initial_operation_does_not_restore_exception(): void {
        $this->initialOrders();DB::table('orders')->update(['order_status'=>'canceled']);
        $this->expectException(ValidationException::class);$this->service()->reserve(1,'cart-b');
    }
    public function test_legacy_accounts_do_not_receive_initial_exception(): void {
        DB::table('users')->where('id',1)->update(['onboarding_journey'=>'legacy']);
        $this->assertFalse($this->service()->status(User::find(1))['initial_order_exception_available']);
    }
    public function test_first_exception_requires_prepayment(): void {
        $this->expectException(ValidationException::class);$this->service()->reserve(1,'cart-a',null,'cash_on_delivery');
    }
    public function test_gateway_reservation_is_customer_bound_and_survives_expiry(): void {
        DB::table('users')->where('id',1)->update(['activation_status'=>'active']);$this->subscribe();
        $op=$this->service()->reserve(1,'cart-a');$this->travel(31)->days();
        $this->assertSame($op->id,$this->service()->reserve(1,'cart-a',$op->id)->id);
        $this->expectException(ValidationException::class);$this->service()->reserve(1,'cart-a','unknown-operation');
    }

    public function test_rejected_initial_payment_retries_same_order_without_restoring_exception(): void {
        $operation = $this->initialOrders(false);
        DB::table('offline_payments')->insert(['order_id'=>1,'payment_info'=>json_encode(['payment_proof'=>'old.jpg','payment_reference'=>'old-ref'])]);
        $this->service()->rejectOrderPayment(1,'إثبات غير واضح');
        $this->service()->rejectOrderPayment(1,'رفض مكرر');
        $order=DB::table('orders')->where('id',1)->first();
        $this->assertSame('pending',$order->order_status);
        $this->assertSame(1,substr_count($order->order_note,'Offline payment rejected:'));
        $this->assertContains('order_payment_rejected',json_decode($order->customer_pending_reasons,true));
        $key=(string)\Illuminate\Support\Str::uuid();
        $retry=app(\App\Services\CustomerOrderPaymentRetryService::class);
        $this->assertTrue($retry->submit(1,1,$key,'new-ref','customer-order-retries/test.enc'));
        $this->assertFalse($retry->submit(1,1,$key,'new-ref','customer-order-retries/duplicate.enc'));
        $info=\App\Models\OfflinePayments::first();
        $this->assertCount(1,$info->payment_info['retry_history']);
        $this->assertSame('old-ref',$info->payment_info['retry_history'][0]['information']['payment_reference']);
        $this->assertArrayNotHasKey('private_retry_proof',$info->toArray()['payment_info']);
        $this->assertSame($operation,DB::table('users')->where('id',1)->value('first_checkout_operation_id'));
        $this->assertSame(2,DB::table('orders')->count());
        $this->assertSame('unpaid',DB::table('orders')->where('id',1)->value('payment_status'));
        $this->assertNull(DB::table('orders')->where('id',1)->value('customer_eligible_at'));
        $this->assertFalse($this->service()->status(User::find(1))['can_checkout']);
    }
    public function test_other_customer_cannot_retry_existing_order(): void {
        $this->initialOrders(false);
        DB::table('users')->insert(['id'=>2]);
        DB::table('offline_payments')->insert(['order_id'=>1,'payment_info'=>'{}']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(\App\Services\CustomerOrderPaymentRetryService::class)->submit(2,1,'key','ref','proof');
    }
    public function test_unrejected_order_cannot_accept_new_payment_proof(): void {
        $this->initialOrders(false);
        DB::table('offline_payments')->insert(['order_id'=>1,'payment_info'=>'{}']);
        $this->expectException(ValidationException::class);
        app(\App\Services\CustomerOrderPaymentRetryService::class)->submit(1,1,'key','ref','proof');
    }
    public function test_quote_preserves_cart_snapshot(): void {
        $snapshot=[['product_id'=>17,'variant'=>'large','quantity'=>2,'price'=>25]];
        $operation=$this->service()->reserve(1,'cart-a',null,'offline_payment',$snapshot);
        $this->assertSame($snapshot,json_decode($operation->cart_snapshot,true));
        $retry=$this->service()->reserve(1,'cart-a',null,'offline_payment',[['price'=>50]]);
        $this->assertSame($snapshot,json_decode($retry->cart_snapshot,true));
    }

    public function test_expired_standard_reservation_cannot_start_another_payment(): void {
        DB::table('users')->where('id',1)->update(['activation_status'=>'active','onboarding_journey'=>'standard']);
        $this->subscribe();$this->service()->reserve(1,'cart-a');$this->travel(31)->days();
        $this->expectException(ValidationException::class);$this->service()->reserve(1,'cart-a');
    }

    public function test_historical_paid_customer_insurance_hold_is_reconciled_without_financial_change(): void {
        DB::table('users')->where('id',1)->update(['onboarding_journey'=>'legacy','activation_status'=>'active']);
        DB::table('orders')->insert(['id'=>1,'customer_id'=>1,'payment_status'=>'paid','commerce_flow_status'=>'customer_insurance_pending']);
        $this->artisan('customers:reconcile-purchase-eligibility')->assertSuccessful();
        $this->assertNull(DB::table('orders')->value('customer_checkout_operation_id'));
        $this->artisan('customers:reconcile-purchase-eligibility',['--apply'=>true])->assertSuccessful();
        $this->artisan('customers:reconcile-purchase-eligibility',['--apply'=>true])->assertSuccessful();
        $this->assertSame(1,DB::table('customer_checkout_operations')->count());
        $this->assertSame('paid',DB::table('orders')->value('payment_status'));
        $this->assertSame('pending_admin_review',DB::table('orders')->value('commerce_flow_status'));
        $this->assertSame(['monthly_subscription'],json_decode(DB::table('orders')->value('customer_pending_reasons'),true));
        $this->assertNull(DB::table('orders')->value('customer_eligible_at'));
        $this->subscribe();$this->service()->reevaluateCustomer(1);
        $this->assertSame('preparing',DB::table('orders')->value('customer_fulfillment_status'));
    }

    public function test_payment_retry_endpoint_requires_authentication(): void {
        $this->postJson('http://localhost/api/v1/customer/order/1/payment-proof/retry')->assertUnauthorized();
    }

    public function test_qualified_standard_customer_keeps_existing_cash_on_delivery_behavior(): void {
        DB::table('users')->where('id',1)->update(['activation_status'=>'active','onboarding_journey'=>'standard']);$this->subscribe();
        $op=$this->service()->reserve(1,'cart-cod',null,'cash_on_delivery');
        DB::table('orders')->insert(['id'=>1,'customer_id'=>1,'payment_status'=>'unpaid','payment_method'=>'cash_on_delivery','customer_checkout_operation_id'=>$op->id]);
        $this->service()->reevaluateCustomer(1);
        $this->assertSame('preparing',DB::table('orders')->value('customer_fulfillment_status'));
        $this->assertSame('unpaid',DB::table('orders')->value('payment_status'));
    }
    public function test_checkout_first_initial_order_still_requires_full_payment_after_activation(): void {
        DB::table('users')->where('id',1)->update(['activation_status'=>'active']);$this->subscribe();
        $this->expectException(ValidationException::class);$this->service()->reserve(1,'cart-cod',null,'cash_on_delivery');
    }

    public function test_investigation_still_blocks_fulfillment_after_customer_qualified(): void {
        $this->initialOrders();
        DB::table('users')->where('id',1)->update(['activation_status'=>'active']);
        $this->subscribe();$this->preparing();
        $qualifiedAt=DB::table('orders')->where('id',1)->value('customer_eligible_at');
        DB::table('orders')->where('id',1)->update(['commerce_flow_status'=>'under_investigation']);
        try {
            $this->service()->assertCanFulfill(\App\Models\Order::find(1));
            $this->fail('An investigation must block fulfillment of a previously qualified order.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertSame($qualifiedAt,DB::table('orders')->where('id',1)->value('customer_eligible_at'));
        DB::table('orders')->where('id',1)->update(['commerce_flow_status'=>'pending_admin_review']);
        $this->travel(31)->days();
        $this->service()->assertCanFulfill(\App\Models\Order::find(1));
        $this->assertSame($qualifiedAt,DB::table('orders')->where('id',1)->value('customer_eligible_at'));
    }
}
