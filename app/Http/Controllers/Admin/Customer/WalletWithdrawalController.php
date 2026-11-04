<?php

namespace App\Http\Controllers\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerWalletWithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletWithdrawalController extends Controller
{
    public function index(CustomerWalletWithdrawalService $service)
    {
        return view('admin-views.customer.withdrawals', [
            'withdrawals' => DB::table('customer_wallet_withdrawals')->latest('id')->paginate(30),
            'feePercent' => $service->feePercent(), 'withdrawalDays' => $service->days(),
        ]);
    }

    public function settings(Request $request)
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('business_settings'), 403);
        $data = $request->validate([
            'fee_percent' => 'required|numeric|between:0,100|decimal:0,2',
            'days' => 'required|integer|between:15,30',
        ]);
        DB::transaction(function () use ($data) {
            \App\Models\BusinessSetting::updateOrCreate(['type' => 'customer_withdrawal_fee_percent'], ['value' => (string) $data['fee_percent']]);
            \App\Models\BusinessSetting::updateOrCreate(['type' => 'customer_withdrawal_days'], ['value' => (string) $data['days']]);
        });
        return back()->with('success', 'تم حفظ نسبة الرسوم ومدة السحب.');
    }

    public function review(Request $request, int $id, CustomerWalletWithdrawalService $service)
    {
        $data = $request->validate([
            'decision' => 'required|in:approve,reject,complete',
            'note' => 'nullable|string|max:1000|required_if:decision,reject',
            'reference' => 'nullable|string|max:191|required_if:decision,complete',
        ]);
        $service->review($id, (int) auth('admin')->id(), $data['decision'], $data['note'] ?? null, $data['reference'] ?? null);
        return back()->with('success', 'تم تحديث طلب السحب.');
    }
}
