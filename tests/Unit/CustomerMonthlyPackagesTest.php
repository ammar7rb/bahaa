<?php

namespace Tests\Unit;

use App\Models\CustomerMonthlySubscription;
use App\Models\CustomerPurchasePackage;
use App\Services\CustomerMonthlyAllowanceService;
use App\Services\CustomerMonthlySubscriptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CustomerMonthlyPackagesTest extends \Tests\TestCase
{
    protected function setUp(): void
    {

        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        Schema::create('users',function(Blueprint $t){$t->id();$t->decimal('wallet_balance',24,3)->default(0);$t->boolean('is_active')->default(true);$t->timestamps();});
        Schema::create('currencies',function(Blueprint $t){$t->id();$t->string('code');$t->decimal('exchange_rate',18,6)->default(1);$t->string('symbol');$t->boolean('status')->default(true);$t->timestamps();});
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value');$t->timestamps();});
        Schema::create('wallet_transactions',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');foreach(['transaction_id','reference','transaction_type','payment_method'] as $s)$t->string($s);foreach(['credit','debit','admin_bonus','balance'] as $s)$t->decimal($s,24,3)->default(0);$t->timestamps();});
        Schema::create('offline_payment_methods',function(Blueprint $t){$t->id();$t->string('method_name');$t->string('payment_channel');$t->boolean('status')->default(true);$t->json('method_fields')->nullable();$t->json('method_informations')->nullable();$t->timestamps();});
        (require base_path('database/migrations/2026_10_10_200000_create_customer_monthly_subscriptions.php'))->up();
        DB::table('currencies')->insert(['id'=>1,'code'=>'EGP','symbol'=>'EGP']);
        foreach(['currency_model'=>'single_currency','system_default_currency'=>'1','wallet_status'=>'1','offline_payment'=>'{"status":1}'] as $key=>$value)DB::table('business_settings')->insert(['type'=>$key,'value'=>$value]);
        DB::table('users')->insert(['id'=>1,'wallet_balance'=>300,'is_active'=>1]);
        DB::table('users')->insert(['id'=>2,'wallet_balance'=>300,'is_active'=>1]);
        DB::table('monthly_subscription_prices')->insert(['id'=>1,'customer_price'=>100,'vendor_price'=>250,'currency_code'=>'EGP']);
        Schema::create('customer_purchase_packages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id')->nullable(); $t->string('name'); $t->text('description')->nullable();
            $t->decimal('package_price', 18, 3); $t->decimal('purchase_limit', 18, 3); $t->boolean('status')->default(true);
            $t->boolean('is_custom')->default(false); $t->integer('sort_order')->default(0); $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_12_100000_customer_monthly_package_terms.php'))->up();
        (require base_path('database/migrations/2026_10_12_160000_snapshot_customer_increase_quantity.php'))->up();
        (require base_path('database/migrations/2026_10_12_130000_customer_allowance_restorations.php'))->up();
        CustomerPurchasePackage::create(['name' => 'Silver', 'package_price' => 100, 'purchase_limit' => 3000]);
        CustomerPurchasePackage::create(['name' => 'Gold', 'package_price' => 200, 'purchase_limit' => 5000]);
    }

    public function test_admin_can_delete_unused_offer_but_cannot_delete_payment_history(): void
    {
        $controller = app(\App\Http\Controllers\Admin\Customer\MonthlyPackageController::class);
        $unused = DB::table('customer_credit_offers')->insertGetId(['name'=>'Unused', 'price'=>50, 'allowance'=>1000, 'active'=>true]);
        $controller->deleteOffer($unused);
        $this->assertFalse(DB::table('customer_credit_offers')->where('id',$unused)->exists());
        $service = app(CustomerMonthlySubscriptionService::class);
        $service->payFromWallet(1,$service->checkout(1,(string) Str::uuid(),1)->id);
        $used = DB::table('customer_credit_offers')->insertGetId(['name'=>'Used', 'price'=>50, 'allowance'=>1000, 'active'=>true]);
        $quote = $service->checkout(1,(string) Str::uuid(),null,$used);
        try { $controller->deleteOffer($used); $this->fail('Referenced offer was deleted'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('offer',$e->errors()); }
        $this->assertTrue(DB::table('customer_credit_offers')->where('id',$used)->exists());
        $this->assertEquals($used,DB::table('customer_subscription_payments')->where('id',$quote->id)->value('offer_id'));
        $controller->offer(\Illuminate\Http\Request::create('/', 'POST', ['name'=>'Changed', 'price'=>75, 'allowance'=>2000, 'active'=>0]),$used);
        $this->assertEquals(0,DB::table('customer_credit_offers')->where('id',$used)->value('active'));
        $this->assertEquals(50,$quote->fresh()->quoted_amount);
        $this->assertEquals(1000,$quote->fresh()->granted_allowance);
        $service->payFromWallet(1,$quote->id);
        $this->assertEquals(4000,$service->status(1)['allowance']['available_limit']);
    }

    public function test_package_terms_are_snapshotted_and_wallet_is_only_debited_once(): void
    {
        $service = app(CustomerMonthlySubscriptionService::class);
        $quote = $service->checkout(1, (string) Str::uuid(), 1);
        CustomerPurchasePackage::whereKey(1)->update(['name' => 'Changed', 'package_price' => 250, 'purchase_limit' => 999]);
        $service->payFromWallet(1, $quote->id); $service->payFromWallet(1, $quote->id);
        $this->assertEquals(200, DB::table('users')->where('id', 1)->value('wallet_balance'));
        $this->assertSame(1, DB::table('wallet_transactions')->count());
        $this->assertSame('Silver', $service->status(1)['package_name']);
        $this->assertEquals(3000, $service->status(1)['allowance']['available_limit']);
    }

    public function test_increase_adds_capacity_only_and_expires_with_package(): void
    {
        $service = app(CustomerMonthlySubscriptionService::class);
        $service->payFromWallet(1, $service->checkout(1, (string) Str::uuid(), 1)->id);
        $expiry = CustomerMonthlySubscription::first()->expires_at;
        DB::table('customer_credit_offers')->insert(['id' => 1, 'name' => 'More', 'price' => 50, 'allowance' => 5000]);
        $quote = $service->checkout(1, (string) Str::uuid(), null, 1);
        $service->payFromWallet(1, $quote->id);
        $this->assertEquals(150, DB::table('users')->where('id', 1)->value('wallet_balance'));
        $this->assertEquals(8000, $service->status(1)['allowance']['available_limit']);
        $this->assertTrue($expiry->equalTo(CustomerMonthlySubscription::first()->expires_at));
        $this->travel(30)->days();
        $this->assertEquals(0, $service->status(1)['allowance']['available_limit']);
        $this->assertSame(1, CustomerMonthlySubscription::count());
        $this->travelBack();
    }

    public function test_early_same_package_renewal_is_blocked_and_upgrade_costs_full_price(): void
    {
        $service = app(CustomerMonthlySubscriptionService::class);
        $service->payFromWallet(1, $service->checkout(1, (string) Str::uuid(), 1)->id);
        try { $service->checkout(1, (string) Str::uuid(), 1); $this->fail(); }
        catch (\DomainException $e) { $this->assertStringContainsString('تجديد مبكر', $e->getMessage()); }
        $this->travel(5)->days();
        $quote = $service->checkout(1, (string) Str::uuid(), 2);
        $this->assertSame('upgrade', $quote->purchase_kind);
        $service->payFromWallet(1, $quote->id);
        $this->assertEquals(0, DB::table('users')->where('id', 1)->value('wallet_balance'));
        $this->assertSame(30, $service->status(1)['remaining_days']);
        $this->assertSame('Gold', $service->status(1)['package_name']);
        $this->travelBack();
    }

    public function test_increase_multiples_snapshot_price_capacity_and_debit_once(): void
    {
        $service = app(CustomerMonthlySubscriptionService::class);
        $service->payFromWallet(1, $service->checkout(1, (string) Str::uuid(), 1)->id);
        $offer = DB::table('customer_credit_offers')->insertGetId(['name' => 'زيادة الحد', 'price' => 50, 'allowance' => 5000, 'active' => true]);
        $quote = $service->checkout(1, (string) Str::uuid(), null, $offer, 3);
        $this->assertSame(3, $quote->offer_quantity);
        $this->assertEquals(150, $quote->quoted_amount);
        $this->assertEquals(15000, $quote->granted_allowance);
        DB::table('customer_credit_offers')->where('id', $offer)->update(['price' => 99, 'allowance' => 100]);
        $service->payFromWallet(1, $quote->id); $service->payFromWallet(1, $quote->id);
        $this->assertEquals(50, DB::table('users')->where('id', 1)->value('wallet_balance'));
        $this->assertEquals(18000, $service->status(1)['allowance']['granted_limit']);
        $this->assertSame(2, DB::table('wallet_transactions')->count());
        $this->expectException(\DomainException::class);
        $service->checkout(1, (string) Str::uuid(), null, $offer, 6);
    }

    public function test_legacy_subscriber_keeps_history_but_needs_new_package(): void
    {
        CustomerMonthlySubscription::create(['customer_id' => 1, 'payment_id' => 987, 'starts_at' => now(), 'expires_at' => now()->addDays(20)]);
        $this->assertFalse(app(CustomerMonthlySubscriptionService::class)->status(1)['active']);
        $this->assertSame(20, (int) now()->diffInDays(CustomerMonthlySubscription::first()->expires_at));
    }

    public function test_projection_boundaries_and_guest_cap_do_not_change_wallet_or_usage(): void
    {
        $service = app(CustomerMonthlySubscriptionService::class);
        $service->payFromWallet(1, $service->checkout(1, (string) Str::uuid(), 1)->id);
        $limits = app(CustomerMonthlyAllowanceService::class);
        $this->assertSame('green', $limits->summary(1, 1499)['indicator']);
        $this->assertSame('orange', $limits->summary(1, 1500)['indicator']);
        $this->assertSame('red', $limits->summary(1, 2700)['indicator']);
        $this->assertFalse($limits->summary(0, 3001, true)['can_checkout']);
        $this->assertTrue($limits->summary(0, 3000, true)['can_checkout']);
        $this->assertEquals(0, CustomerMonthlySubscription::first()->committed_allowance);
        $this->assertEquals(200, DB::table('users')->where('id', 1)->value('wallet_balance'));
    }

    public function test_customer_coupon_api_rejects_coupon_and_backend_rejects_over_limit_cart(): void
    {
        session()->put(['coupon_discount'=>100,'coupon_code'=>'OLD']);
        $request = \Illuminate\Http\Request::create('/coupon/apply','POST',['code'=>'ANY']);
        $request->headers->set('Accept','application/json');
        $response = app(\App\Http\Controllers\Web\CouponController::class)->apply($request);
        $this->assertSame(422,$response->getStatusCode());
        $this->assertFalse(session()->has('coupon_discount'));
        $service = app(CustomerMonthlySubscriptionService::class);
        $service->payFromWallet(1,$service->checkout(1,(string) Str::uuid(),1)->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CustomerMonthlyAllowanceService::class)->assertCart(1,3001);
    }

    public function test_order_reservation_and_refund_retry_never_count_twice(): void
    {
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('customer_id'); });
        Schema::create('order_details', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('order_id'); $t->decimal('price',18,3); $t->integer('qty'); $t->decimal('discount',18,3)->default(0); });
        $service = app(CustomerMonthlySubscriptionService::class);
        $service->payFromWallet(1, $service->checkout(1, (string) Str::uuid(), 1)->id);
        DB::table('orders')->insert(['id'=>1,'customer_id'=>1]);
        DB::table('order_details')->insert(['order_id'=>1,'price'=>1000,'qty'=>2]);
        $order = \App\Models\Order::findOrFail(1); $limits = app(CustomerMonthlyAllowanceService::class);
        $this->assertTrue($limits->reserveOrder($order)); $this->assertTrue($limits->reserveOrder($order));
        $this->assertEquals(1000, $limits->summary(1)['available_limit']);
        $limits->restoreOrder($order, 500, 'refund:one'); $limits->restoreOrder($order, 500, 'refund:one');
        $this->assertEquals(1500, $limits->summary(1)['available_limit']);
        $this->assertSame(1, DB::table('customer_monthly_limit_entries')->count());
        $this->assertSame(1, DB::table('customer_monthly_limit_restorations')->count());
    }

    public function test_external_transfer_reaches_admin_notification_source_and_is_private_to_customer(): void
    {
        Schema::table('users', function (Blueprint $t) { $t->string('f_name')->default('Customer'); $t->string('l_name')->default(''); $t->string('cm_firebase_token')->nullable(); });
        Schema::create('notifications', function (Blueprint $t) { $t->id(); foreach(['sent_by','sent_to','title','description','image'] as $c) $t->string($c); $t->integer('status'); $t->integer('notification_count'); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_12_120000_customer_payment_notifications.php'))->up();
        $service = app(CustomerMonthlySubscriptionService::class);
        $payment = $service->checkout(1,(string)Str::uuid(),1);
        $method = \App\Models\OfflinePaymentMethod::create(['method_name'=>'InstaPay','payment_channel'=>'instapay']);
        $service->submitOffline(1,$payment->id,$method,['payment_reference'=>'external-one'], 'proof.enc');
        $this->assertSame('pending_review', $payment->fresh()->status);
        $this->assertSame(0, CustomerMonthlySubscription::count());
        $admin = new \App\Models\Admin(['id'=>9,'admin_role_id'=>1]); $admin->setRelation('role', null);
        $this->actingAs($admin, 'admin');
        $workspace = app(\App\Services\AdminWorkspaceService::class);
        $rows = $workspace->pendingPaymentRows()->get();
        $this->assertSame(1, $rows->count());
        $this->assertStringContainsString('customer_subscription', $workspace->paymentUrl('customer_subscription_payments', $rows->first()));
        $this->assertSame(1, \App\Models\Notification::forCustomer(1)->count());
        $this->assertSame(0, \App\Models\Notification::forCustomer(2)->count());
        $service->review($payment->id,'approve',9,null); $service->review($payment->id,'approve',9,null);
        $this->assertSame(2, DB::table('notifications')->count());
        $this->assertTrue($service->status(1)['active']);
    }

    private function checkoutSchema(): void
    {
        Schema::table('users', function (Blueprint $t) { $t->string('onboarding_journey')->default('standard'); $t->string('activation_status')->default('active'); });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id'); $t->boolean('is_guest')->default(false);
            $t->string('order_status')->default('pending'); $t->string('payment_status')->default('paid');
            $t->string('payment_method')->default('offline_payment'); $t->string('activation_status')->nullable();
            $t->boolean('is_pause')->default(false); $t->string('commerce_flow_status')->default('pending_admin_review'); $t->timestamps();
        });
        Schema::create('order_details', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('order_id'); $t->decimal('price',18,3); $t->integer('qty'); $t->decimal('discount',18,3)->default(0); });
        (require base_path('database/migrations/2026_10_10_300000_add_customer_purchase_eligibility.php'))->up();
        (require base_path('database/migrations/2026_10_10_310000_preserve_customer_checkout_snapshot.php'))->up();
        (require base_path('database/migrations/2026_10_12_140000_reserve_monthly_allowance_at_checkout.php'))->up();
    }

    public function test_reserved_checkout_projects_once_and_converts_once_even_after_expiry(): void
    {
        $this->checkoutSchema(); $subscriptions = app(CustomerMonthlySubscriptionService::class);
        $subscriptions->payFromWallet(1,$subscriptions->checkout(1,(string)Str::uuid(),1)->id);
        $eligibility = app(\App\Services\CustomerPurchaseEligibilityService::class);
        $snapshot = [['cart_list'=>[['price'=>1000,'quantity'=>2,'discount'=>0]]]];
        $op = $eligibility->reserve(1,'same-cart',null,'digital_payment',$snapshot);
        $this->assertSame($op->id,$eligibility->reserve(1,'same-cart',null,'digital_payment',$snapshot)->id);
        $limit = app(CustomerMonthlyAllowanceService::class)->summary(1,2000);
        $this->assertEquals(1000,$limit['available_limit']); $this->assertEquals(1000,$limit['projected_remaining']);
        $this->travel(31)->days();
        DB::table('orders')->insert(['id'=>1,'customer_id'=>1,'customer_checkout_operation_id'=>$op->id]);
        DB::table('order_details')->insert(['order_id'=>1,'price'=>1000,'qty'=>2]);
        $order = \App\Models\Order::findOrFail(1);
        $this->assertTrue(app(CustomerMonthlyAllowanceService::class)->reserveOrder($order));
        $this->assertTrue(app(CustomerMonthlyAllowanceService::class)->reserveOrder($order));
        $this->assertEquals(2000,CustomerMonthlySubscription::first()->committed_allowance);
        $this->assertEquals(0,app(CustomerMonthlyAllowanceService::class)->summary(1)['available_limit']);
        $this->travelBack();
    }

    public function test_initial_paid_order_waits_for_activation_and_covering_package_then_releases_once(): void
    {
        $this->checkoutSchema(); DB::table('users')->where('id',1)->update(['onboarding_journey'=>'checkout_first','activation_status'=>'pending_support']);
        $eligibility = app(\App\Services\CustomerPurchaseEligibilityService::class);
        $op = $eligibility->reserve(1,'first-cart',null,'offline_payment',[['cart_list'=>[['price'=>2000,'quantity'=>1]]]]);
        DB::table('orders')->insert(['id'=>1,'customer_id'=>1,'customer_checkout_operation_id'=>$op->id]);
        DB::table('order_details')->insert(['order_id'=>1,'price'=>2000,'qty'=>1]);
        DB::table('customer_checkout_operations')->where('id',$op->id)->update(['consumed_at'=>now()]);
        $eligibility->reevaluateCustomer(1); $this->assertNull(DB::table('orders')->value('customer_eligible_at'));
        DB::table('users')->where('id',1)->update(['activation_status'=>'active']);
        CustomerPurchasePackage::whereKey(1)->update(['purchase_limit'=>1000]);
        $subscriptions = app(CustomerMonthlySubscriptionService::class); $subscriptions->payFromWallet(1,$subscriptions->checkout(1,(string)Str::uuid(),1)->id);
        $this->assertNull(DB::table('orders')->value('customer_eligible_at'));
        $this->assertStringContainsString('purchasing_limit',DB::table('orders')->value('customer_pending_reasons'));
        DB::table('customer_credit_offers')->insert(['id'=>1,'name'=>'Increase','price'=>10,'allowance'=>1000]);
        $subscriptions->payFromWallet(1,$subscriptions->checkout(1,(string)Str::uuid(),null,1)->id);
        $eligibility->reevaluateCustomer(1);
        $this->assertNotNull(DB::table('orders')->value('customer_eligible_at'));
        $this->assertSame('preparing',DB::table('orders')->value('customer_fulfillment_status'));
        $this->assertSame('pending_admin_review',DB::table('orders')->value('commerce_flow_status'));
        $this->assertSame(1,DB::table('customer_monthly_limit_entries')->count());
    }

    public function test_admin_can_edit_deactivate_and_configure_offers_without_changing_a_quote(): void
    {
        Schema::table('customer_purchase_packages', function (Blueprint $t) { $t->string('duration_unit')->nullable(); $t->integer('duration_value')->nullable(); $t->unsignedBigInteger('created_by_admin_id')->nullable(); $t->unsignedBigInteger('updated_by_admin_id')->nullable(); });
        $admin = new \App\Models\Admin(['id'=>9,'admin_role_id'=>1]); $admin->setRelation('role',null); $this->actingAs($admin,'admin');
        $quote = app(CustomerMonthlySubscriptionService::class)->checkout(1,(string)Str::uuid(),1);
        $controller = app(\App\Http\Controllers\Admin\Customer\MonthlyPackageController::class);
        $controller->save(\Illuminate\Http\Request::create('/admin/operations/monthly-packages/packages/1','POST',['name'=>'Edited','package_price'=>'250','purchase_limit'=>'7000','status'=>'0','duration_value'=>365]),1);
        $this->assertFalse(CustomerPurchasePackage::find(1)->status); $this->assertSame(30,CustomerPurchasePackage::find(1)->duration_value);
        $this->assertSame('Silver',$quote->fresh()->terms_name); $this->assertEquals(100,$quote->fresh()->quoted_amount); $this->assertEquals(3000,$quote->fresh()->granted_allowance);
        $controller->offer(\Illuminate\Http\Request::create('/admin/operations/monthly-packages/offers','POST',['name'=>'Extra','price'=>'30','allowance'=>'1000','active'=>'1']));
        $this->assertEquals(1000,DB::table('customer_credit_offers')->value('allowance'));
        $this->assertContains('module:people',app('router')->getRoutes()->getByName('admin.monthly-packages.save')->gatherMiddleware());
    }

    public function test_referenced_package_cannot_be_deleted_but_unused_package_can(): void
    {
        foreach (['customer_purchase_package_subscriptions','customer_purchase_limit_transactions','customer_activation_invoices'] as $table) Schema::create($table, function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('customer_purchase_package_id'); });
        app(CustomerMonthlySubscriptionService::class)->checkout(1,(string)Str::uuid(),1);
        $controller = app(\App\Http\Controllers\Admin\Customer\MonthlyPackageController::class);
        try { $controller->delete(1); $this->fail('Historical quote must survive'); } catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('package',$e->errors()); }
        $this->assertNotNull(CustomerPurchasePackage::find(1));
        $controller->delete(2); $this->assertNull(CustomerPurchasePackage::find(2));
    }
}

