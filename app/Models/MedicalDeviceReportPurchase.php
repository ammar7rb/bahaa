<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalDeviceReportPurchase extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['purchase_requested_at' => 'datetime', 'confirmed_at' => 'datetime', 'report_sent_at' => 'datetime', 'cart_removed_at' => 'datetime', 'report_hidden_at' => 'datetime'];

    public function device(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CompanyMedicalDevice::class, 'device_id')->withTrashed();
    }
}
