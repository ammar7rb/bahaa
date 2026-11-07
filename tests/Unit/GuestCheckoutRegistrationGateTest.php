<?php
namespace Tests\Unit;

use App\Http\Controllers\Web\WebController;
use App\Utils\OrderManager;
use Illuminate\Http\Request;
use Tests\TestCase;

class GuestCheckoutRegistrationGateTest extends TestCase
{
    public function test_guest_direct_checkout_and_payment_urls_always_redirect_to_quick_registration(): void
    {
        $controller=\Mockery::mock(WebController::class)->makePartial();
        foreach (['checkout_details','checkout_payment','checkout_complete_wallet','getCashOnDeliveryCheckoutComplete'] as $method) {
            $request=Request::create('/checkout-payment','GET',['payment_method'=>'cash_on_delivery']);
            $response=$controller->$method($request);
            $this->assertSame(route('customer.auth.checkout-register'),$response->getTargetUrl());
        }
    }

    public function test_guest_ajax_continue_returns_registration_gate_without_entering_checkout(): void
    {
        $response=OrderManager::checkValidationForCheckoutPages(Request::create('/order_note','POST'));
        $this->assertSame(0,$response['status']);
        $this->assertTrue($response['registration_required']);
        $this->assertSame(route('customer.auth.checkout-register'),$response['redirect']);
    }

    public function test_direct_ajax_checkout_returns_the_quick_form_url(): void
    {
        $request=Request::create('/checkout-details','GET',[],[],[],['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);
        $controller=\Mockery::mock(WebController::class)->makePartial();
        $this->assertSame(route('customer.auth.checkout-register'),$controller->checkout_details($request)->getData(true)['redirect']);
    }
}
