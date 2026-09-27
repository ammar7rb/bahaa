<?php

namespace Tests\Unit;

use App\Models\CustomerBalanceDeposit;
use App\Services\CustomerBalanceDepositService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerBalanceDepositServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->decimal('wallet_balance',24,3)->default(0); $table->timestamps(); });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('transaction_id');
            $table->string('transaction_type'); $table->string('reference'); $table->string('payment_method');
            foreach (['credit','debit','balance','admin_bonus'] as $field) $table->decimal($field,24,3)->default(0);
            $table->timestamps();
        });
        Schema::create('customer_insurance_ledger_entries', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->string('entry_type');
            $table->decimal('credit',24,3); $table->decimal('debit',24,3); $table->string('reference')->unique(); $table->json('metadata'); $table->timestamps();
        });
        (require base_path('database/migrations/2026_09_06_000001_create_customer_balance_deposits_table.php'))->up();
        DB::table('users')->insert(['id'=>1, 'wallet_balance'=>100]);
        DB::table('users')->insert(['id'=>2, 'wallet_balance'=>300]);
    }

    private function deposit(string $wallet = 'purchase'): CustomerBalanceDeposit
    {
        return CustomerBalanceDeposit::create([
            'customer_id'=>1, 'wallet_type'=>$wallet, 'amount'=>50, 'submitted_amount'=>50, 'currency_code'=>'USD',
            'offline_payment_method_id'=>1, 'method_name'=>'Bank', 'payment_reference'=>uniqid('transfer'),
            'payment_proof'=>'private/receipt.png', 'request_key'=>uniqid('request'),
        ]);
    }

    public function test_approval_credits_only_the_selected_customer_purchase_balance(): void
    {
        $deposit = $this->deposit();
        $this->assertEquals(100, DB::table('users')->where('id',1)->value('wallet_balance'));
        app(CustomerBalanceDepositService::class)->review($deposit,'approve',9,'Verified');
        $this->assertEquals(150, DB::table('users')->where('id',1)->value('wallet_balance'));
        $this->assertEquals(300, DB::table('users')->where('id',2)->value('wallet_balance'));
        $this->assertSame(0, DB::table('customer_insurance_ledger_entries')->count());
        $this->assertSame(1, DB::table('wallet_transactions')->count());
        $this->assertSame('paid', $deposit->fresh()->status);
    }

    public function test_insurance_approval_never_credits_purchase_balance(): void
    {
        $deposit = $this->deposit('insurance');
        app(CustomerBalanceDepositService::class)->review($deposit,'approve',9,null);
        $this->assertEquals(100, DB::table('users')->where('id',1)->value('wallet_balance'));
        $this->assertEquals(50, DB::table('customer_insurance_ledger_entries')->sum('credit'));
        $this->assertSame(0, DB::table('wallet_transactions')->count());
    }

    public function test_repeated_approval_cannot_credit_twice(): void
    {
        $deposit = $this->deposit();
        $service = app(CustomerBalanceDepositService::class);
        $service->review($deposit,'approve',9,null);
        try { $service->review($deposit,'approve',9,null); $this->fail('Duplicate approval must be rejected'); } catch (DomainException $e) {
            $this->assertSame('deposit_already_reviewed',$e->getMessage());
        }
        $this->assertEquals(150, DB::table('users')->where('id',1)->value('wallet_balance'));
        $this->assertSame(1, DB::table('wallet_transactions')->count());
    }

    public function test_deposited_insurance_can_pay_second_stage_but_never_tax(): void
    {
        Schema::table('customer_insurance_ledger_entries', function (Blueprint $table) { $table->unsignedBigInteger('order_id')->nullable(); });
        Schema::create('post_purchase_invoices', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->unsignedBigInteger('order_id');
            $table->string('status'); $table->decimal('insurance_amount',24,4); $table->decimal('tax_amount',24,4);
            $table->decimal('total_amount',24,4); $table->decimal('paid_amount',24,4)->default(0);
            $table->json('metadata')->nullable(); $table->timestamps();
        });
        (require base_path('database/migrations/2026_08_16_000003_add_post_purchase_payment_state_and_customer_insurance_balances.php'))->up();
        $deposit = $this->deposit('insurance');
        app(CustomerBalanceDepositService::class)->review($deposit, 'approve', 9, null);
        $invoice = \App\Models\PostPurchaseInvoice::create([
            'customer_id'=>1, 'order_id'=>42, 'status'=>'pending', 'insurance_amount'=>40,
            'tax_amount'=>14, 'total_amount'=>54, 'paid_amount'=>0, 'metadata'=>[],
        ]);
        $service = app(\App\Services\PostPurchaseInvoiceService::class);
        $paid = $service->applyCustomerInsuranceBalance($invoice, \App\Models\User::find(1));
        $this->assertEquals(40, $paid->paid_amount);
        $this->assertSame('pending', $paid->status);
        $this->assertEquals(14, $service->paymentSummary($paid)['external_amount_due']);
        $this->assertEquals(10, DB::table('customer_insurance_ledger_entries')->selectRaw('SUM(credit-debit) as balance')->value('balance'));
        $service->applyCustomerInsuranceBalance($paid, \App\Models\User::find(1));
        $this->assertSame(1, DB::table('customer_insurance_ledger_entries')->where('entry_type','insurance_payment_debit')->count());
        $this->assertEquals(100, \App\Models\User::find(1)->wallet_balance);
        $this->expectException(DomainException::class);
        $service->applyCustomerInsuranceBalance($paid, \App\Models\User::find(2));
    }

    public function test_rejection_leaves_both_balances_unchanged_and_cannot_be_approved_later(): void
    {
        $deposit = $this->deposit();
        $service = app(CustomerBalanceDepositService::class);
        $service->review($deposit,'reject',9,'Transfer not received');
        $this->assertSame('rejected',$deposit->fresh()->status);
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertEquals(100, DB::table('users')->where('id',1)->value('wallet_balance'));
        $this->expectException(DomainException::class);
        $service->review($deposit,'approve',9,null);
    }

    public function test_invalid_wallet_rolls_back_without_marking_payment_paid(): void
    {
        $deposit = $this->deposit('invalid');
        try { app(CustomerBalanceDepositService::class)->review($deposit,'approve',9,null); $this->fail(); } catch (DomainException $e) {
            $this->assertSame('invalid_deposit_wallet',$e->getMessage());
        }
        $this->assertSame('pending',$deposit->fresh()->status);
        $this->assertEquals(100, DB::table('users')->where('id',1)->value('wallet_balance'));
    }

    public function test_receipt_cannot_be_read_by_another_customer(): void
    {
        $deposit = $this->deposit();
        $this->actingAs(\App\Models\User::find(2), 'customer');
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(\App\Http\Controllers\Customer\CustomerBalanceDepositController::class)->proof($deposit->id);
    }

    public function test_offline_submission_uses_platform_settings_and_waits_for_review(): void
    {
        Schema::create('business_settings', function (Blueprint $table) { $table->id(); $table->string('type'); $table->text('value'); });
        foreach (['language'=>'[{"code":"en","status":1,"default":true}]','add_funds_to_wallet'=>'1','wallet_status'=>'1','offline_payment'=>'{"status":1}', 'currency_model'=>'single_currency','system_default_currency'=>'1'] as $key=>$value) {
            DB::table('business_settings')->insert(['type'=>$key,'value'=>$value]);
        }
        Schema::create('currencies', function (Blueprint $table) { $table->id(); $table->string('code'); });
        DB::table('currencies')->insert(['id'=>1,'code'=>'USD']);
        Schema::create('offline_payment_methods', function (Blueprint $table) {
            $table->id(); $table->string('method_name'); $table->boolean('status'); $table->json('method_informations')->nullable();
        });
        DB::table('offline_payment_methods')->insert(['id'=>1,'method_name'=>'Bank','status'=>1]);
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->actingAs(\App\Models\User::find(1),'customer');
        $file = \Illuminate\Http\UploadedFile::fake()->image('receipt.png');
        $request = \Illuminate\Http\Request::create('/customer-balances/offline', 'POST', [
            'wallet_type'=>'purchase','amount'=>50,'method_id'=>1,'request_key'=>(string)\Illuminate\Support\Str::uuid(),'payment_reference'=>'transfer-42',
        ], [], ['payment_proof'=>$file]);
        $request->setLaravelSession(app('session')->driver());
        $controller = app(\App\Http\Controllers\Customer\CustomerBalanceDepositController::class);
        $controller->store($request);
        $controller->store($request);
        $this->assertSame(1,CustomerBalanceDeposit::count());
        $deposit = CustomerBalanceDeposit::first();
        $this->assertSame('pending',$deposit->status);
        $this->assertEquals(100,DB::table('users')->where('id',1)->value('wallet_balance'));
        $encrypted = \Illuminate\Support\Facades\Storage::disk('local')->get($deposit->payment_proof);
        $this->assertNotSame(file_get_contents($file->getRealPath()),$encrypted);
        $response = $controller->proof($deposit->id);
        $this->assertSame('image/png',$response->headers->get('Content-Type'));
        $this->assertSame(file_get_contents($file->getRealPath()),$response->getContent());

        // API identity must win over any customer web session on the device.
        $apiRequest = \Illuminate\Http\Request::create('/api/v1/customer/wallet/deposits', 'POST', [
            'wallet_type'=>'insurance','amount'=>25,'method_id'=>1,
            'request_key'=>(string)\Illuminate\Support\Str::uuid(),'payment_reference'=>'mobile-transfer',
        ], [], ['payment_proof'=>\Illuminate\Http\UploadedFile::fake()->image('mobile.png')]);
        $apiRequest->setUserResolver(fn () => \App\Models\User::find(2));
        $apiResponse = $controller->store($apiRequest);
        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $apiResponse);
        $this->assertSame(200, $apiResponse->getStatusCode());
        $controller->store($apiRequest);
        $this->assertSame(2, CustomerBalanceDeposit::count());
        $mobile = CustomerBalanceDeposit::where('payment_reference', 'mobile-transfer')->firstOrFail();
        $this->assertSame(2, (int) $mobile->customer_id);
        $this->assertSame('insurance', $mobile->wallet_type);
        $this->assertSame('pending', $mobile->status);
        $this->assertEquals(300, DB::table('users')->where('id', 2)->value('wallet_balance'));
    }

    public function test_disabled_platform_offline_payment_rejects_direct_submissions(): void
    {
        Schema::create('business_settings', function (Blueprint $table) { $table->id(); $table->string('type'); $table->text('value'); });
        DB::table('business_settings')->insert([['type'=>'add_funds_to_wallet','value'=>'1'],['type'=>'offline_payment','value'=>'{"status":0}']]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Http\Controllers\Customer\CustomerBalanceDepositController::class)->store(\Illuminate\Http\Request::create('/customer-balances/offline','POST'));
    }

    public function test_repeated_digital_purchase_success_credits_once_and_preserves_insurance_balance(): void
    {
        Schema::create('business_settings', function (Blueprint $table) { $table->id(); $table->string('type'); $table->text('value'); });
        DB::table('business_settings')->insert([['type'=>'wallet_status','value'=>'1'],['type'=>'currency_model','value'=>'single_currency']]);
        session(['local'=>'en']);
        Schema::table('users', function (Blueprint $table) { $table->string('f_name')->nullable(); $table->string('email')->nullable(); });
        DB::table('users')->where('id',1)->update(['f_name'=>'Test','email'=>'test@example.com']);
        Schema::create('add_fund_bonus_categories', function (Blueprint $table) {
            $table->id(); $table->boolean('is_active'); $table->dateTime('start_date_time'); $table->dateTime('end_date_time'); $table->decimal('min_add_money_amount',24,3);
        });
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->string('id')->primary(); $table->unsignedBigInteger('payer_id'); $table->boolean('is_paid');
            $table->string('attribute'); $table->decimal('payment_amount',24,3); $table->string('currency_code');
            $table->string('payment_method'); $table->text('additional_data')->nullable(); $table->timestamps();
        });
        \Illuminate\Support\Facades\Event::fake([\App\Events\AddFundToWalletEvent::class]);
        $payment = \App\Models\PaymentRequest::create([
            'payer_id'=>1,'is_paid'=>1,'attribute'=>'add_funds_to_wallet','payment_amount'=>40,
            'currency_code'=>'USD','payment_method'=>'stripe','additional_data'=>'{}',
        ]);
        \add_fund_to_wallet_success($payment);
        \add_fund_to_wallet_success($payment);
        $this->assertEquals(140,DB::table('users')->where('id',1)->value('wallet_balance'));
        $this->assertSame(1,DB::table('wallet_transactions')->count());
        $this->assertSame(0,DB::table('customer_insurance_ledger_entries')->count());
        $this->assertNotEmpty(json_decode($payment->fresh()->additional_data,true)['wallet_credit_transaction_id']);
    }
}
