<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderVendorPricing extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'approved_at' => 'datetime'];
}
