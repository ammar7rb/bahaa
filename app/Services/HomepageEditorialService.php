<?php

namespace App\Services;

use App\Models\Product;

class HomepageEditorialService
{
    public function eligibleProducts()
    {
        // Group the legacy active scope's seller/admin OR before adding editorial restrictions.
        return Product::query()->where(fn ($q) => $q->active())->where('added_by', 'seller')
            ->where('status', 1)->where('request_status', 1)->homepageVisible()
            ->whereHas('seller', fn ($q) => $q->where('status', 'approved'));
    }
}
