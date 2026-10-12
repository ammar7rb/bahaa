<?php

namespace Tests\Unit;

use App\Services\EgyptDispatchOriginResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EgyptDispatchOriginResolverTest extends TestCase
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
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }

    public function test_it_uses_the_governorate_centre_when_no_dispatch_address_is_entered(): void
    {
        $origin = app(EgyptDispatchOriginResolver::class)->resolve('القاهرة');

        $this->assertSame('مركز محافظة القاهرة', $origin['dispatch_address']);
        $this->assertSame(30.0444, $origin['dispatch_latitude']);
        $this->assertSame(31.2357, $origin['dispatch_longitude']);
        Http::assertNothingSent();
    }

    public function test_it_geocodes_a_textual_dispatch_address_without_accepting_manual_coordinates(): void
    {
        \DB::table('business_settings')->insert([
            'type' => 'map_api_key_server',
            'value' => 'test-server-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::flush();
        Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'results' => [[
                    'geometry' => ['location' => ['lat' => 30.1023, 'lng' => 31.3456]],
                ]],
            ]),
        ]);

        $origin = app(EgyptDispatchOriginResolver::class)->resolve(
            governorate: 'القاهرة',
            district: 'مدينة نصر',
            dispatchAddress: 'مخزن الشركة، شارع الطيران'
        );

        $this->assertSame('مخزن الشركة، شارع الطيران', $origin['dispatch_address']);
        $this->assertSame(30.1023, $origin['dispatch_latitude']);
        $this->assertSame(31.3456, $origin['dispatch_longitude']);
        Http::assertSentCount(1);
    }
}
