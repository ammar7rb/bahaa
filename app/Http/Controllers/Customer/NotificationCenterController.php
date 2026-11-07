<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Notification;

class NotificationCenterController extends Controller
{
    public function index()
    {
        $customerId = auth('customer')->id();
        abort_unless($customerId, 401);
        $notifications = app(\App\Services\CustomerCommunicationService::class)->notifications((int) $customerId)->paginate(20);
        return view('customer-account.notifications', compact('notifications'));
    }
}
