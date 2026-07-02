<?php

namespace Tests\Unit;

use App\Models\PaymentRequest;
use App\Models\Seller;
use App\Models\SellerInsurance;
use App\Services\SellerInsuranceService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SellerInsuranceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('approved');
            $table->timestamps();
        });
        Schema::create('seller_insurances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->uuid('transaction_id')->unique();
            $table->decimal('amount', 40, 20)->default(0);
            $table->string('status', 50)->default('pending');
            $table->string('payment_status', 50)->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->char('payment_request_id', 36)->nullable();
            $table->text('forfeiture_reason')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->timestamp('forfeited_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('payer_id', 64)->nullable();
            $table->string('receiver_id', 64)->nullable();
            $table->decimal('payment_amount', 40, 20)->default(0);
            $table->string('success_hook', 100)->nullable();
            $table->string('failure_hook', 100)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('currency_code', 20)->default('USD');
            $table->string('payment_method', 50)->nullable();
            $table->longText('additional_data')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->string('attribute_id', 64)->nullable();
            $table->string('attribute')->nullable();
            $table->string('payment_platform')->nullable();
            $table->timestamps();
        });

        DB::table('business_settings')->insert([
            ['type' => 'seller_insurance_status', 'value' => '1'],
            ['type' => 'seller_insurance_amount', 'value' => '100'],
            ['type' => 'seller_insurance_repayment_after_forfeiture', 'value' => '1'],
        ]);
        DB::table('sellers')->insert(['id' => 1, 'status' => 'approved']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('seller_insurances');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('business_settings');
        Cache::flush();

        parent::tearDown();
    }

    public function test_offline_payment_only_activates_after_admin_approval_and_is_idempotent(): void
    {
        $service = app(SellerInsuranceService::class);
        $seller = Seller::findOrFail(1);
        $insurance = $service->getOrCreatePayableInsurance($seller);

        $this->assertSame(100.0, (float) $insurance->amount);
        $insurance = $service->submitOfflinePayment($insurance, [
            'method_id' => 2,
            'method_name' => 'Manual Transfer',
            'payment_proof' => ['image_name' => 'proof.webp', 'storage' => 'public'],
        ]);

        $this->assertSame(SellerInsurance::STATUS_PENDING_REVIEW, $insurance->status);
        $this->assertSame('unpaid', $insurance->payment_status);

        $result = $service->markPaid($insurance, [
            'payment_method' => 'offline_payment',
            'transaction_id' => 'manual-reference-1',
            'payment_amount' => 100,
            'currency_code' => 'USD',
        ], 9, 'Transfer verified.');

        $this->assertSame(1, $result['status']);
        $this->assertSame(SellerInsurance::STATUS_PAID, $result['insurance']->status);
        $this->assertSame(9, $result['insurance']->reviewed_by_admin_id);

        $duplicateResult = $service->markPaid($result['insurance'], [
            'payment_method' => 'offline_payment',
            'transaction_id' => 'manual-reference-1',
        ], 9);
        $this->assertSame('already_paid', $duplicateResult['message']);
        $this->assertSame(1, SellerInsurance::count());
    }

    public function test_rejected_offline_payment_remains_in_history_and_allows_a_new_attempt(): void
    {
        $service = app(SellerInsuranceService::class);
        $seller = Seller::findOrFail(1);
        $firstInsurance = $service->getOrCreatePayableInsurance($seller);
        $firstInsurance = $service->submitOfflinePayment($firstInsurance, [
            'method_id' => 2,
            'method_name' => 'Manual Transfer',
        ]);
        $rejected = $service->rejectOfflinePayment($firstInsurance, 7, 'Proof does not match.');

        $this->assertSame(SellerInsurance::STATUS_REJECTED, $rejected->status);
        $this->assertSame('unpaid', $rejected->payment_status);

        $newInsurance = $service->getOrCreatePayableInsurance($seller);
        $this->assertNotSame($rejected->id, $newInsurance->id);
        $this->assertSame(2, SellerInsurance::count());
    }

    public function test_verified_payment_request_success_hook_activates_insurance_once(): void
    {
        $service = app(SellerInsuranceService::class);
        $insurance = $service->getOrCreatePayableInsurance(Seller::findOrFail(1));
        $paymentRequestId = (string) Str::uuid();
        $paymentRequest = PaymentRequest::create([
            'id' => $paymentRequestId,
            'payer_id' => '1',
            'receiver_id' => '100',
            'payment_amount' => 100,
            'success_hook' => 'seller_insurance_payment_success',
            'failure_hook' => 'seller_insurance_payment_fail',
            'transaction_id' => 'gateway-transaction-1',
            'currency_code' => 'USD',
            'payment_method' => 'stripe',
            'additional_data' => json_encode([
                'seller_id' => 1,
                'seller_insurance_id' => $insurance->id,
            ]),
            'is_paid' => 1,
            'attribute_id' => (string) $insurance->id,
            'attribute' => 'seller_insurance',
            'payment_platform' => 'web',
        ]);

        seller_insurance_payment_success($paymentRequest);
        seller_insurance_payment_success($paymentRequest->fresh());

        $insurance->refresh();
        $this->assertSame(SellerInsurance::STATUS_PAID, $insurance->status);
        $this->assertSame('paid', $insurance->payment_status);
        $this->assertSame((string) $paymentRequest->id, $insurance->payment_request_id);
        $this->assertSame(1, SellerInsurance::count());
    }

    public function test_disabled_insurance_does_not_create_a_payment_record(): void
    {
        DB::table('business_settings')->where('type', 'seller_insurance_status')->update(['value' => '0']);
        Cache::flush();
        $service = app(SellerInsuranceService::class);
        $seller = Seller::findOrFail(1);

        $summary = $service->getSummary($seller);
        $this->assertFalse($summary['required']);
        $this->assertTrue($summary['active']);

        $this->expectException(DomainException::class);
        $service->getOrCreatePayableInsurance($seller);
    }
}
