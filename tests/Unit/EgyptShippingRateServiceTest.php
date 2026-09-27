<?php

namespace Tests\Unit;

use App\Models\EgyptShippingZoneRate;
use App\Services\EgyptShippingRateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EgyptShippingRateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('egypt_shipping_zone_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('country_code', 2)->default('EG');
            $table->string('governorate')->nullable();
            $table->string('district')->nullable();
            $table->string('area')->nullable();
            $table->decimal('normal_cost', 24, 3)->nullable();
            $table->decimal('sigma_cost', 24, 3)->nullable();
            $table->decimal('included_distance_km', 24, 3)->default(0);
            $table->decimal('price_per_km', 24, 3)->default(0);
            $table->decimal('peak_multiplier', 24, 3)->default(1);
            $table->boolean('normal_available')->default(true);
            $table->boolean('sigma_available')->default(true);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('egypt_shipping_zone_rates');
        parent::tearDown();
    }

    public function test_most_specific_egypt_zone_wins_and_non_egypt_address_is_rejected(): void
    {
        EgyptShippingZoneRate::create([
            'governorate' => 'Cairo',
            'normal_cost' => 50,
            'sigma_cost' => 80,
            'normal_available' => true,
            'sigma_available' => true,
            'status' => true,
        ]);
        EgyptShippingZoneRate::create([
            'governorate' => 'Cairo',
            'district' => 'Nasr City',
            'area' => 'First Zone',
            'normal_cost' => 60,
            'sigma_cost' => 95,
            'normal_available' => true,
            'sigma_available' => true,
            'status' => true,
        ]);

        $service = app(EgyptShippingRateService::class);
        $address = ['country' => 'Egypt', 'state' => 'Cairo', 'city' => 'Nasr City', 'area' => 'First Zone'];

        $this->assertSame(180.0, $service->costFor($address, 'sigma'));
        $this->assertSame(60.0, $service->costFor($address, 'normal'));
        $this->assertNull($service->findRate(['country' => 'Saudi Arabia', 'state' => 'Cairo']));
        $this->assertFalse($service->isEgyptAddress(['country' => 'Saudi Arabia']));
    }

    public function test_governorate_tariff_never_requires_a_map_distance(): void
    {
        $rate = EgyptShippingZoneRate::create([
            'governorate' => 'Cairo', 'normal_cost' => 80, 'sigma_cost' => 240,
            'price_per_km' => 0, 'status' => true,
        ]);
        $service = app(EgyptShippingRateService::class);
        $address = ['country' => 'Egypt', 'state' => 'Cairo'];
        $this->assertSame(0.0, $service->pricingDistanceFromDispatch($address));
        $rate->update(['price_per_km' => 5]);
        $this->assertSame(0.0, $service->pricingDistanceFromDispatch($address));
    }

    public function test_egypt_address_rejects_coordinates_outside_egypt_when_coordinates_are_supplied(): void
    {
        $service = app(EgyptShippingRateService::class);

        $this->assertTrue($service->isEgyptAddress([
            'country' => 'Egypt',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
        ]));
        $this->assertTrue($service->isEgyptAddress([
            'country' => 'Egypt',
            'latitude' => 0,
            'longitude' => 0,
        ]));
        $this->assertFalse($service->isEgyptAddress([
            'country' => 'Egypt',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
        ]));
        $this->assertFalse($service->isEgyptAddress([
            'country' => 'Egypt',
            'latitude' => 'not-a-coordinate',
            'longitude' => 31.2357,
        ]));
    }

    public function test_sigma_is_exactly_three_times_the_distance_based_normal_quote(): void
    {
        EgyptShippingZoneRate::create([
            'governorate' => 'Cairo',
            'normal_cost' => 25,
            'sigma_cost' => 999,
            'included_distance_km' => 5,
            'price_per_km' => 3,
            'normal_available' => true,
            'sigma_available' => true,
            'status' => true,
        ]);

        $service = app(EgyptShippingRateService::class);
        $address = ['country' => 'Egypt', 'state' => 'Cairo'];

        $normal = $service->quote($address, 'normal', 10);
        $sigma = $service->quote($address, 'sigma', 10);

        $this->assertSame(40.0, $normal['total']);
        $this->assertSame(120.0, $sigma['total']);
        $this->assertSame(3.0, $sigma['total'] / $normal['total']);
    }

    public function test_fixed_governorate_price_does_not_require_google_distance(): void
    {
        EgyptShippingZoneRate::create([
            'governorate' => 'Cairo',
            'normal_cost' => 80,
            'sigma_cost' => 240,
            'price_per_km' => 0,
            'status' => true,
        ]);

        $distance = app(EgyptShippingRateService::class)->pricingDistanceFromDispatch([
            'country' => 'Egypt',
            'state' => 'Cairo',
        ]);

        self::assertSame(0.0, $distance);
    }
}
