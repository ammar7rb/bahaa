<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerWalletWithdrawalService;
use App\Models\User;
use App\Utils\Convert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletWithdrawalController extends Controller
{
    public function index(Request $request, CustomerWalletWithdrawalService $service)
    {
        $customerId = (int) auth('customer')->id();
        $customer = User::findOrFail($customerId);

        return view('customer-account.withdrawals', [
            'balance' => max(0, (float) $customer->wallet_balance - $service->lockedCancellationCredit($customerId)),
            'feePercent' => $service->feePercent(),
            'withdrawals' => DB::table('customer_wallet_withdrawals')
                ->where('customer_id', $customerId)->latest('id')->paginate(10),
        ]);
    }

    public function store(Request $request, CustomerWalletWithdrawalService $service)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'currency_code' => 'required|string|size:3',
            'expected_fee_percent' => 'required|numeric|between:0,100',
            'request_key' => 'required|uuid',
            'acknowledge' => 'accepted',
        ]);
        if ($data['currency_code'] !== session('currency_code', getCurrencyCode(type: 'default'))) {
            return back()->withErrors(['amount' => 'تغيرت العملة المعروضة. حدّث الصفحة وراجع المبلغ قبل التأكيد.'])->withInput();
        }
        $amount = Convert::usdPaymentModule($data['amount'], $data['currency_code']);
        $service->request((int) auth('customer')->id(), (float) $amount,
            (float) $data['expected_fee_percent'], $data['request_key']);

        return redirect()->route('customer.withdrawals.index')
            ->with('withdrawal_message', 'تم تقديم طلب السحب للمراجعة.');
    }
}
