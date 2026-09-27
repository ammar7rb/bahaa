<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Models\VendorWithdrawMethodInfo;
use App\Models\WithdrawalMethod;
use App\Services\SellerWithdrawalMethodService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerWithdrawalMethodServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('sellers', function (Blueprint $table) { $table->id(); $table->string('email')->nullable(); $table->timestamps(); });
        Schema::create('withdrawal_methods', function (Blueprint $table) { $table->id(); $table->string('method_name'); $table->json('method_fields')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps(); });
        Schema::create('vendor_withdraw_method_infos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('withdraw_method_id'); $table->string('method_name')->nullable(); $table->string('method_type')->default('bank'); $table->string('approval_status')->default('pending'); $table->text('approval_reason')->nullable(); $table->timestamp('submitted_at')->nullable(); $table->timestamp('reviewed_at')->nullable(); $table->unsignedBigInteger('reviewed_by')->nullable(); $table->json('method_info')->nullable(); $table->boolean('is_active')->default(false); $table->boolean('is_default')->default(false); $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('vendor_withdraw_method_infos'); Schema::dropIfExists('withdrawal_methods'); Schema::dropIfExists('sellers'); parent::tearDown();
    }

    public function test_submission_is_pending_and_approval_makes_it_default_and_usable(): void
    {
        $seller = Seller::create(['email' => 'seller@example.com']);
        $global = WithdrawalMethod::create(['method_name' => 'Bank', 'method_fields' => []]);
        $data = app(SellerWithdrawalMethodService::class)->prepareSubmission([
            'user_id' => $seller->id, 'withdraw_method_id' => $global->id, 'method_name' => 'My bank', 'method_info' => [],
        ], 'bank');
        $method = VendorWithdrawMethodInfo::create($data);
        $this->assertSame('pending', $method->approval_status);
        $this->assertFalse($method->is_active);
        $this->assertFalse($method->is_default);

        app(SellerWithdrawalMethodService::class)->approve($method, 1);
        $method->refresh();
        $this->assertSame('approved', $method->approval_status);
        $this->assertTrue($method->is_active);
        $this->assertTrue($method->is_default);
        $this->assertSame($method->id, app(SellerWithdrawalMethodService::class)->assertUsable($method->id, $seller->id)->id);
    }

    public function test_rejected_method_cannot_be_used(): void
    {
        $seller = Seller::create(['email' => 'seller@example.com']);
        $global = WithdrawalMethod::create(['method_name' => 'Vodafone Cash', 'method_fields' => []]);
        $method = VendorWithdrawMethodInfo::create(app(SellerWithdrawalMethodService::class)->prepareSubmission([
            'user_id' => $seller->id, 'withdraw_method_id' => $global->id, 'method_name' => 'Cash', 'method_info' => [],
        ], 'vodafone_cash'));
        app(SellerWithdrawalMethodService::class)->reject($method, 1, 'Invalid account details');
        $this->expectException(\DomainException::class);
        app(SellerWithdrawalMethodService::class)->assertUsable($method->id, $seller->id);
    }
}
