<?php

namespace Tests\Unit;

use App\Services\SubscriptionDurationService;
use Carbon\Carbon;
use Tests\TestCase;

class SubscriptionDurationServiceTest extends TestCase
{
    public function test_calendar_month_and_year_expiry_are_not_converted_to_fixed_days(): void
    {
        $service = app(SubscriptionDurationService::class);
        $start = Carbon::create(2026, 1, 31, 12);

        $this->assertSame('months', $service->normalize('months', 1)['unit']);
        $this->assertSame('2026-02-28 12:00:00', $service->expiresAt($start, 'months', 1)->toDateTimeString());
        $this->assertSame('2027-01-31 12:00:00', $service->expiresAt($start, 'years', 1)->toDateTimeString());
        $this->assertNull($service->expiresAt($start, 'lifetime', null));
    }

    public function test_legacy_days_are_normalized_and_missing_duration_is_lifetime(): void
    {
        $service = app(SubscriptionDurationService::class);

        $this->assertSame(['unit' => 'days', 'value' => 15], $service->normalize(null, null, 15));
        $this->assertSame(['unit' => 'lifetime', 'value' => null], $service->normalize(null, null, null));
    }

    public function test_all_duration_units_and_the_exact_expiry_boundary_are_consistent(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0));
        $service = app(SubscriptionDurationService::class);
        $start = now()->copy();

        $this->assertSame('2026-08-17 10:00:00', $service->expiresAt($start, 'days', 7)->toDateTimeString());
        $this->assertSame('2026-10-10 10:00:00', $service->expiresAt($start, 'months', 2)->toDateTimeString());
        $this->assertSame('2028-08-10 10:00:00', $service->expiresAt($start, 'years', 2)->toDateTimeString());
        $this->assertSame('lifetime', $service->status(null, 'lifetime'));
        $this->assertSame('active', $service->status(now()->addSecond(), 'days'));
        $this->assertSame('expired', $service->status(now(), 'days'));

        Carbon::setTestNow();
    }
}
