<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditRequest extends Model
{
    protected $fillable = [
        'reference', 'customer_id', 'product_id', 'support_ticket_id', 'product_code',
        'requested_product_price', 'customer_message', 'status', 'active_key',
        'admin_response', 'responded_by_admin_id', 'responded_at',
    ];

    protected $casts = ['responded_at' => 'datetime'];

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function ticket(): BelongsTo { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function responder(): BelongsTo { return $this->belongsTo(Admin::class, 'responded_by_admin_id'); }
}
