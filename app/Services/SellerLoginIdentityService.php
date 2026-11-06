<?php

namespace App\Services;

use App\Models\Seller;

class SellerLoginIdentityService
{
    /** Resolve only a unique account. Historical aliases may otherwise collide. */
    public function resolve(string $identity): ?Seller
    {
        $identity = trim($identity);
        $phoneService = app(EgyptPhoneService::class);
        if ($phoneService->isValid($identity)) {
            $matches = Seller::query()->whereIn('phone', $phoneService->aliases($identity))->limit(2)->get();
        } elseif (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
            $matches = Seller::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($identity)])->limit(2)->get();
        } else {
            return null;
        }
        return $matches->count() === 1 ? $matches->first() : null;
    }
}
