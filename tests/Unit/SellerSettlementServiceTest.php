<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Services\SellerLedgerService;
use App\Services\SellerSettlementService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerSettlementServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sellers', function (Blueprint $table) { $table->id(); $table->timestamps(); });
        Schema::create('seller_wallets', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id');
            $table->decimal('total_earning', 40, 20)->default(0);
            $table->decimal('pending_withdraw', 40, 20)->default(0);
            $table->decimal('withdrawn', 40, 20)->default(0);
            $table->timestamps();
        });
        Schema::create('seller_ledger_entries', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id'); $table->string('bucket'); $table->string('direction');
            $table->decimal('amount', 40, 20); $table->string('event_type'); $table->string('group_key');
            $table->char('idempotency_key', 64)->unique(); $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable(); $table->json('metadata')->nullable();
            $table->char('previous_hash', 64)->nullable(); $table->char('entry_hash', 64);
            $table->unsignedBigInteger('created_by')->nullable(); $table->timestamps();
        });
        Schema::create('seller_settlements', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id'); $table->unsignedBigInteger('order_id')->unique();
            $table->decimal('amount', 40, 20); $table->string('status')->default('held');
            $table->timestamp('delivered_at'); $table->timestamp('dispute_until')->nullable();
            $table->timestamp('due_at')->nullable(); $table->timestamp('pre_due_notified_at')->nullable(); $table->timestamp('overdue_notified_at')->nullable(); $table->timestamp('released_at')->nullable();
            $table->timestamp('reversed_at')->nullable(); $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->text('review_note')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id')->nullable(); $table->string('seller_is')->nullable(); $table->timestamps();
        });
        Schema::create('business_settings', function (Blueprint $table) { $table->id(); $table->string('type')->unique(); $table->text('value')->nullable(); $table->timestamps(); });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id(); $table->string('sent_by')->nullable(); $table->string('sent_to')->nullable(); $table->unsignedBigInteger('seller_id')->nullable(); $table->string('title')->nullable();
            $table->text('description')->nullable(); $table->integer('notification_count')->default(0); $table->string('image')->nullable();
            $table->boolean('status')->default(true); $table->timestamps();
        });
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        foreach (['notifications', 'business_settings', 'orders', 'seller_settlements', 'seller_ledger_entries', 'seller_wallets', 'sellers'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_hold_uses_working_days_and_releases_pending_to_available_once(): void
    {
        $this->setting('seller_settlement_hold_days', '3');
        $this->setting('seller_settlement_working_days', json_encode([0, 1, 2, 3, 4]));
        $this->setting('seller_settlement_holidays', json_encode(['2026-08-10']));
        $service = app(SellerSettlementService::class);

        $due = $service->calculateDueAt(Carbon::parse('2026-08-07 10:00:00'));
        $this->assertSame('2026-08-12', $due->toDateString());

        $seller = Seller::create();
        $order = Order::create(['seller_id' => $seller->id, 'seller_is' => 'seller']);
        $settlement = $service->holdDeliveredOrder($order, 50, now()->subMinute());
        $this->assertSame(SellerSettlement::STATUS_HELD, $settlement->status);
        $this->assertSame(50.0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::PENDING]);
        $this->assertSame($settlement->id, $service->holdDeliveredOrder($order, 50)->id);

        $this->assertSame(1, $service->releaseDue());
        $this->assertSame(0.0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::PENDING]);
        $this->assertSame(50.0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::AVAILABLE]);
        $this->assertSame(0, $service->releaseDue());
        $this->assertSame(2, \App\Models\Notification::count());
    }

    public function test_held_settlement_can_be_disputed_and_reversed_without_available_credit(): void
    {
        $seller = Seller::create();
        $order = Order::create(['seller_id' => $seller->id, 'seller_is' => 'seller']);
        $service = app(SellerSettlementService::class);
        $settlement = $service->holdDeliveredOrder($order, 25, now()->addDay());

        $this->assertTrue($service->dispute($settlement, 'Customer return review'));
        $this->assertTrue($service->reverse($settlement, 'Approved return', 9));
        $summary = app(SellerLedgerService::class)->summary($seller->id);
        $this->assertSame(0.0, $summary[SellerLedgerService::PENDING]);
        $this->assertSame(0.0, $summary[SellerLedgerService::AVAILABLE]);
        $this->assertSame(SellerSettlement::STATUS_REVERSED, $settlement->fresh()->status);
    }

    public function test_forty_five_working_days_are_allowed_and_the_settings_are_snapshotted(): void
    {
        $this->setting('seller_settlement_hold_days', '45');
        $this->setting('seller_settlement_working_days', json_encode([0, 1, 2, 3, 4]));
        $this->setting('seller_settlement_holidays', json_encode(['2026-08-10']));
        $service = app(SellerSettlementService::class);

        $this->assertSame(45, $service->holdDays());
        $this->assertTrue($service->calculateDueAt(Carbon::parse('2026-08-07 10:00:00'))->gt(Carbon::parse('2026-10-01')));

        $seller = Seller::create();
        $order = Order::create(['seller_id' => $seller->id, 'seller_is' => 'seller']);
        $settlement = $service->holdDeliveredOrder($order, 75);

        $this->assertSame(45, data_get($settlement->metadata, 'hold_days'));
        $this->assertSame([0, 1, 2, 3, 4], data_get($settlement->metadata, 'working_days'));
        $this->assertSame(['2026-08-10'], data_get($settlement->metadata, 'holidays'));

        \App\Models\BusinessSetting::where('type', 'seller_settlement_hold_days')->update(['value' => '3']);
        $this->assertSame(45, data_get($settlement->fresh()->metadata, 'hold_days'));
    }

    public function test_due_alerts_are_targeted_and_idempotent(): void
    {
        $seller = Seller::create();
        $order = Order::create(['seller_id' => $seller->id, 'seller_is' => 'seller']);
        $service = app(SellerSettlementService::class);
        $settlement = $service->holdDeliveredOrder($order, 10, now()->addDays(2));

        $this->assertSame(['upcoming' => 1, 'overdue' => 0], $service->sendDueAlerts());
        $this->assertSame(['upcoming' => 0, 'overdue' => 0], $service->sendDueAlerts());
        $settlement->update(['delivered_at' => now()->subDays(2)]);
        $service->setDueAt($settlement, now()->subMinute(), 4, 'Overdue review');
        $this->assertSame(['upcoming' => 0, 'overdue' => 1], $service->sendDueAlerts());
        $this->assertSame(3, \App\Models\Notification::where('seller_id', $seller->id)->count());
    }

    private function setting(string $type, string $value): void
    {
        \App\Models\BusinessSetting::create(['type' => $type, 'value' => $value]);
    }
}
