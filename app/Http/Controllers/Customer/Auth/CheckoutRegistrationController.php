<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Services\CustomerCheckoutRegistrationService;
use App\Services\PolicyAcceptanceService;
use App\Utils\CartManager;
use Illuminate\Http\Request;

class CheckoutRegistrationController extends Controller
{
    public function show()
    {
        if (auth('customer')->check()) {
            return redirect()->route('checkout-details');
        }
        session()->put('checkout_login_return', true);
        return view('customer-checkout.register', ['policies' => app(PolicyAcceptanceService::class)->requiredFor('customer')]);
    }

    public function store(Request $request, CustomerCheckoutRegistrationService $service)
    {
        abort_if(auth('customer')->check(), 409);
        $this->requireGuestCart(session('guest_id'));
        $user = $this->registerWithCart($service, $request->all(), session('guest_id'));
        auth('customer')->login($user);
        $request->session()->regenerate();
        session()->forget('checkout_login_return');
        return redirect()->route('checkout-details');
    }

    public function apiStore(Request $request, CustomerCheckoutRegistrationService $service)
    {
        abort_if(auth('customer')->check() || $request->user('api'), 409);
        $this->requireGuestCart($request->input('guest_id'));
        $user = $this->registerWithCart($service, $request->all(), $request->input('guest_id'));
        return response()->json([
            'token' => $user->createToken('LaravelAuthApp')->accessToken,
            'onboarding_journey' => $user->onboarding_journey,
            'activation' => ['enabled' => false, 'status' => 'active', 'is_active' => true],
        ], 201);
    }

    private function registerWithCart(CustomerCheckoutRegistrationService $service, array $input, mixed $guestId): \App\Models\User
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($service, $input, $guestId) {
            $cart = \App\Models\Cart::where('customer_id', $guestId)->where('is_guest', 1)->lockForUpdate()->get();
            if ($cart->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'السلة لم تعد متاحة. ارجع إلى السلة أو سجّل الدخول لاستكمال طلبك.']);
            }
            $user = $service->register($input);
            // Use the server-created identity, never a customer id supplied by the client.
            $mergeRequest = Request::create('/', 'POST', ['guest_id' => $guestId]);
            $mergeRequest->setUserResolver(fn () => $user);
            CartManager::cartListSessionToDatabase($mergeRequest, $guestId);
            return $user;
        }, 3);
    }

    private function requireGuestCart(mixed $guestId): void
    {
        if (!$guestId || !\App\Models\Cart::query()->where('customer_id', $guestId)->where('is_guest', 1)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'أضف منتجات إلى السلة قبل التسجيل من الدفع.']);
        }
        if (app(\App\Services\CustomerMonthlyAllowanceService::class)->enabled()) {
            $cart = \App\Models\Cart::where('customer_id', $guestId)->where('is_guest', 1)->get();
            $amount = app(\App\Services\CustomerPurchaseLimitService::class)->getCartProductTotal($cart);
            app(\App\Services\CustomerMonthlyAllowanceService::class)->assertCart(0, $amount, true);
        }
    }
}
