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
        $customerId = (int) ($request->user()?->id ?: auth('customer')->id());
        $customer = User::findOrFail($customerId);

        $payload = [
            'balance' => max(0, (float) $customer->wallet_balance - $service->lockedCancellationCredit($customerId)),
            'feePercent' => $service->feePercent(),
            'withdrawals' => DB::table('customer_wallet_withdrawals')
                ->where('customer_id', $customerId)->latest('id')->paginate(10),
        ];
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json($payload);
        }

        return view('customer-account.withdrawals', $payload);
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
        $api = $request->is('api/*') || $request->expectsJson();
        if (!$api && $data['currency_code'] !== session('currency_code', getCurrencyCode(type: 'default'))) {
            return back()->withErrors(['amount' => 'تغيرت العملة المعروضة. حدّث الصفحة وراجع المبلغ قبل التأكيد.'])->withInput();
        }
        $amount = $api ? (float) $data['amount'] : (float) Convert::usdPaymentModule($data['amount'], $data['currency_code']);
        $withdrawal = $service->request((int) ($request->user()?->id ?: auth('customer')->id()), (float) $amount,
            (float) $data['expected_fee_percent'], $data['request_key']);
        if ($api) {
            return response()->json(['message' => 'تم تقديم طلب السحب للمراجعة.', 'withdrawal' => $withdrawal]);
        }

        return redirect()->route('customer.withdrawals.index')
            ->with('withdrawal_message', 'تم تقديم طلب السحب للمراجعة.');
    }
}
