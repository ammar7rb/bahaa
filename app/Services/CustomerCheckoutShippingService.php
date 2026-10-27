<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartShipping;
use App\Models\ShippingAddress;
use App\Models\ShippingType;
use Illuminate\Support\Facades\DB;

class CustomerCheckoutShippingService
{
    /** The caller supplies only groups from the customer's checked basket. */
    public function legacyGroups(array $groupIds): array
    {
        $groups = [];
        $model = getWebConfig('shipping_method');
        foreach (Cart::whereIn('cart_group_id', $groupIds)->where('is_checked', 1)->where('product_type', 'physical')->get()->groupBy('cart_group_id') as $id => $items) {
            $first = $items->first();
            $owner = $model === 'inhouse_shipping' || $first->seller_is === 'admin' ? 0 : $first->seller_id;
            $type = ShippingType::where('seller_id', $owner)->value('shipping_type') ?? 'order_wise';
            if ($type !== 'order_wise') continue;
            $groups[$id] = app(CustomerThreeStepShippingService::class)->getAvailableMethodsForSeller($owner, $owner ? 'seller' : 'admin');
        }
        return $groups;
    }

    public function apply(array $groupIds, ShippingAddress $address, array $input): array
    {
        if (!app(EgyptShippingRateService::class)->isEgyptAddress($address)) throw new \DomainException('عنوان الشحن يجب أن يكون داخل مصر.');
        $shipping = app(CustomerThreeStepShippingService::class);
        if ($shipping->isEnabled()) {
            if (!app(EgyptShippingRateService::class)->governorateFor($address)) throw new \DomainException('اختر المحافظة وأكمل المدينة وتفاصيل العنوان.');
            return $shipping->applyCheckoutOption($groupIds, $address, $input['preferred_shipping_option'] ?? '');
        }
        $selections = [];
        foreach ($this->legacyGroups($groupIds) as $groupId => $methods) {
            $selected = $methods->firstWhere('id', (int) ($input['legacy_shipping_methods'][$groupId] ?? 0));
            if (!$selected || !is_finite((float) $selected->cost) || $selected->cost < 0) {
                throw new \DomainException('اختر طريقة شحن متاحة لكل مجموعة منتجات في صفحة العنوان.');
            }
            $selections[$groupId] = ['shipping_method_id' => $selected->id, 'shipping_cost' => $selected->cost];
        }
        DB::transaction(function () use ($selections) {
            foreach ($selections as $id => $selection) CartShipping::updateOrCreate(['cart_group_id' => $id], $selection);
        });
        return [];
    }
}
