<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerPasswordChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountSettingsController extends Controller
{
    public function password(Request $request, CustomerPasswordChangeService $service): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|string|max:255',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        DB::transaction(function () use ($request, $service) {
            $customer = User::lockForUpdate()->findOrFail(auth('customer')->id());
            $customer->password = $service->password($customer, $request);
            $customer->save();
        });

        $request->session()->regenerate();

        return back()->with('settings_status', translate('updated_successfully'));
    }
}
