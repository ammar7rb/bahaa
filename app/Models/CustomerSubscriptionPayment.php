<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSubscriptionPayment extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['payment_proof', 'request_key'];
    protected $casts = [
        'quoted_amount' => 'decimal:3', 'wallet_debit_amount' => 'decimal:3',
        'method_fields' => 'array', 'method_information' => 'array',
        'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
}
