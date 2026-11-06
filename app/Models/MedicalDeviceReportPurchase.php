<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalDeviceReportPurchase extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['purchase_requested_at' => 'datetime'];
}
