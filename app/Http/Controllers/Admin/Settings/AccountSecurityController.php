<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use App\Utils\Helpers;
use Illuminate\Http\Request;

class AccountSecurityController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Helpers::module_permission_check('customer_password_reset')
            || Helpers::module_permission_check('vendor_password_reset'), 403);
        $data = $request->validate(['q' => 'nullable|string|max:100', 'type' => 'nullable|in:customer,vendor']);
        $q = trim($data['q'] ?? '');
        $type = $data['type'] ?? (Helpers::module_permission_check('customer_password_reset') ? 'customer' : 'vendor');
        $can = Helpers::module_permission_check($type === 'customer' ? 'customer_password_reset' : 'vendor_password_reset');
        $accounts = $q !== '' && $can ? ($type === 'customer' ? User::query() : Seller::query())
            ->where(function ($query) use ($q) {
                $query->where('f_name', 'like', "%{$q}%")->orWhere('l_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%");
            })->limit(20)->get(['id', 'f_name', 'l_name', 'email', 'phone']) : collect();
        return view('admin-views.business-settings.account-security', compact('accounts', 'q', 'type'));
    }
}
