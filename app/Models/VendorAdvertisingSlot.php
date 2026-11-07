<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAdvertisingSlot extends Model
{
    protected $hidden = ['reservation_token_hash'];
    protected $appends = ['editable', 'lifecycle_status'];

    public function getEditableAttribute(): bool
    {
        return $this->starts_at->lte(now()) && $this->expires_at->gt(now());
    }

    public function getLifecycleStatusAttribute(): string
    {
        return $this->starts_at->gt(now()) ? 'scheduled' : ($this->expires_at->lte(now()) ? 'frozen' : 'current');
    }

    protected $fillable = ['seller_id', 'seller_package_subscription_id', 'position', 'product_id', 'extra_ad_purchase_id', 'starts_at', 'expires_at'];

    protected $casts = ['reserved_at' => 'immutable_datetime', 'reservation_expires_at' => 'immutable_datetime', 'seller_id' => 'integer', 'seller_package_subscription_id' => 'integer', 'position' => 'integer', 'product_id' => 'integer', 'extra_ad_purchase_id' => 'integer', 'starts_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SellerPackageSubscription::class, 'seller_package_subscription_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
