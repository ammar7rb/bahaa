<?php

namespace Tests\Unit;

use App\Models\EgyptShippingZoneRate;
use App\Services\EgyptShippingRateService;
use App\Services\GoogleMapsShippingLocationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleMapsShippingLocationServiceTest extends TestCase
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
        Schema::create('egypt_shipping_zone_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('country_code', 2)->default('EG');
            $table->string('governorate')->nullable();
            $table->string('district')->nullable();
            $table->string('area')->nullable();
            $table->string('street')->nullable();
            $table->decimal('dispatch_latitude', 10, 7)->nullable();
            $table->decimal('dispatch_longitude', 10, 7)->nullable();
            $table->decimal('normal_cost', 24, 3)->nullable();
            $table->decimal('sigma_cost', 24, 3)->nullable();
            $table->decimal('included_distance_km', 24, 3)->default(0);
            $table->decimal('price_per_km', 24, 3)->default(0);
            $table->decimal('peak_multiplier', 24, 3)->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        \DB::table('business_settings')->insert([
            'type' => 'map_api_key_server',
            'value' => 'test-server-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Schema::dropIfExists('egypt_shipping_zone_rates');
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }

    public static function addressDetailCases(): array
    {
        return ['full address' => [false], 'optional details omitted' => [true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('addressDetailCases')]
    public function test_it_accepts_structured_text_and_uses_the_matched_governorate_tariff(bool $omitOptional): void
    {
        $rate = EgyptShippingZoneRate::create([
            'governorate' => 'القاهرة',
            'dispatch_latitude' => 30.0444,
            'dispatch_longitude' => 31.2357,
            'normal_cost' => 50,
            'status' => true,
        ]);

        Http::fake();

        $service = app(GoogleMapsShippingLocationService::class);
        $address = [
            'state' => 'القاهرة',
            'city' => 'مدينة نصر',
            'district' => 'الحي العاشر',
            'area' => 'المنطقة الأولى',
            'street' => 'شارع أحمد الزمر',
            'building_number' => '12',
            'address' => 'عمارة 12، الدور الثالث',
        ];
        if ($omitOptional) {
            unset($address['district'], $address['area'], $address['street'], $address['building_number']);
            $address['address'] = 'شارع أحمد الزمر، عمارة 12، الدور الثالث';
        }
        $result = $service->resolve($address);

        $this->assertSame(GoogleMapsShippingLocationService::STATUS_RESOLVED, $result['status']);
        $this->assertSame($rate->id, $result['shipping_zone_rate_id']);
        $this->assertSame(0.0, $result['distance_km']);
        $this->assertArrayNotHasKey('latitude', $result);
        $this->assertArrayNotHasKey('longitude', $result);
        $this->assertSame($omitOptional ? null : '12', $service->persistedLocationFields($address, $result)['building_number']);
        Http::assertNothingSent();
    }

    public function test_it_uses_the_selected_governorate_without_a_map_lookup(): void
    {
        EgyptShippingZoneRate::create([
            'governorate' => 'القاهرة',
            'dispatch_latitude' => 30.0444,
            'dispatch_longitude' => 31.2357,
            'normal_cost' => 50,
            'status' => true,
        ]);

        Http::fake();

        $result = app(GoogleMapsShippingLocationService::class)->resolve([
            'state' => 'القاهرة',
            'city' => 'مدينة نصر',
            'district' => 'الحي العاشر',
            'area' => 'المنطقة الأولى',
            'street' => 'شارع أحمد الزمر',
            'building_number' => '12',
            'address' => 'شارع أحمد الزمر، عمارة 12',
        ]);

        $this->assertSame(GoogleMapsShippingLocationService::STATUS_RESOLVED, $result['status']);
        $this->assertSame(0.0, $result['distance_km']);
        Http::assertNothingSent();
    }

    public function test_rate_service_uses_a_verified_stored_road_distance_without_recalculating_it(): void
    {
        $rate = EgyptShippingZoneRate::create([
            'governorate' => 'القاهرة',
            'dispatch_latitude' => 30.0444,
            'dispatch_longitude' => 31.2357,
            'normal_cost' => 50,
            'status' => true,
        ]);
        $service = app(EgyptShippingRateService::class);

        $this->assertSame(18.75, $service->distanceFromDispatch([
            'state' => 'القاهرة',
            'country' => 'Egypt',
            'shipping_zone_rate_id' => $rate->id,
            'distance_km' => 18.75,
        ]));
    }
}
