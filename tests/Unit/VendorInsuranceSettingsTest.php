<?php

namespace Tests\Unit;

use App\Http\Requests\Admin\VendorSettingsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class VendorInsuranceSettingsTest extends TestCase
{
    public function test_enabled_insurance_rejects_zero_amount(): void
    {
        $validator = Validator::make([
            'seller_insurance_status' => 1,
            'seller_insurance_amount' => 0,
        ], (new VendorSettingsRequest())->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('seller_insurance_amount', $validator->errors()->toArray());
    }

    public function test_enabled_insurance_accepts_valid_settings(): void
    {
        $validator = Validator::make([
            'seller_insurance_status' => 1,
            'seller_insurance_amount' => 250,
            'seller_insurance_repayment_after_forfeiture' => 1,
            'vendor_forgot_password_method' => 'phone',
        ], (new VendorSettingsRequest())->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_disabled_insurance_allows_zero_amount(): void
    {
        $validator = Validator::make([
            'seller_insurance_amount' => 0,
            'vendor_forgot_password_method' => 'email',
        ], (new VendorSettingsRequest())->rules());

        $this->assertTrue($validator->passes());
    }
}
