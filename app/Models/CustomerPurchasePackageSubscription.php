<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPurchasePackageSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'customer_purchase_package_id',
        'package_name',
        'paid_package_price',
        'package_purchase_limit',
        'used_purchase_limit',
        'extra_credit_limit',
        'admin_adjustment_limit',
        'available_purchase_limit',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'starts_at',
        'expires_at',
        'activated_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'id' => 'integer',
        'customer_id' => 'integer',
        'customer_purchase_package_id' => 'integer',
        'paid_package_price' => 'float',
        'package_purchase_limit' => 'float',
        'used_purchase_limit' => 'float',
        'extra_credit_limit' => 'float',
        'admin_adjustment_limit' => 'float',
        'available_purchase_limit' => 'float',
        'metadata' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CustomerPurchasePackage::class, 'customer_purchase_package_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerPurchaseLimitTransaction::class);
    }
}
