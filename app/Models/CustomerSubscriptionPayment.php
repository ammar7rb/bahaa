<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSubscriptionPayment extends Model
{
    protected $guarded = ['id'];
    protected $fillable = ['customer_id','open_customer_id','request_key','quoted_amount','wallet_debit_amount','currency_code','status','payment_method','offline_payment_method_id','method_name','method_fields','method_information','payment_reference','payment_proof','payment_note','reviewed_by','review_note','submitted_at','reviewed_at','approved_at','package_id','offer_id','offer_quantity','target_subscription_id','purchase_kind','terms_name','granted_allowance','wallet_policy_version','operation_signature','terms_snapshot','allowance_before','allowance_after','wallet_balance_after','subscription_expires_at'];
    protected $hidden = ['payment_proof', 'request_key'];
    protected $casts = [
        'wallet_policy_version'=>'integer','terms_snapshot'=>'array','allowance_before'=>'decimal:3','allowance_after'=>'decimal:3','wallet_balance_after'=>'decimal:3','subscription_expires_at'=>'datetime',
        'offer_quantity' => 'integer',
        'package_id' => 'integer', 'offer_id' => 'integer', 'target_subscription_id' => 'integer', 'granted_allowance' => 'decimal:3',
        'quoted_amount' => 'decimal:3', 'wallet_debit_amount' => 'decimal:3',
        'method_fields' => 'array', 'method_information' => 'array',
        'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
}
