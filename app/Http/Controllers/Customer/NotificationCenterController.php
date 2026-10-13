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
        $notifications = Notification::active()->forCustomer((int) $customerId)
            ->where('sent_to', 'customer')->whereIn('sent_by', ['admin', 'system'])
            ->select(['id', 'title', 'description', 'created_at'])
            ->latest('id')->paginate(20);
        return view('customer-account.notifications', compact('notifications'));
    }
}
