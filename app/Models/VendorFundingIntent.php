<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class VendorFundingIntent extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['seller_id'=>'integer', 'deposit_id'=>'integer', 'package_id'=>'integer', 'extra_purchase_id'=>'integer',
        'subscription_id'=>'integer', 'snapshot'=>'array', 'purchase_amount'=>'decimal:2', 'completed_at'=>'immutable_datetime'];
}
