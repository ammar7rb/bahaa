<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMonthlySubscription extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'package_id' => 'integer', 'granted_allowance' => 'decimal:3', 'additional_allowance' => 'decimal:3', 'committed_allowance' => 'decimal:3', 'package_price' => 'decimal:3'];
}
