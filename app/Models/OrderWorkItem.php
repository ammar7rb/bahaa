<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderWorkItem extends Model
{
    public const STATUSES = ['awaiting_admin', 'sent_to_vendor', 'accepted', 'overdue', 'rejected', 'canceled', 'completed'];

    protected $guarded = [];

    protected $casts = [
        'vendor_unit_price' => 'float', 'shipping_customer_cost' => 'float',
        'shipping_vendor_cost' => 'float', 'customer_total' => 'float', 'vendor_net_amount' => 'float',
        'sent_to_vendor_at' => 'datetime', 'vendor_response_due_at' => 'datetime',
        'vendor_responded_at' => 'datetime', 'expected_delivery_at' => 'datetime', 'vendor_payment_due_at' => 'datetime',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function line(): BelongsTo { return $this->belongsTo(OrderDetail::class, 'order_detail_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function events(): HasMany { return $this->hasMany(OrderWorkItemEvent::class, 'work_item_id')->latest('id'); }
}
