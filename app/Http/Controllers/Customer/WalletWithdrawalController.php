<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerWalletWithdrawalService;
use App\Utils\Convert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletWithdrawalController extends Controller
{
    private function customer(Request $request): int
    {
        return (int) ($request->is('api/*') ? $request->user('api')->id : auth('customer')->id());
    }

    public function index(Request $request, CustomerWalletWithdrawalService $service)
    {
        $id = $this->customer($request);
        $data = [
            'withdrawals' => DB::table('customer_wallet_withdrawals')->where('customer_id', $id)->latest('id')->paginate(20),
            'feePercent' => $service->feePercent(),
            'balance' => (float) \App\Models\User::findOrFail($id)->wallet_balance,
        ];

        return $request->is('api/*') ? response()->json($data) : view('customer-account.withdrawals', $data);
    }

    public function store(Request $request, CustomerWalletWithdrawalService $service)
    {
        $data = $request->validate([
            'acknowledge' => 'required|accepted',
            'amount' => 'required|numeric|gt:0|max:999999999',
            'expected_fee_percent' => 'required|numeric|between:0,100',
            'request_key' => 'required|uuid',
            'currency_code' => 'required|exists:currencies,code',
        ]);
        $row = $service->request($this->customer($request),
            (float) Convert::usdPaymentModule($data['amount'], $data['currency_code']),
            (float) $data['expected_fee_percent'], $data['request_key']);

        return $request->is('api/*') ? response()->json(['withdrawal' => $row], 201)
            : back()->with('withdrawal_message', 'تم حجز مبلغ السحب وإرسال الطلب للإدارة.');
    }
}
