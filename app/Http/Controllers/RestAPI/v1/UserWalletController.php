<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\AddFundBonusCategories;
use App\Models\WalletTransaction;
use App\Models\PostPurchaseInvoice;

use App\Utils\Helpers;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserWalletController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $customer = $request->user('api');
        $customerId = (int) $customer->id;

        $canDeposit = getWebConfig('add_funds_to_wallet') == 1;
        $pendingInvoicesQuery = PostPurchaseInvoice::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [PostPurchaseInvoice::STATUS_PENDING, PostPurchaseInvoice::STATUS_AWAITING_REVIEW]);
        $pendingInvoicesCount = (clone $pendingInvoicesQuery)->count();
        $request->validate(['page'=>'nullable|integer|min:1','entries_page'=>'nullable|integer|min:1','deposits_page'=>'nullable|integer|min:1']);
        $statement=app(\App\Services\CustomerWalletStatementService::class);
        $entries=$statement->query($customerId,$request)->paginate(20,['*'],'entries_page',(int)$request->get('entries_page',$request->get('page',1)));
        $entries->setCollection(collect($statement->decorate($entries->items())));
        return response()->json([
            'purchase_balance' => (float) $customer->wallet_balance,
            'subscription' => app(\App\Services\CustomerMonthlySubscriptionService::class)->status($customerId),
            'purchase_enabled' => getWebConfig('wallet_status') == 1,
            'insurance_enabled' => false,
            'tax_enabled' => getWebConfig(name: 'customer_second_invoice_tax_enabled') == 1,
            'pending_invoices_count' => $pendingInvoicesCount,
            'pending_invoices' => $pendingInvoicesQuery->with('order:id,order_group_id,order_amount')
                ->latest('id')->limit(5)->get()->map(fn (PostPurchaseInvoice $invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'order_id' => $invoice->order_id,
                    'order_group_id' => $invoice->order?->order_group_id,
                    'status' => $invoice->status,
                    'insurance_amount' => (float) $invoice->insurance_amount,
                    'tax_amount' => (float) $invoice->tax_amount,
                    'total_amount' => (float) $invoice->total_amount,
                    'paid_amount' => (float) $invoice->paid_amount,
                    'amount_due' => max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount),
                    'payment_due_at' => $invoice->payment_due_at?->toIso8601String(),
                ])->values(),
            'offline_methods' => $canDeposit && (getWebConfig('offline_payment')['status'] ?? 0)
                ? \App\Models\OfflinePaymentMethod::where('status', 1)->get(['id', 'method_name', 'payment_channel', 'method_fields', 'method_informations']) : [],
            'digital_methods' => $canDeposit && (getWebConfig('digital_payment')['status'] ?? 0)
                ? \App\Utils\payment_gateways()->map(fn ($gateway) => ['key' => $gateway->key_name])->values() : [],
            'deposits' => \App\Models\CustomerBalanceDeposit::where('customer_id', $customerId)->where('wallet_type', 'purchase')->latest('id')
                ->paginate(20, ['id', 'wallet_type', 'amount', 'status', 'method_name', 'payment_reference', 'review_note', 'created_at'],'deposits_page',(int)$request->get('deposits_page',$request->get('page',1))),
            'purchase_entries' => $entries,
            'insurance_entries' => [],
            'orders' => \App\Models\Order::where('customer_id', $customerId)->where('is_guest', 0)
                ->with(['insurance', 'postPurchaseInvoice'])->latest('id')->paginate(20)->through(fn ($order) => [
                    'id' => $order->id, 'amount' => $order->order_amount,
                    'payment_status' => $order->payment_status, 'created_at' => $order->created_at,
                    'insurance' => $order->insurance ? collect($order->insurance->toArray())->only([
                        'amount', 'insurance_amount', 'status', 'maturity_at', 'matures_at', 'released_at',
                    ]) : ($order->postPurchaseInvoice ? [
                        'amount' => $order->postPurchaseInvoice->insurance_amount,
                        'status' => $order->postPurchaseInvoice->status, 'matures_at' => null,
                    ] : null),
                ]),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer|min:1|max:100',
            'offset' => 'required|integer|min:1',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)]);
        }

        $walletStatus = getWebConfig(name: 'wallet_status');

        if ($walletStatus == 1) {
            $user = $request->user();
            $totalWalletBalance = $user->wallet_balance;


            $types = json_decode($request->get('transaction_types', ''), true) ?? [];
            $transactionTypes = $this->getSelectTransactionTypes(types: $types);
            if (request()->has('start_date') && request()->has('end_date') && !checkDateFormatInMDY($request['start_date']) && !checkDateFormatInMDY($request['end_date'])) {
                $startDate = Carbon::createFromFormat('m/d/Y h:i:s a', $request['start_date'])->format('Y-m-d') . ' 00:00:00';
                $endDate = Carbon::createFromFormat('m/d/Y h:i:s a', $request['end_date'])->format('Y-m-d') . ' 23:59:59';
            } else {
                $startDate = '';
                $endDate = '';
            }

            $walletTransactionList = WalletTransaction::where(['user_id' => $user->id])
                ->when($request->has('filter_by') && in_array($request['filter_by'], ['debit', 'credit']), function ($query) use ($request) {
                    $query->when($request['filter_by'] == 'debit', function ($query) {
                        $query->where('debit', '!=', 0);
                    })->when($request['filter_by'] == 'credit', function ($query) {
                        $query->where('debit', '=', 0);
                    });
                })
                ->when(!empty($startDate) && !empty($endDate), function ($query) use ($startDate, $endDate) {
                    return $query->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->when(!empty($transactionTypes) || in_array('added_via_payment_method', $types) || in_array('earned_by_referral', $types), function ($query) use ($transactionTypes, $types) {
                    $query->where(function ($subResult) use ($transactionTypes, $types) {
                        if (!empty($transactionTypes)) {
                            $subResult->whereIn('transaction_type', $transactionTypes);
                        }
                        if (in_array('added_via_payment_method', $types)) {
                            $subResult->orWhere('reference', 'add_funds_to_wallet');
                        }
                        if (in_array('earned_by_referral', $types)) {
                            $subResult->orWhere('reference', 'earned_by_referral');
                        }
                    });
                })
                ->latest()
                ->paginate($request['limit'], ['*'], 'page', $request['offset']);

            return response()->json([
                'limit' => (integer)$request['limit'],
                'offset' => (integer)$request['offset'],
                'total_wallet_balance' => $totalWalletBalance,
                'subscription' => app(\App\Services\CustomerMonthlySubscriptionService::class)->status((int) $user->id),
                'insurance_enabled' => false,
                'insurance_available_balance' => 0,
                'insurance_held_balance' => 0,
                'insurance_next_maturity_at' => null,
                'insurance_ledger_entries' => [],
                'total_size' => $walletTransactionList->total(),
                'wallet_transaction_list' => app(\App\Services\CustomerWalletStatementService::class)->decorate($walletTransactionList->items()),
                'filter_by' => $request['filter_by'],
                'start_date' => $request['start_date'],
                'end_date' => $request['end_date'],
                'transaction_types' => (array)$types,
            ], 200);
        } else {
            return response()->json(['message' => translate('access_denied!')], 422);
        }
    }

    public function bonus_list(Request $request): JsonResponse
    {
        $addFundBonusCategories = AddFundBonusCategories::active()
            ->whereDate('start_date_time', '<=', now())
            ->whereDate('end_date_time', '>=', now())
            ->get();
        return response()->json(['bonus_list' => $addFundBonusCategories], 200);
    }

    public function getSelectTransactionTypes($types): array
    {
        $typeMapping = [
            'order_refund' => 'order_refund',
            'order_place' => 'order_place',
            'loyalty_point' => 'loyalty_point',
            'add_fund' => 'add_fund',
            'add_fund_by_admin' => 'add_fund_by_admin',
            'due_payment_for_order' => 'due_payment_for_order',
            'return_order_amount_by_admin' => 'return_order_amount_by_admin',
            'monthly_subscription' => 'monthly_subscription',
        ];

        foreach ($typeMapping as $key => $value) {
            if (in_array($key, $types)) {
                $transactionTypes[] = $value;
            }
        }

        return $transactionTypes ?? [];
    }
}
