<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Services\FirebaseService;
use App\Services\SellerRegistrationVerificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class SellerRegistrationVerificationModeTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }

    public function test_default_mode_uses_support_ticket_without_otp(): void
    {
        $service = $this->service();
        $seller = new Seller(['registration_reference' => 'reference']);

        $this->assertSame(SellerRegistrationVerificationService::MODE_SUPPORT_TICKET_ONLY, $service->verificationMode());
        $this->assertFalse($service->requiresOtp());
        $this->assertTrue($service->requiresSupportTicket());
        $this->assertFalse($service->requiresPhoneVerification($seller));
    }

    public function test_each_configured_mode_exposes_its_required_verification_steps(): void
    {
        $service = $this->service();
        $seller = new Seller(['registration_reference' => 'reference']);

        $service->setVerificationMode(SellerRegistrationVerificationService::MODE_NONE);
        $this->assertFalse($service->requiresOtp());
        $this->assertFalse($service->requiresSupportTicket());

        $service->setVerificationMode(SellerRegistrationVerificationService::MODE_OTP_ONLY);
        $this->assertTrue($service->requiresOtp());
        $this->assertFalse($service->requiresSupportTicket());
        $this->assertTrue($service->requiresPhoneVerification($seller));

        $service->setVerificationMode(SellerRegistrationVerificationService::MODE_OTP_AND_SUPPORT_TICKET);
        $this->assertTrue($service->requiresOtp());
        $this->assertTrue($service->requiresSupportTicket());
    }

    public function test_invalid_mode_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->setVerificationMode('unsupported');
    }

    private function service(): SellerRegistrationVerificationService
    {
        return new SellerRegistrationVerificationService(Mockery::mock(FirebaseService::class));
    }
}
