<?php

namespace Tests\Unit;

use App\Services\OrderCancellationSettlementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderCancellationCreditExpiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->decimal('wallet_balance', 24, 3); $table->timestamps(); });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('transaction_id')->unique();
            $table->string('reference'); $table->string('transaction_type'); $table->string('payment_method');
            foreach (['credit', 'debit', 'admin_bonus', 'balance'] as $column) $table->decimal($column, 24, 3)->default(0);
            $table->timestamps();
        });
        (require base_path('database/migrations/2026_11_08_130000_create_order_cancellation_settlements.php'))->up();
    }

    public function test_credit_is_not_removed_after_date(): void
    {
        $start = now()->subDays(5);
        DB::table('users')->insert(['id' => 1, 'wallet_balance' => 120, 'created_at' => $start, 'updated_at' => $start]);
        DB::table('wallet_transactions')->insert([
            ['user_id' => 1, 'transaction_id' => 'refund', 'reference' => 'admin-cancel-order-3', 'transaction_type' => 'order_refund', 'payment_method' => 'purchase_wallet', 'credit' => 100, 'debit' => 0, 'balance' => 100, 'created_at' => $start, 'updated_at' => $start],
            ['user_id' => 1, 'transaction_id' => 'purchase', 'reference' => 'purchase', 'transaction_type' => 'order_place', 'payment_method' => 'purchase_wallet', 'credit' => 0, 'debit' => 30, 'balance' => 70, 'created_at' => $start->copy()->addDay(), 'updated_at' => $start->copy()->addDay()],
            ['user_id' => 1, 'transaction_id' => 'deposit', 'reference' => 'deposit', 'transaction_type' => 'add_fund', 'payment_method' => 'purchase_wallet', 'credit' => 50, 'debit' => 0, 'balance' => 120, 'created_at' => $start->copy()->addDays(2), 'updated_at' => $start->copy()->addDays(2)],
        ]);
        DB::table('order_cancellation_settlements')->insert([
            'operation_key' => 'customer:3', 'order_id' => 3, 'customer_id' => 1, 'canceled_by' => 'customer',
            'reason' => 'cancel', 'canceled_at' => $start, 'refund_amount' => 100,
            'financial_reference' => 'admin-cancel-order-3', 'credit_expires_at' => $start->copy()->addDays(3),
            'reviewed_by_admin_id' => 7, 'created_at' => $start, 'updated_at' => $start,
        ]);
        $service = app(OrderCancellationSettlementService::class);
        $this->assertSame(0, $service->expireCredits());
        $this->assertEquals(120, DB::table('users')->where('id', 1)->value('wallet_balance'));
        $this->assertNull(DB::table('order_cancellation_settlements')->value('credit_expired_at'));
        $this->assertSame(0, $service->expireCredits());
        $this->assertSame(0, DB::table('wallet_transactions')->where('transaction_type', 'cancellation_credit_expiry')->count());
    }

    public function test_multiple_credits_are_not_removed_after_date(): void
    {
        $start = now()->subDays(6);
        DB::table('users')->insert(['id' => 1, 'wallet_balance' => 70]);
        DB::table('wallet_transactions')->insert([
            ['user_id'=>1,'transaction_id'=>'refund-a','reference'=>'admin-cancel-order-1','transaction_type'=>'order_refund','payment_method'=>'purchase_wallet','credit'=>50,'debit'=>0,'balance'=>50,'created_at'=>$start,'updated_at'=>$start],
            ['user_id'=>1,'transaction_id'=>'refund-b','reference'=>'admin-cancel-order-2','transaction_type'=>'order_refund','payment_method'=>'purchase_wallet','credit'=>50,'debit'=>0,'balance'=>100,'created_at'=>$start->copy()->addDay(),'updated_at'=>$start->copy()->addDay()],
            ['user_id'=>1,'transaction_id'=>'spend','reference'=>'spend','transaction_type'=>'order_place','payment_method'=>'purchase_wallet','credit'=>0,'debit'=>30,'balance'=>70,'created_at'=>$start->copy()->addDays(2),'updated_at'=>$start->copy()->addDays(2)],
        ]);
        foreach ([1, 2] as $number) DB::table('order_cancellation_settlements')->insert([
            'operation_key'=>'customer:'.$number,'order_id'=>$number,'customer_id'=>1,'canceled_by'=>'customer',
            'reason'=>'cancel','canceled_at'=>$start,'refund_amount'=>50,
            'financial_reference'=>'admin-cancel-order-'.$number,
            'credit_expires_at'=>$start->copy()->addDays($number === 1 ? 3 : 10),
            'reviewed_by_admin_id'=>7,'created_at'=>$start->copy()->addDays($number - 1),
            'updated_at'=>$start->copy()->addDays($number - 1),
        ]);
        $this->assertSame(0, app(OrderCancellationSettlementService::class)->expireCredits());
        $this->assertNull(DB::table('order_cancellation_settlements')->where('order_id', 1)->value('credit_expired_at'));
        $this->assertEquals(70, DB::table('users')->where('id', 1)->value('wallet_balance'));
        $this->assertNull(DB::table('order_cancellation_settlements')->where('order_id', 2)->value('credit_expired_at'));
    }
}
