<?php

namespace Tests\Unit;

use App\Services\CustomerPaymentNotificationService;
use App\Services\CustomerWalletWithdrawalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CustomerWalletWithdrawalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->text('value')->nullable(); $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->decimal('wallet_balance', 24, 3); $table->boolean('is_active');
            $table->timestamp('account_deleted_at')->nullable(); $table->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id(); $table->integer('user_id'); $table->string('transaction_id'); $table->string('reference');
            $table->string('transaction_type'); $table->string('payment_method');
            foreach (['credit', 'debit', 'admin_bonus', 'balance'] as $field) $table->decimal($field, 24, 3);
            $table->timestamps();
        });
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_04_160000_create_customer_wallet_withdrawals.php')->up();
        DB::table('users')->insert(['id' => 1, 'wallet_balance' => 1000, 'is_active' => true]);
        $notification = Mockery::mock(CustomerPaymentNotificationService::class);
        $notification->shouldReceive('notify')->byDefault();
        $this->app->instance(CustomerPaymentNotificationService::class, $notification);
    }

    public function test_request_reserves_once_and_snapshots_ten_percent(): void
    {
        $service = app(CustomerWalletWithdrawalService::class);
        $key = (string) \Illuminate\Support\Str::uuid();
        $first = $service->request(1, 100, 10, $key);
        $again = $service->request(1, 100, 10, $key);
        $this->assertEquals($first->id, $again->id);
        $this->assertEquals(10, $first->fee_amount);
        $this->assertEquals(90, $first->net_amount);
        $this->assertEquals(900, DB::table('users')->value('wallet_balance'));
        $this->assertSame(1, DB::table('wallet_transactions')->count());
    }

    public function test_refund_can_be_spent_but_not_withdrawn_until_its_unlock_date(): void
    {
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_08_130000_create_order_cancellation_settlements.php')->up();
        DB::table('users')->where('id', 1)->update(['wallet_balance'=>120]);
        DB::table('wallet_transactions')->insert(['user_id'=>1,'transaction_id'=>'refund','reference'=>'refund-1',
            'transaction_type'=>'order_refund','payment_method'=>'purchase_wallet','credit'=>100,'debit'=>0,'admin_bonus'=>0,'balance'=>120,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('order_cancellation_settlements')->insert(['operation_key'=>'customer:1','order_id'=>1,'customer_id'=>1,'canceled_by'=>'customer',
            'reason'=>'cancel','canceled_at'=>now(),'refund_amount'=>100,'financial_reference'=>'refund-1','credit_expires_at'=>now()->addDays(2),
            'reviewed_by_admin_id'=>7,'created_at'=>now(),'updated_at'=>now()]);
        $service = app(CustomerWalletWithdrawalService::class);
        try { $service->request(1, 21, 10, 'early'); $this->fail('Locked funds were withdrawable'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('withdrawal', $e->errors()); }
        $this->assertEquals(120, DB::table('users')->value('wallet_balance'));
        $service->request(1, 20, 10, 'unlocked');
        $this->travel(3)->days();
        $service->request(1, 100, 10, 'matured');
        $this->assertEquals(0, DB::table('users')->value('wallet_balance'));
    }

    public function test_changed_fee_requires_new_consent_without_debiting(): void
    {
        DB::table('business_settings')->insert(['type' => 'customer_withdrawal_fee_percent', 'value' => '12']);
        try {
            app(CustomerWalletWithdrawalService::class)->request(1, 100, 10, (string) \Illuminate\Support\Str::uuid());
            $this->fail('Stale fee accepted');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('withdrawal', $error->errors());
        }
        $this->assertEquals(1000, DB::table('users')->value('wallet_balance'));
        $this->assertSame(0, DB::table('customer_wallet_withdrawals')->count());
    }

    public function test_rejection_returns_the_full_reserved_amount_once(): void
    {
        $service = app(CustomerWalletWithdrawalService::class);
        $row = $service->request(1, 100, 10, (string) \Illuminate\Support\Str::uuid());
        $service->review($row->id, 7, 'reject', 'رفض', null);
        try {
            $service->review($row->id, 7, 'reject', 'رفض', null);
            $this->fail('Duplicate rejection accepted');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('withdrawal', $error->errors());
        }
        $this->assertEquals(1000, DB::table('users')->value('wallet_balance'));
        $this->assertSame(2, DB::table('wallet_transactions')->count());
    }

    public function test_approval_starts_the_deadline_and_completion_never_debits_again(): void
    {
        $this->travelTo(now()->startOfDay());
        $service = app(CustomerWalletWithdrawalService::class);
        $row = $service->request(1, 100, 10, (string) \Illuminate\Support\Str::uuid());
        $this->travel(5)->days();
        $approved = $service->review($row->id, 7, 'approve', null, null);
        $this->assertSame(now()->addDays(30)->toDateTimeString(), $approved->due_at);
        try {
            $service->review($row->id, 7, 'complete', null, 'paid-1');
            $this->fail('Early completion accepted');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('withdrawal', $error->errors());
        }
        $this->travel(30)->days();
        $completed = $service->review($row->id, 7, 'complete', null, 'paid-1');
        $this->assertSame('completed', $completed->status);
        $this->assertEquals(900, DB::table('users')->value('wallet_balance'));
        $this->assertSame(1, DB::table('wallet_transactions')->count());
    }
}
