<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Services\{CustomerMonthlyAllowanceService, CustomerPurchaseEligibilityService, CustomerPurchaseLimitService};
use App\Utils\CartManager;
use Illuminate\Http\Request;
class PurchasingLimitController extends Controller
{
    public function show(Request $request, CustomerMonthlyAllowanceService $limits) {
        $customer = $request->is('api/*') ? auth('api')->user() : auth('customer')->user();
        $request->merge(['user' => $customer]);
        $request->setUserResolver(fn ($guard = null) => $customer);
        $state = $customer ? app(CustomerPurchaseEligibilityService::class)->status($customer) : null;
        $provisional = !$customer || ($state['is_urgent_account'] ?? false);
        $cart = CartManager::getCartListQuery(type: 'checked');
        $amount = app(CustomerPurchaseLimitService::class)->getCartProductTotal($cart);
        return response()->json(['limit' => $limits->enabled() ? $limits->summary((int) ($customer?->id ?? 0), $amount, $provisional) : null, 'eligibility' => $state,
            'cart_items' => collect($cart)->map(fn ($item) => ['id' => $item->id, 'price' => max(0,(float)$item->price-(float)$item->discount), 'quantity' => (int)$item->quantity])->values()]);
    }
}
