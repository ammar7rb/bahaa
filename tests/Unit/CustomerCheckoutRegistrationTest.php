<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\CustomerCheckoutRegistrationService;
use App\Services\PolicyAcceptanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerCheckoutRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            foreach (['name','f_name','l_name','phone','email','password','activation_status','onboarding_journey'] as $column) $table->string($column)->nullable();
            $table->string('canonical_phone')->nullable()->unique();
            foreach (['is_active','is_phone_verified','is_email_verified'] as $column) $table->boolean($column)->default(false);
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->timestamps();
        });
        $policies = $this->mock(PolicyAcceptanceService::class);
        $policies->shouldReceive('requiredFor')->andReturn(collect());
        $policies->shouldReceive('accept')->andReturnNull();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    private function input(): array
    {
        return ['name'=>'عميل جديد', 'phone'=>'٠١٠١٢٣٤٥٦٧٨', 'password'=>'Test-pass-123', 'terms_accepted'=>true];
    }

    public function test_registration_is_minimal_and_does_not_claim_phone_verification(): void
    {
        $user = app(CustomerCheckoutRegistrationService::class)->register($this->input());
        $this->assertSame('01012345678', $user->phone);
        $this->assertNull($user->email);
        $this->assertSame('checkout_first', $user->onboarding_journey);
        $this->assertSame('pending_support', $user->activation_status);
        $this->assertFalse($user->is_phone_verified);
        $this->assertTrue(Hash::check('Test-pass-123', $user->password));
    }

    public function test_existing_international_phone_cannot_receive_a_new_journey(): void
    {
        User::query()->create(['phone'=>'+201012345678','onboarding_journey'=>'standard']);
        try {
            app(CustomerCheckoutRegistrationService::class)->register($this->input());
            $this->fail('Duplicate phone accepted');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('phone', $error->errors());
            $this->assertSame(1, User::query()->count());
            $this->assertSame('standard', User::query()->first()->onboarding_journey);
        }
    }

    public function test_required_policy_versions_must_be_submitted(): void
    {
        $policies = $this->mock(PolicyAcceptanceService::class);
        $policies->shouldReceive('requiredFor')->andReturn(collect([(object)['id'=>7]]));
        $this->expectException(ValidationException::class);
        app(CustomerCheckoutRegistrationService::class)->register($this->input());
    }

    public function test_short_password_or_unsupported_phone_cannot_create_account(): void
    {
        foreach ([['password'=>'123'],['phone'=>'01312345678'],['phone'=>'010abc12345678']] as $invalid) {
            try {
                app(CustomerCheckoutRegistrationService::class)->register(array_replace($this->input(), $invalid));
                $this->fail('Invalid account accepted');
            } catch (ValidationException) {
                $this->assertSame(0, User::query()->count());
            }
        }
    }
}
