<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorExtraAdPurchase extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['seller_id' => 'integer', 'seller_package_subscription_id' => 'integer', 'quantity' => 'integer',
        'unit_price' => 'decimal:2', 'total_price' => 'decimal:2', 'terms' => 'array', 'payment_details' => 'array',
        'paid_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];

    public function seller() { return $this->belongsTo(Seller::class); }
    public function subscription() { return $this->belongsTo(SellerPackageSubscription::class, 'seller_package_subscription_id'); }
}
