<?php

namespace Tests\Unit;

use App\Providers\ConfigServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformTimezoneTest extends TestCase
{
    public function test_config_php_and_carbon_use_the_same_egypt_timezone(): void
    {
        Schema::create('business_settings', function (Blueprint $table): void {
            $table->id(); $table->string('type'); $table->text('value');
        });
        \DB::table('business_settings')->insert(['type' => 'timezone', 'value' => 'Africa/Cairo']);
        config(['app.timezone' => 'Asia/Dhaka']);
        (new ConfigServiceProvider($this->app))->boot();
        $this->assertSame('Africa/Cairo', config('app.timezone'));
        $this->assertSame('Africa/Cairo', config('timezone'));
        $this->assertSame('Africa/Cairo', date_default_timezone_get());
        $this->assertSame('Africa/Cairo', now()->timezoneName);
    }

    public function test_summer_and_winter_offsets_and_legacy_conversion_preserve_the_instant(): void
    {
        $cairo = new \DateTimeZone('Africa/Cairo');
        $this->assertSame(10800, (new \DateTimeImmutable('2026-10-01 12:00:00', $cairo))->getOffset());
        $this->assertSame(7200, (new \DateTimeImmutable('2026-02-01 12:00:00', $cairo))->getOffset());
        $old = new \DateTimeImmutable('2026-10-01 13:01:58', new \DateTimeZone('Asia/Dhaka'));
        $converted = $old->setTimezone($cairo);
        $this->assertSame('2026-10-01 10:01:58', $converted->format('Y-m-d H:i:s'));
        $this->assertSame($old->getTimestamp(), $converted->getTimestamp());
    }
}
