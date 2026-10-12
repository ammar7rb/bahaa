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
            return redirect()->route('shop-cart');
        }
        return view('customer-checkout.register', ['policies' => app(PolicyAcceptanceService::class)->requiredFor('customer')]);
    }

    public function store(Request $request, CustomerCheckoutRegistrationService $service)
    {
        abort_if(auth('customer')->check(), 409);
        $this->requireGuestCart(session('guest_id'));
        $user = $service->register($request->all());
        auth('customer')->login($user);
        $request->session()->regenerate();
        CartManager::cartListSessionToDatabase();
        return redirect()->route('shop-cart');
    }

    public function apiStore(Request $request, CustomerCheckoutRegistrationService $service)
    {
        $this->requireGuestCart($request->input('guest_id'));
        $user = $service->register($request->all());
        return response()->json([
            'token' => $user->createToken('LaravelAuthApp')->accessToken,
            'onboarding_journey' => $user->onboarding_journey,
            'activation' => ['status' => $user->activation_status, 'is_active' => false],
        ], 201);
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
