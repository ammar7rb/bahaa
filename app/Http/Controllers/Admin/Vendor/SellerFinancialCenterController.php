<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\SellerBalanceDeposit;
use App\Models\SellerLedgerEntry;
use App\Models\SellerSettlement;
use App\Services\SellerSettlementService;
use Carbon\Carbon;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use App\Models\WithdrawRequest;
use App\Services\SellerLedgerService;
use App\Services\FinancialReconciliationService;
use App\Utils\Helpers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SellerFinancialCenterController extends Controller
{
    public function __construct(private readonly SellerSettlementService $settlements) {}

    public function legacyIndex(Request $request)
    {
        return $this->index($request);
    }

    public function index(Request $request)
    {
        $request->validate(['seller_id'=>'nullable|integer|min:1','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from','bucket'=>'nullable|in:sales_total,pending,available,operating,pending_withdraw,withdrawn','settlement_status'=>'nullable|in:held,disputed,released,reversed','settlement_q'=>'nullable|string|max:100']);
        $query = $request->only(['seller_id','from','to','event_type']);
        $query['tab'] = $request->filled('settlement_status') || $request->boolean('overdue') ? 'settlements' : 'vendor_accounts';
        $query['register'] = 'ledger';
        if ($request->filled('settlement_status')) $query['status'] = $request->get('settlement_status');
        if ($request->boolean('overdue')) $query['settlement_window'] = 'overdue';
        if (in_array($request->get('bucket'), \App\Services\Admin\VendorFinancialsReadService::BUCKETS, true)) $query['bucket'] = $request->get('bucket');
        if ($request->filled('settlement_q')) $query['q'] = $request->get('settlement_q');
        if (!$request->filled('from') && !$request->filled('to') && $query['tab'] === 'vendor_accounts') $query['all_dates'] = 1;
        return redirect()->route('admin.report.financials.company',$query);
    }

    public function updateSettlementDueDate(Request $request, int|string $id)
    {
        $request->validate(['due_at' => 'required|date', 'review_note' => 'nullable|string|max:1000']);
        try {
            $settlement = $this->settlements->setDueAt(
                (int) $id,
                Carbon::parse($request->get('due_at')),
                auth('admin')->id(),
                $request->get('review_note'),
            );
            if (! $settlement) {
                throw new \DomainException('seller_settlement_not_editable');
            }
            ToastMagic::success(translate('seller_settlement_due_date_updated'));
        } catch (\Throwable $exception) {
            ToastMagic::error(translate($exception->getMessage()));
        }
        return back();
    }

    public function releaseSettlement(int|string $id)
    {
        try {
            if (! $this->settlements->release((int) $id)) {
                throw new \DomainException('seller_settlement_not_due');
            }
            ToastMagic::success(translate('seller_settlement_released'));
        } catch (\Throwable $exception) {
            ToastMagic::error(translate($exception->getMessage()));
        }
        return back();
    }

    public function disputeSettlement(Request $request, int|string $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);
        try {
            if (! $this->settlements->dispute((int) $id, $request->get('reason'))) {
                throw new \DomainException('seller_settlement_not_held');
            }
            ToastMagic::success(translate('seller_settlement_disputed'));
        } catch (\Throwable $exception) {
            ToastMagic::error(translate($exception->getMessage()));
        }
        return back();
    }

    public function resumeSettlementReview(Request $request, int|string $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);
        try {
            if (!$this->settlements->resumeAfterReview((int)$id, $request->string('reason')->toString(), (int)auth('admin')->id())) {
                throw new \DomainException('Settlement is not paused for review.');
            }
            ToastMagic::success(__('company-financials.resume_success'));
        } catch (\DomainException $exception) {
            ToastMagic::error($exception->getMessage());
        }
        return back();
    }

    public function reverseSettlement(Request $request, int|string $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);
        try {
            if (! $this->settlements->reverse((int) $id, $request->get('reason'), auth('admin')->id())) {
                throw new \DomainException('seller_settlement_not_reversible');
            }
            ToastMagic::success(translate('seller_settlement_reversed'));
        } catch (\Throwable $exception) {
            ToastMagic::error(translate($exception->getMessage()));
        }
        return back();
    }

}
