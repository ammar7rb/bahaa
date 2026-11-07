<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMonthlySubscription extends Model
{
    protected $guarded = ['id'];
    protected $fillable = ['customer_id','payment_id','starts_at','expires_at','package_id','package_name','package_price','granted_allowance','additional_allowance','committed_allowance','limit_revision'];
    protected $casts = ['limit_revision'=>'integer','starts_at' => 'datetime', 'expires_at' => 'datetime', 'package_id' => 'integer', 'granted_allowance' => 'decimal:3', 'additional_allowance' => 'decimal:3', 'committed_allowance' => 'decimal:3', 'package_price' => 'decimal:3'];
}
