<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/** Approved vendor rules; integration is staged until the phase 4 rollout. */
final class VendorAdvertisingCyclePolicy
{
    public const DURATION_DAYS = 30;

    public const TRANSITION_DAYS = 7;

    /** Early renewal and tier changes start after the paid current period. */
    public function cycle(DateTimeInterface $activatedAt, ?DateTimeInterface $paidThrough = null): array
    {
        $timezone = $activatedAt->getTimezone();
        $start = CarbonImmutable::instance($activatedAt)->utc();
        if ($paidThrough !== null) {
            $endOfPaidPeriod = CarbonImmutable::instance($paidThrough)->utc();
            if ($endOfPaidPeriod->greaterThan($start)) {
                $start = $endOfPaidPeriod;
            }
        }

        // Persist in the application's timezone; UTC strings in a local-time
        // database would otherwise shorten a renewed period by the UTC offset.
        return ['starts_at' => $start->setTimezone($timezone), 'expires_at' => $start->addDays(self::DURATION_DAYS)->setTimezone($timezone)];
    }

    /** Every slot uses the cycle boundary, including a late replacement. */
    public function canPublish(DateTimeInterface $at, DateTimeInterface $start, DateTimeInterface $end): bool
    {
        $instant = CarbonImmutable::instance($at)->utc();

        return $instant->greaterThanOrEqualTo(CarbonImmutable::instance($start)->utc())
            && $instant->lessThan(CarbonImmutable::instance($end)->utc());
    }

    public function transitionDeadline(DateTimeInterface $rolloutAt): CarbonImmutable
    {
        return CarbonImmutable::instance($rolloutAt)->utc()->addDays(self::TRANSITION_DAYS);
    }

    /** A downgrade cannot silently choose which paid ads stay visible. */
    public function validateSelection(array $productIds, int $slotCount): array
    {
        if ($slotCount < 1 || count($productIds) > $slotCount) {
            throw new InvalidArgumentException('vendor_advertising_slot_limit_exceeded');
        }
        foreach ($productIds as $id) {
            if (! is_int($id) || $id < 1) {
                throw new InvalidArgumentException('vendor_advertising_product_id_invalid');
            }
        }
        if (count(array_unique($productIds)) !== count($productIds)) {
            throw new InvalidArgumentException('vendor_advertising_duplicate_product');
        }

        return array_values($productIds);
    }
}
