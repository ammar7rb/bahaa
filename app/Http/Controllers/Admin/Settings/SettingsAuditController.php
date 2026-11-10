<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsAuditController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['kind' => 'nullable|in:tax,commission,package,shipping,payment_destination,legal_content,advertising_placement,catalog,company_product,account_security']);
        $records = DB::table('business_setting_audits')
            ->when($data['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->orderByDesc('id')->paginate(50)->withQueryString();
        return view('admin-views.business-settings.audit', compact('records'));
    }
}
