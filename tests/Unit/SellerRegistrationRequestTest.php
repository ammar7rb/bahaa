<?php

namespace Tests\Unit;

use App\Http\Requests\API\v3\SellerRegistrationRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SellerRegistrationRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sellers');
        parent::tearDown();
    }

    public function test_registration_accepts_phone_and_a_six_character_password_without_email(): void
    {
        $validator = Validator::make([
            'phone' => '01000000000',
            'password' => 'secret',
        ], (new SellerRegistrationRequest())->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_first_registration_stage_does_not_accept_a_missing_password(): void
    {
        $validator = Validator::make([
            'email' => 'seller@example.com',
            'phone' => '01000000000',
        ], (new SellerRegistrationRequest())->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_first_registration_stage_rejects_non_egyptian_phone_numbers(): void
    {
        $validator = Validator::make([
            'email' => 'seller@example.com',
            'phone' => '01312345678',
            'password' => 'secure-password',
        ], (new SellerRegistrationRequest())->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('phone', $validator->errors()->toArray());
    }
}
