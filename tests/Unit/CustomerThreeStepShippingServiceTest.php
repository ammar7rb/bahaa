<?php

namespace Tests\Unit;

use App\Services\CustomerThreeStepShippingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerThreeStepShippingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('creator_type')->default('admin');
            $table->string('title')->nullable();
            $table->decimal('cost', 24, 3)->default(0);
            $table->string('duration')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $settings = [
            'customer_three_step_shipping_status' => '1',
            'customer_shipping_sigma_status' => '1',
            'customer_shipping_sigma_title' => 'Sigma Delivery',
            'shipping_promise_sigma_mode' => 'range',
            'shipping_promise_sigma_min_days' => '2',
            'shipping_promise_sigma_max_days' => '7',
            'customer_shipping_normal_status' => '1',
            'customer_shipping_normal_title' => 'Normal Delivery',
            'shipping_promise_normal_mode' => 'range',
            'shipping_promise_normal_min_days' => '7',
            'shipping_promise_normal_max_days' => '14',
        ];

        foreach ($settings as $type => $value) {
            DB::table('business_settings')->insert([
                'type' => $type,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('carts');
        Cache::flush();
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }

    public function test_sigma_and_normal_have_the_admin_configured_delivery_ranges(): void
    {
        $service = app(CustomerThreeStepShippingService::class);
        $options = $service->getThreeStepOptionsWithAvailability();

        $this->assertCount(2, $options);
        $this->assertSame(['sigma', 'normal'], $options->pluck('option_key')->all());
        $this->assertSame('2-7 أيام عمل', $options->firstWhere('option_key', 'sigma')['duration']);
        $this->assertSame('7-14 أيام عمل', $options->firstWhere('option_key', 'normal')['duration']);
    }

    public function test_fixed_tariff_selection_matches_preview_without_route_coordinates(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->string('cart_group_id');
            $table->string('product_type');
            $table->boolean('is_checked');
        });
        $address = ['country' => 'Egypt', 'state' => 'Cairo'];
        $zones = \Mockery::mock(\App\Services\EgyptShippingRateService::class);
        $zones->shouldReceive('isEgyptAddress')->andReturn(true);
        $zones->shouldReceive('hasConfiguredRates')->andReturn(true);
        $zones->shouldReceive('pricingDistanceFromDispatch')->andReturn(0.0);
        $zones->shouldReceive('quote')->andReturnUsing(fn ($address, $option) => ['total' => $option === 'normal' ? 80.0 : 240.0]);
        $this->app->instance(\App\Services\EgyptShippingRateService::class, $zones);

        $service = app(CustomerThreeStepShippingService::class);
        $preview = $service->quoteAutomaticDistanceDeliveryOptions('test-group', $address);
        foreach (['normal' => 80.0, 'sigma' => 240.0] as $key => $expected) {
            $method = $service->resolveSelectableMethod($preview['options'][$key]['method']->id);
            $this->assertNotNull($method);
            $this->assertSame($expected, $preview['options'][$key]['cost']);
            $this->assertSame($expected, $service->calculateCartGroupCost('test-group', $method, $address));
        }
    }
}
