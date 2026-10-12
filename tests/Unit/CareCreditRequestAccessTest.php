<?php

namespace Tests\Unit;

use App\Http\Controllers\Web\CreditRequestController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CareCreditRequestAccessTest extends TestCase
{
    public function test_guest_cannot_create_a_credit_review_request(): void
    {
        try {
            app(CreditRequestController::class)->store();
            $this->fail('A guest must not create a credit review request.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
        }
    }
}
