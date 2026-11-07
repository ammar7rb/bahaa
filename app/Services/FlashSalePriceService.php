<?php

namespace App\Services;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A flash-sale discount belongs to the dated offer, never to the product. */
class FlashSalePriceService
{
    public function forProduct(object|array $product): ?array
    {
        $id = is_array($product) ? ($product['id'] ?? null) : ($product->id ?? null);
        $container = Container::getInstance();
        if (!$id || !$container->bound('db') || !$container->bound('request')) return null;
        $request = $container->make('request');
        $key = '_active_flash_sale_prices';
        if (!$request->attributes->has($key)) {
            $offers = [];
            if (Schema::hasTable('flash_deals') && Schema::hasTable('flash_deal_products')) {
                $rows = DB::table('flash_deal_products as fp')->join('flash_deals as f', 'f.id', '=', 'fp.flash_deal_id')
                    ->join('products as p', 'p.id', '=', 'fp.product_id')
                    ->where('f.deal_type', 'flash_deal')->where('f.status', 1)
                    ->where('f.start_date', '<=', now())->where('f.end_date', '>=', now())
                    ->where('fp.discount', '>', 0)->whereIn('fp.discount_type', ['percent', 'flat'])
                    ->orderByDesc('f.id')->select('fp.product_id', 'fp.discount', 'fp.discount_type', 'p.unit_price', 'p.variation')->get();
                foreach ($rows as $row) {
                    $prices = [(float) $row->unit_price];
                    foreach (json_decode($row->variation ?? '[]', true) ?: [] as $variant) {
                        if (isset($variant['price'])) $prices[] = (float) $variant['price'];
                    }
                    if (($row->discount_type === 'percent' && (float) $row->discount >= 100)
                        || ($row->discount_type === 'flat' && (float) $row->discount >= min($prices))) continue;
                    $offers[(int) $row->product_id] ??= ['amount' => (float) $row->discount, 'type' => $row->discount_type];
                }
            }
            $request->attributes->set($key, $offers);
        }
        return $request->attributes->get($key)[(int) $id] ?? null;
    }

    public function discountedAmount(object|array $product, float $price): float
    {
        $offer = $this->forProduct($product);
        if (!$offer) return 0.0;
        $discount = $offer['type'] === 'percent' ? $price * $offer['amount'] / 100 : $offer['amount'];
        // A later price change can invalidate a previously valid fixed offer.
        return $discount > 0 && $discount < $price ? $discount : 0.0;
    }
}
