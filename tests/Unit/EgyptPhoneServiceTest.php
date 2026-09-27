<?php

namespace Tests\Unit;

use App\Services\EgyptPhoneService;
use Tests\TestCase;

class EgyptPhoneServiceTest extends TestCase
{
    public function test_it_normalizes_and_accepts_only_egyptian_mobile_numbers(): void
    {
        $service = app(EgyptPhoneService::class);

        $this->assertSame('+201012345678', $service->normalize('01012345678'));
        $this->assertSame('+201012345678', $service->normalize('00201012345678'));
        $this->assertTrue($service->isValid('+201012345678'));
        $this->assertFalse($service->isValid('+8801012345678'));
        $this->assertFalse($service->isValid('0111234567'));
    }
}
