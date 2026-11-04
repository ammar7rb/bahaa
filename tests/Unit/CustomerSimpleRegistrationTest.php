<?php

namespace Tests\Unit;

use App\Http\Requests\Web\CustomerRegistrationRequest;
use App\Services\Web\CustomerAuthService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerSimpleRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session()->put('local', 'en');
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->text('value')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('phone')->nullable(); $table->string('canonical_phone')->nullable();
            $table->string('email')->nullable(); $table->string('referral_code')->nullable();
        });
    }

    private function request(array $data): CustomerRegistrationRequest
    {
        $request = CustomerRegistrationRequest::create('/customer/auth/sign-up', 'POST', $data);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        return $request;
    }

    public function test_three_fields_are_sufficient_and_phone_is_stored_in_local_form(): void
    {
        $request = $this->request(['f_name' => 'أحمد', 'phone' => '+201000000003', 'password' => 'abc123']);
        $request->validateResolved();
        $data = app(CustomerAuthService::class)->getCustomerRegisterData($request, null);
        $this->assertSame('01000000003', $data['phone']);
        $this->assertSame('01000000003', $data['canonical_phone']);
        $this->assertSame('أحمد', $data['name']);
        $this->assertNull($data['email']);
        $this->assertNull($data['terms_accepted_at']);
        $this->assertNull($data['privacy_accepted_at']);
        $this->assertTrue(password_verify('abc123', $data['password']));
    }

    public function test_existing_phone_alias_cannot_create_another_account(): void
    {
        DB::table('users')->insert(['phone' => '+201000000003', 'canonical_phone' => '01000000003']);
        $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class);
        $this->request(['f_name' => 'أحمد', 'phone' => '01000000003', 'password' => 'abc123'])->validateResolved();
    }

    public function test_password_shorter_than_six_characters_is_rejected(): void
    {
        $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class);
        $this->request(['f_name' => 'أحمد', 'phone' => '01000000003', 'password' => '12345'])->validateResolved();
    }
}
