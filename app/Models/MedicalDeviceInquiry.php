<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalDeviceInquiry extends Model
{
    protected $guarded = ['id'];

    public function device()
    {
        return $this->belongsTo(CompanyMedicalDevice::class, 'device_id')->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function conversation()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function reportPurchase()
    {
        return $this->hasOne(MedicalDeviceReportPurchase::class, 'inquiry_id');
    }
}
