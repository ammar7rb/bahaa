<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerPackageSubscription extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REPLACED = 'replaced';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'seller_id',
        'seller_package_id',
        'package_name',
        'paid_package_price',
        'product_limit',
        'used_product_limit',
        'product_adjustment_limit',
        'product_duration_days',
        'search_promotion_limit',
        'used_search_promotion_limit',
        'search_promotion_adjustment_limit',
        'search_promotion_duration_days',
        'homepage_promotion_limit',
        'used_homepage_promotion_limit',
        'homepage_promotion_adjustment_limit',
        'homepage_promotion_duration_days',
        'package_validity_days',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'payment_request_id',
        'starts_at',
        'expires_at',
        'activated_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'id' => 'integer',
        'seller_id' => 'integer',
        'seller_package_id' => 'integer',
        'paid_package_price' => 'float',
        'product_limit' => 'integer',
        'used_product_limit' => 'integer',
        'product_adjustment_limit' => 'integer',
        'product_duration_days' => 'integer',
        'search_promotion_limit' => 'integer',
        'used_search_promotion_limit' => 'integer',
        'search_promotion_adjustment_limit' => 'integer',
        'search_promotion_duration_days' => 'integer',
        'homepage_promotion_limit' => 'integer',
        'used_homepage_promotion_limit' => 'integer',
        'homepage_promotion_adjustment_limit' => 'integer',
        'homepage_promotion_duration_days' => 'integer',
        'package_validity_days' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SellerPackage::class, 'seller_package_id');
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class, 'payment_request_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(SellerProductEntitlement::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(SellerProductPromotion::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SellerPackageTransaction::class);
    }
}
