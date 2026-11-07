<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorFulfillmentRequest extends Model
{
    protected $guarded = [];
    protected $casts = [
        'vendor_amount' => 'decimal:8', 'shipping_amount' => 'decimal:8',
        'forwarded_at' => 'datetime', 'pending_due_at' => 'datetime',
        'pending_at' => 'datetime', 'unlocked_at' => 'datetime',
        'accepted_at' => 'datetime', 'rejected_at' => 'datetime', 'canceled_at' => 'datetime', 'delivered_at' => 'datetime',
        'unlock_snapshot' => 'array', 'penalty_rule_snapshot' => 'array',
    ];
}
