<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMonthlySubscription extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['starts_at' => 'datetime', 'expires_at' => 'datetime'];
}
