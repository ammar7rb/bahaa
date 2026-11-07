<?php

namespace App\Services;

use App\Models\CompanyMedicalDevice;
use App\Models\MedicalDeviceInquiry;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\DB;

class MedicalDeviceInquiryService
{
    public function submit(int $deviceId, int $customerId): MedicalDeviceInquiry
    {
        return DB::transaction(function () use ($deviceId, $customerId) {
            // Serialize requests for this device, including concurrent taps in different tabs.
            $device = CompanyMedicalDevice::where('is_published', true)->lockForUpdate()->findOrFail($deviceId);
            $existing = MedicalDeviceInquiry::where('customer_id', $customerId)->where('device_id', $deviceId)->first();
            if ($existing) {
                return $existing;
            }
            $ticket = SupportTicket::create(['customer_id' => $customerId, 'subject' => \Illuminate\Support\Str::limit('طلب تفاصيل جهاز / Device inquiry: '.$device->name, 150, ''),
                'description' => $device->name, 'type' => 'medical_device', 'purpose' => 'medical_device', 'priority' => 'low', 'status' => 'open']);

            // An unread support ticket is the existing persisted administration inbox notification.
            return MedicalDeviceInquiry::create(['device_id' => $deviceId, 'device_name' => $device->name,
                'customer_id' => $customerId, 'support_ticket_id' => $ticket->id, 'status' => 'new']);
        });
    }
}
