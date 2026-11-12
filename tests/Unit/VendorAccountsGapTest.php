<?php

namespace Tests\Unit;

use App\Enums\GlobalConstant;
use App\Models\{Admin, AdminRole, Seller, SellerPackageSubscription, VendorExtraAdPurchase};
use App\Services\{SellerBalanceDepositService, SellerLedgerService, VendorDebtService, VendorExtraAdService};
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VendorAccountsGapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['vendor_advertising.enabled' => false]);
        Schema::create('sellers', function (Blueprint $t) {
            $t->id(); $t->string('f_name')->nullable(); $t->string('l_name')->nullable(); $t->string('phone')->nullable();
            $t->string('email')->nullable(); $t->string('status')->default('approved'); $t->timestamps();
        });
        Schema::create('shops', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->string('name')->nullable(); });
        Schema::create('seller_wallets', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id');
            foreach (['total_earning','pending_withdraw','withdrawn','commission_given','delivery_charge_earned','collected_cash','total_tax_collected'] as $column) $t->decimal($column, 40, 20)->default(0);
            $t->timestamps();
        });
        Schema::create('seller_ledger_entries', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->string('bucket'); $t->string('direction'); $t->decimal('amount', 40, 20);
            $t->string('event_type'); $t->string('reporting_category')->nullable(); $t->string('group_key'); $t->char('idempotency_key', 64)->unique();
            $t->string('reference_type')->nullable(); $t->unsignedBigInteger('reference_id')->nullable(); $t->string('reference_code')->nullable();
            $t->json('metadata')->nullable(); $t->char('previous_hash', 64)->nullable(); $t->char('entry_hash', 64); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('currencies', function (Blueprint $t) { $t->id(); $t->string('code'); $t->decimal('exchange_rate', 12, 4)->default(1); $t->string('symbol')->nullable(); });
        Schema::create('business_settings', function (Blueprint $t) { $t->id(); $t->string('type')->unique(); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('seller_packages', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->decimal('package_price', 40, 20)->default(0);
            $t->unsignedInteger('product_limit')->default(0); $t->boolean('status')->default(true); $t->integer('sort_order')->default(0); $t->timestamps();
        });
        Schema::create('seller_package_subscriptions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('seller_package_id')->nullable(); $t->string('package_name')->nullable();
            $t->decimal('paid_package_price', 40, 20)->default(0); $t->unsignedInteger('product_limit')->default(0);
            $t->string('status')->default('pending'); $t->string('payment_status')->default('unpaid');
            $t->timestamp('starts_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('seller_package_transactions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('seller_package_subscription_id')->nullable();
            $t->unsignedBigInteger('seller_package_id')->nullable(); $t->string('transaction_id')->unique(); $t->string('transaction_type');
            $t->string('quota_type')->nullable(); $t->integer('credit')->default(0); $t->integer('debit')->default(0); $t->integer('balance_after')->default(0);
            $t->decimal('paid_amount', 40, 20)->default(0); $t->string('reference')->nullable(); $t->unsignedBigInteger('created_by_admin_id')->nullable();
            $t->json('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('seller_balance_deposits', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->decimal('amount', 40, 20); $t->string('currency_code')->default('EGP');
            $t->string('source'); $t->string('status'); $t->string('payment_method')->nullable(); $t->string('transaction_reference')->nullable();
            $t->text('payment_note')->nullable(); $t->text('review_note')->nullable(); $t->text('rejection_reason')->nullable(); $t->json('metadata')->nullable();
            $t->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamp('paid_at')->nullable();
            $t->timestamp('reversed_at')->nullable(); $t->unsignedBigInteger('reversed_by_admin_id')->nullable(); $t->string('idempotency_key')->unique(); $t->timestamps();
        });
        Schema::create('vendor_debt_charges', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->string('kind'); $t->string('source_type'); $t->string('source_id');
            $t->char('idempotency_key', 64)->unique(); $t->char('fingerprint', 64); $t->decimal('amount', 24, 8); $t->decimal('wallet_collected', 24, 8);
            $t->decimal('debt_assessed', 24, 8); $t->decimal('outstanding', 24, 8); $t->json('rule_snapshot'); $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('assessed_at'); $t->timestamps();
        });
        Schema::create('vendor_deposit_allocations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('deposit_id')->unique(); $t->unsignedBigInteger('seller_id'); $t->decimal('gross_amount', 24, 8);
            $t->decimal('debt_recovered', 24, 8); $t->decimal('net_wallet_credit', 24, 8); $t->decimal('remaining_debt', 24, 8);
            $t->timestamp('confirmed_at'); $t->timestamp('reversed_at')->nullable(); $t->timestamps();
        });
        Schema::create('vendor_debt_repayments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('charge_id'); $t->unsignedBigInteger('deposit_id')->nullable();
            $t->string('collection_type'); $t->decimal('amount', 24, 8); $t->char('event_key', 64)->unique(); $t->timestamp('reversed_at')->nullable(); $t->timestamps();
        });
        Schema::create('vendor_debt_events', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('charge_id')->nullable(); $t->unsignedBigInteger('deposit_id')->nullable();
            $t->string('event_type'); $t->decimal('amount', 24, 8); $t->char('event_key', 64)->unique(); $t->timestamp('created_at');
        });
        Schema::create('vendor_extra_ad_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary(); $t->decimal('unit_price', 14, 2)->nullable(); $t->unsignedInteger('max_slots')->default(100); $t->boolean('enabled')->default(false); $t->timestamps();
        });
        Schema::create('vendor_extra_ad_purchases', function (Blueprint $t) {
            $t->id(); $t->string('admin_reference', 40)->unique(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('seller_package_subscription_id');
            $t->uuid('request_key'); $t->unsignedInteger('quantity'); $t->decimal('unit_price', 14, 2); $t->decimal('total_price', 14, 2);
            $t->string('currency', 3)->default('EGP'); $t->string('payment_method', 32); $t->string('status', 32); $t->json('terms'); $t->json('payment_details')->nullable();
            $t->string('payment_reference', 191)->nullable(); $t->unsignedBigInteger('reviewed_by')->nullable(); $t->text('review_note')->nullable();
            $t->timestamp('paid_at')->nullable(); $t->timestamp('expires_at'); $t->timestamps(); $t->unique(['seller_id', 'request_key']);
        });
        Schema::create('vendor_advertising_slots', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('seller_package_subscription_id'); $t->unsignedInteger('position');
            $t->unsignedBigInteger('product_id')->nullable(); $t->unsignedBigInteger('extra_ad_purchase_id')->nullable();
            $t->timestamp('starts_at'); $t->timestamp('expires_at'); $t->timestamps();
            $t->unique(['seller_package_subscription_id', 'position']);
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id')->nullable(); $t->string('customer_event_key')->nullable()->unique();
            $t->string('sent_by')->nullable(); $t->string('sent_to')->nullable(); $t->string('title')->nullable(); $t->text('description')->nullable();
            $t->boolean('status')->default(1); $t->integer('notification_count')->default(1); $t->string('image')->nullable(); $t->timestamps();
        });
        DB::table('currencies')->insert(['id' => 1, 'code' => 'EGP', 'symbol' => 'EGP']);
        foreach (['currency_model' => 'single_currency', 'system_default_currency' => '1'] as $type => $value) {
            DB::table('business_settings')->insert(['type' => $type, 'value' => $value]);
        }
        DB::table('sellers')->insert(['id' => 1, 'f_name' => 'تاجر', 'l_name' => 'اختبار', 'phone' => '01000000001', 'email' => 'vendor@example.test', 'status' => 'approved']);
        DB::table('sellers')->insert(['id' => 2, 'f_name' => 'آخر', 'l_name' => 'تاجر', 'phone' => '01000000002', 'email' => 'other@example.test', 'status' => 'approved']);
        DB::table('shops')->insert(['seller_id' => 1, 'name' => 'متجر الاختبار']);
        DB::table('vendor_extra_ad_settings')->insert(['id' => 1, 'unit_price' => 100, 'max_slots' => 100, 'enabled' => 1]);
    }

    public function test_role_catalog_exposes_view_and_manage_separately(): void
    {
        $permissions = GlobalConstant::EMPLOYEE_ROLE_MODULE_PERMISSION;
        $this->assertSame('عرض ماليات التجار', $permissions['vendor_financial_view']);
        $this->assertSame('إدارة مدفوعات وديون واشتراكات التجار', $permissions['vendor_financial_manage']);
        $this->assertNotSame($permissions['vendor_financial_view'], $permissions['vendor_financial_manage']);
    }

    public function test_view_role_can_open_finance_but_cannot_post_and_customer_id_is_rejected(): void
    {
        $controller = app(\App\Http\Controllers\Admin\Vendor\VendorAccountsController::class);
        session()->setPreviousUrl('/admin/vendors/accounts/payments');
        $this->act(2, '["people"]');
        try { $controller->fund($this->fundRequest(1, 'M000001')); $this->fail('People role posted'); }
        catch (HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        try { $controller->index(\Illuminate\Http\Request::create('/admin/vendors/accounts/payments', 'GET', ['seller_id' => 1]), 'payments'); $this->fail('People role opened payments'); }
        catch (HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }

        $this->act(3, '["people","vendor_financial_view"]');
        $view = $controller->index(\Illuminate\Http\Request::create('/admin/vendors/accounts/payments', 'GET', ['seller_id' => 1]), 'payments');
        $this->assertSame(1, $view->getData()['seller']->id);
        try { $controller->fund($this->fundRequest(1, 'M000001')); $this->fail('View role posted'); }
        catch (HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }

        $this->act(1, '[]', 1);
        try { $controller->fund($this->fundRequest(999, 'M000999')); $this->fail('Unknown customer id was accepted as a merchant'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('seller_id', $error->errors()); }
        DB::table('sellers')->where('id', 1)->update(['status' => 'suspended']);
        $response = $controller->fund($this->fundRequest(1, 'M000001', 'SUSPEND-REF'));
        $this->assertSame('حساب التاجر غير نشط.', $response->getSession()->get('errors')->first());
        try { $controller->fund($this->fundRequest(1, 'C000001', 'WRONG-REF')); $this->fail('Customer reference was accepted'); }
        catch (HttpException $error) { $this->assertSame(422, $error->getStatusCode()); }
        $this->assertSame(0, DB::table('seller_balance_deposits')->count());
    }

    public function test_fifo_partial_and_duplicate_reference_do_not_cross_merchants(): void
    {
        $debt = app(VendorDebtService::class);
        $older = $debt->createAdministrativeDebt(1, '60.00', 'الأقدم', (string) Str::uuid(), 9);
        $newer = $debt->createAdministrativeDebt(1, '50.00', 'الأحدث', (string) Str::uuid(), 9);
        $other = $debt->createAdministrativeDebt(2, '25.00', 'تاجر آخر', (string) Str::uuid(), 9);
        $service = app(SellerBalanceDepositService::class);
        $key = (string) Str::uuid();
        $first = $service->confirmAdministrativePayment(Seller::findOrFail(1), '40.00', 'BANK-40', $key, 9, 'شحن جزئي');
        $repeat = $service->confirmAdministrativePayment(Seller::findOrFail(1), '40.00', 'BANK-40', $key, 9, 'شحن جزئي');
        $this->assertSame($first->id, $repeat->id);
        $this->assertSame(20.0, (float) DB::table('vendor_debt_charges')->find($older->id)->outstanding);
        $this->assertSame(50.0, (float) DB::table('vendor_debt_charges')->find($newer->id)->outstanding);
        $this->assertSame(0.0, app(SellerLedgerService::class)->summary(1)[SellerLedgerService::OPERATING]);
        $second = $service->confirmAdministrativePayment(Seller::findOrFail(1), '100.00', 'BANK-100', (string) Str::uuid(), 9, 'باقي الدين والصافي');
        $this->assertSame(0.0, (float) DB::table('vendor_debt_charges')->find($older->id)->outstanding);
        $this->assertSame(0.0, (float) DB::table('vendor_debt_charges')->find($newer->id)->outstanding);
        $this->assertSame(30.0, (float) $debt->depositReceipt($second->id)['net_wallet_credit']);
        $this->assertSame(30.0, app(SellerLedgerService::class)->summary(1)[SellerLedgerService::OPERATING]);
        $this->assertSame(25.0, (float) DB::table('vendor_debt_charges')->find($other->id)->outstanding);
        try {
            $service->confirmAdministrativePayment(Seller::findOrFail(2), '25.00', 'BANK-40', (string) Str::uuid(), 9, 'مرجع مكرر');
            $this->fail('Reference was reused for another merchant');
        } catch (DomainException $error) {
            $this->assertStringContainsString('مستخدم', $error->getMessage());
        }
        $this->assertSame(0.0, app(SellerLedgerService::class)->summary(2)[SellerLedgerService::OPERATING]);
    }

    public function test_extra_ad_uses_payment_wallet_despite_debt_and_retries_once(): void
    {
        config(['vendor_advertising.enabled' => true]);
        $this->subscription();
        app(SellerLedgerService::class)->post(1, 'funding', 'ad-operating', [['bucket' => 'operating', 'direction' => 'credit', 'amount' => 100]]);
        app(SellerLedgerService::class)->post(1, 'funding', 'ad-other-buckets', [
            ['bucket' => 'available', 'direction' => 'credit', 'amount' => 500],
            ['bucket' => 'order_insurance_credit', 'direction' => 'credit', 'amount' => 500],
        ]);
        $debt = app(VendorDebtService::class)->createAdministrativeDebt(1, '40.00', 'لا يمنع الإعلان', (string) Str::uuid(), 9);
        $key = (string) Str::uuid();
        $service = app(VendorExtraAdService::class);
        $first = $service->purchase(Seller::findOrFail(1), $this->adInput($key), null, 9);
        $again = $service->purchase(Seller::findOrFail(1), $this->adInput($key), null, 9);
        $this->assertSame($first->id, $again->id);
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame(9, (int) $first->reviewed_by);
        $this->assertSame(0.0, app(SellerLedgerService::class)->summary(1)[SellerLedgerService::OPERATING]);
        $this->assertSame(500.0, app(SellerLedgerService::class)->summary(1)['available']);
        $this->assertSame(500.0, app(SellerLedgerService::class)->summary(1)['order_insurance_credit']);
        $this->assertSame(40.0, (float) DB::table('vendor_debt_charges')->find($debt->id)->outstanding);
        $this->assertSame(1, DB::table('seller_ledger_entries')->where('event_type', 'extra_ad_purchase')->count());
        $this->assertSame(1, VendorExtraAdPurchase::count());
    }

    public function test_extra_ad_insufficient_sales_wallet_suspended_and_failed_debit_write_nothing(): void
    {
        config(['vendor_advertising.enabled' => true]);
        $subscription = $this->subscription();
        $ledger = app(SellerLedgerService::class);
        $ledger->post(1, 'funding', 'sales-only', [['bucket' => 'available', 'direction' => 'credit', 'amount' => 500]]);
        try {
            app(VendorExtraAdService::class)->purchase(Seller::findOrFail(1), $this->adInput((string) Str::uuid()), null, 9);
            $this->fail('Sales wallet bought an extra ad');
        } catch (DomainException $error) {
            $this->assertStringContainsString('رصيد المدفوعات غير كاف', $error->getMessage());
        }
        $this->assertSame(0, VendorExtraAdPurchase::count());
        $this->assertSame(500.0, $ledger->summary(1)['available']);

        $ledger->post(1, 'funding', 'small-operating', [['bucket' => 'operating', 'direction' => 'credit', 'amount' => 10]]);
        $this->act(1, '[]', 1);
        session()->setPreviousUrl('/admin/vendors/accounts/subscriptions');
        $key = (string) Str::uuid();
        $response = app(\App\Http\Controllers\Admin\Vendor\VendorAccountsController::class)->extraAd($this->adRequest($key));
        $this->assertStringContainsString('رصيدكم الحالي لا يسمح', $response->getSession()->get('errors')->first());
        $this->assertSame('رصيدكم الحالي لا يسمح', DB::table('notifications')->value('description'));
        $this->assertSame(0, VendorExtraAdPurchase::count());
        $this->assertSame(10.0, $ledger->summary(1)['operating']);

        $ledger->post(1, 'funding', 'enough-operating', [['bucket' => 'operating', 'direction' => 'credit', 'amount' => 90]]);
        DB::statement("CREATE TRIGGER gap_reject_extra_ad BEFORE INSERT ON seller_ledger_entries WHEN NEW.event_type = 'extra_ad_purchase' BEGIN SELECT RAISE(ABORT, 'synthetic extra ad failure'); END");
        try {
            app(VendorExtraAdService::class)->purchase(Seller::findOrFail(1), $this->adInput((string) Str::uuid()), null, 9);
            $this->fail('Failed extra-ad debit was committed');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('synthetic extra ad failure', $error->getMessage());
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS gap_reject_extra_ad');
        }
        $this->assertSame(0, VendorExtraAdPurchase::count());
        $this->assertSame(100.0, $ledger->summary(1)['operating']);
        $this->assertSame($subscription->id, SellerPackageSubscription::find($subscription->id)->id);

        DB::table('sellers')->where('id', 1)->update(['status' => 'suspended']);
        try {
            app(VendorExtraAdService::class)->purchase(Seller::findOrFail(1), $this->adInput((string) Str::uuid()), null, 9);
            $this->fail('Suspended merchant bought an extra ad');
        } catch (DomainException $error) {
            $this->assertStringContainsString('نشط', $error->getMessage());
        }
        $this->assertSame(0, VendorExtraAdPurchase::count());
    }

    public function test_selected_vendor_stays_on_clear_search_profile_nav_and_extra_ad_history_names_staff(): void
    {
        config(['vendor_advertising.enabled' => true]);
        $subscription = $this->subscription();
        VendorExtraAdPurchase::create([
            'admin_reference' => 'VAD-TEST-1', 'seller_id' => 1, 'seller_package_subscription_id' => $subscription->id,
            'request_key' => (string) Str::uuid(), 'quantity' => 1, 'unit_price' => 100, 'total_price' => 100,
            'currency' => 'EGP', 'payment_method' => 'operating_balance', 'status' => 'paid', 'terms' => ['duration_policy' => 'subscription_expiry'],
            'reviewed_by' => 15, 'expires_at' => now()->addDays(10),
        ]);
        $this->act(3, '["people","vendor_financial_view"]');
        $request = \Illuminate\Http\Request::create('/admin/vendors/accounts/subscriptions', 'GET', ['seller_id' => 1, 'q' => 'قديم']);
        app()->instance('request', $request);
        $view = app(\App\Http\Controllers\Admin\Vendor\VendorAccountsController::class)->index($request, 'subscriptions');
        $source = file_get_contents(resource_path('views/admin-views/vendor/accounts/index.blade.php'));
        $start = strpos($source, "@section('content')") + strlen("@section('content')");
        $end = strrpos($source, '@endsection');
        $html = \Illuminate\Support\Facades\Blade::render(substr($source, $start, $end - $start), $view->getData() + ['errors' => new \Illuminate\Support\ViewErrorBag()]);
        $this->assertStringContainsString('seller_id=1', $html);
        $this->assertStringContainsString('إدارة #15', $html);
        $this->assertStringContainsString('أقسام التاجر المحدد', $html);
        $this->assertStringContainsString('مسح البحث', $html);
        $profile = file_get_contents(resource_path('views/admin-views/vendor/view.blade.php'));
        $nav = file_get_contents(resource_path('views/admin-views/vendor/accounts/context-nav.blade.php'));
        app('blade.compiler')->compileString($source);
        app('blade.compiler')->compileString($profile);
        app('blade.compiler')->compileString($nav);
        $this->assertStringContainsString("admin-views.vendor.accounts.context-nav", $profile);
        $this->assertStringContainsString('seller_id', $nav);
    }

    private function subscription(): SellerPackageSubscription
    {
        return SellerPackageSubscription::create([
            'seller_id' => 1, 'seller_package_id' => null, 'package_name' => 'الخطة', 'paid_package_price' => 100,
            'product_limit' => 1, 'status' => 'active', 'payment_status' => 'paid',
            'starts_at' => now()->subDay(), 'expires_at' => now()->addDays(20),
        ]);
    }

    private function adInput(string $key): array
    {
        return ['subscription_id' => SellerPackageSubscription::first()->id, 'quantity' => 1, 'expected_unit_price' => '100.00', 'request_key' => $key, 'payment_method' => 'operating_balance'];
    }

    private function adRequest(string $key): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/admin/vendors/accounts/extra-ad', 'POST', [
            'seller_id' => 1, 'subscription_id' => SellerPackageSubscription::first()->id, 'quantity' => 1,
            'expected_unit_price' => '100.00', 'request_key' => $key, 'confirmation' => 'M000001',
        ]);
        app()->instance('request', $request);
        return $request;
    }

    private function fundRequest(int $sellerId, string $confirmation, string $reference = 'REF-1'): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/admin/vendors/accounts/fund', 'POST', [
            'seller_id' => $sellerId, 'amount' => '10.00', 'reason' => 'اختبار', 'payment_reference' => $reference,
            'request_key' => (string) Str::uuid(), 'confirmation' => $confirmation, 'verified' => '1',
        ]);
        app()->instance('request', $request);
        return $request;
    }

    private function act(int $id, string $modules, int $roleId = 2): void
    {
        $admin = new Admin();
        $admin->id = $id;
        $admin->admin_role_id = $roleId;
        $admin->setRelation('role', new AdminRole(['status' => 1, 'module_access' => $modules]));
        auth('admin')->setUser($admin);
    }
}
