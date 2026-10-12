<?php

namespace Tests\Unit;

use App\Models\BusinessSetting;
use App\Services\SellerBalanceFundingSettingsService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerBalanceFundingSettingsServiceTest extends TestCase
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

    public function test_settings_are_saved_and_amount_limits_are_enforced(): void
    {
        $service = app(SellerBalanceFundingSettingsService::class);
        $service->update(['offline_enabled' => false, 'digital_enabled' => true, 'min_amount' => 50, 'max_amount' => 500, 'digital_gateways' => ['stripe'], 'company_accounts' => [['bank_name' => 'Bank']]]);

        $settings = $service->get();
        $this->assertFalse($settings['offline_enabled']);
        $this->assertSame(50.0, $settings['min_amount']);
        $this->assertSame(['stripe'], $settings['digital_gateways']);
        $this->assertSame('Bank', $settings['company_accounts'][0]['bank_name']);

        $this->expectException(DomainException::class);
        $service->assertAmountAllowed(501);
    }
}
