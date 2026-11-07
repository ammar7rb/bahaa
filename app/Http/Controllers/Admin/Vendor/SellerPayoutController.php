<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\WithdrawRequest;
use App\Services\SellerWithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SellerPayoutController extends Controller
{
    public function index()
    {
        return view('admin-views.vendor.payouts', ['withdrawals' => WithdrawRequest::whereNotNull('seller_id')->whereNull('delivery_man_id')->latest('id')->paginate(30)]);
    }

    public function review(Request $request, int $id, SellerWithdrawalService $service)
    {
        $input = $request->validate([
            'decision' => 'required|in:approve,reject,complete',
            'note' => 'nullable|string|max:1000|required_if:decision,reject',
            'reference' => 'nullable|string|max:191|required_if:decision,complete',
        ]);
        $row = WithdrawRequest::whereNotNull('seller_id')->whereNull('delivery_man_id')->findOrFail($id);
        if ($input['decision'] === 'complete' && !$row->reviewed_at || $input['decision'] === 'approve' && $row->reviewed_at) {
            throw ValidationException::withMessages(['decision' => 'تغيرت حالة الطلب. حدّث الصفحة.']);
        }
        try {
            $service->finish($id, $input['decision'] === 'reject' ? 2 : 1, note: $input['note'] ?? null, payoutReference: $input['reference'] ?? null);
        } catch (\DomainException $error) {
            throw ValidationException::withMessages(['payout' => $error->getMessage()]);
        }
        return back()->with('success', 'تم تحديث طلب التاجر.');
    }
}
