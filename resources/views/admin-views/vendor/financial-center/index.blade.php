@extends('layouts.admin.app')

@section('title', translate('seller_financial_center'))

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h1 mb-1">{{ translate('seller_financial_center') }}</h2>
            <p class="text-muted mb-0">{{ translate('seller_financial_center_note') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" target="_blank" href="{{ route('admin.report.operational-print', array_merge(request()->query(), ['section' => 'finances'])) }}">{{ translate('print_report') }}</a>
            <a class="btn btn-outline-primary" href="{{ route('admin.vendors.balance-deposits.index') }}">{{ translate('seller_balance_deposits') }}</a>
            <a class="btn btn-outline-primary" href="{{ route('admin.vendors.withdraw_list') }}">{{ translate('withdraw') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach([
            'sales_total' => 'Sales_Total',
            'pending' => 'Pending_Balance',
            'available' => 'Available_Balance',
            'operating' => 'Operating_Balance',
            'order_insurance_credit' => 'order_insurance_credit',
            'pending_withdraw' => 'Pending_Withdraw',
            'withdrawn' => 'Withdrawn',
        ] as $bucket => $label)
            <div class="col-sm-6 col-xl-2">
                <div class="card h-100"><div class="card-body">
                    <div class="fs-12 text-muted">{{ translate($label) }}</div>
                    <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $ledgerTotals[$bucket])) }}</strong>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="mb-1">{{ translate('financial_reconciliation') }}</h3><small class="text-muted">{{ translate('financial_reconciliation_note') }}</small></div>
        <div class="card-body">
            <div class="row g-2">
                @foreach([
                    'sales_revenue','admin_commission','shipping_customer_revenue','shipping_seller_cost','shipping_seller_entitlement',
                    'seller_package_revenue','customer_insurance_received','seller_insurance_received','refunds',
                    'settlements_held','settlements_released','settlements_reversed','ledger_net'
                ] as $metric)
                    <div class="col-sm-6 col-lg-3"><div class="bg-section rounded p-3 h-100"><small class="text-muted">{{ translate($metric) }}</small><div class="fw-bold mt-1">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $reconciliation[$metric] ?? 0)) }}</div></div></div>
                @endforeach
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <span class="badge {{ ($reconciliation['missing_references'] ?? 0) ? 'bg-danger' : 'bg-success' }}">{{ translate('missing_references') }}: {{ $reconciliation['missing_references'] ?? 0 }}</span>
                <span class="badge {{ ($reconciliation['duplicate_idempotency_keys'] ?? 0) ? 'bg-danger' : 'bg-success' }}">{{ translate('duplicate_idempotency_keys') }}: {{ $reconciliation['duplicate_idempotency_keys'] ?? 0 }}</span>
            </div>
            @if($reconciliation['insurance_wallets'] ?? null)
                @php($insuranceReconciliation = $reconciliation['insurance_wallets'])
                <div class="border rounded p-3 mt-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong>{{ translate('Insurance_Wallet_Reconciliation') }}</strong><span class="badge {{ $insuranceReconciliation['is_reconciled'] ? 'bg-success':'bg-danger' }}">{{ translate($insuranceReconciliation['is_reconciled'] ? 'Reconciled':'Needs_Review') }}</span></div>
                    <div class="row g-2">
                        @foreach(['customer_insurance_balance','seller_insurance_balance','insurance_held_by_admin','insurance_net_confiscated'] as $metric)
                            <div class="col-sm-6 col-lg-3"><small class="text-muted">{{ translate($metric) }}</small><div class="fw-bold">{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insuranceReconciliation[$metric] ?? 0)) }}</div></div>
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @foreach(['legacy_customer_purchase_movements','new_customer_ledger_policy_violations','seller_operating_insurance_violations','purchase_wallet_insurance_violations'] as $metric)
                            <span class="badge {{ ($metric === 'legacy_customer_purchase_movements' || !($insuranceReconciliation[$metric] ?? 0)) ? 'bg-secondary':'bg-danger' }}">{{ translate($metric) }}: {{ $insuranceReconciliation[$metric] ?? 0 }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h3 class="mb-1">{{ translate('seller_settlements') }}</h3>
                <small class="text-muted">{{ translate('seller_settlements_admin_review_note') }}</small>
            </div>
            <div class="d-flex flex-wrap gap-2 small">
                <span class="badge bg-warning text-dark">{{ translate('held') }}: {{ $settlementSummary['held'] }}</span>
                <span class="badge bg-danger">{{ translate('overdue') }}: {{ $settlementSummary['overdue'] }}</span>
                <span class="badge bg-danger">{{ translate('escalated') }}: {{ $settlementSummary['escalated'] }}</span>
                <span class="badge bg-info text-dark">{{ translate('due_soon') }}: {{ $settlementSummary['due_soon'] }}</span>
                <span class="badge bg-secondary">{{ translate('disputed') }}: {{ $settlementSummary['disputed'] }}</span>
                <span class="badge bg-success">{{ translate('released') }}: {{ $settlementSummary['released'] }}</span>
                <span class="badge bg-dark">{{ translate('reversed') }}: {{ $settlementSummary['reversed'] }}</span>
            </div>
        </div>
        <div class="card-body border-bottom">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label">{{ translate('seller_or_order') }}</label><input class="form-control" name="settlement_q" value="{{ request('settlement_q') }}"></div>
                <div class="col-md-2"><label class="form-label">{{ translate('status') }}</label><select class="form-select" name="settlement_status"><option value="">{{ translate('all_statuses') }}</option>@foreach(['held','disputed','released','reversed'] as $status)<option value="{{ $status }}" @selected(request('settlement_status') === $status)>{{ translate($status) }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label d-block">&nbsp;</label><label class="form-check"><input class="form-check-input" type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))><span class="form-check-label">{{ translate('overdue_only') }}</span></label></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary text-white shadow-sm fw-semibold w-100" style="background-color:#0177cd;border-color:#0177cd;color:#fff">{{ translate('filter') }}</button></div>
            </form>
        </div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>{{ translate('order') }}</th><th>{{ translate('seller') }}</th><th>{{ translate('amount') }}</th><th>{{ translate('status') }}</th><th>{{ translate('due_date') }}</th><th>{{ translate('admin_decision') }}</th></tr></thead><tbody>
        @forelse($settlements as $settlement)
            @php($statusClass = $settlement->status === 'released' ? 'bg-success' : ($settlement->status === 'reversed' ? 'bg-dark' : ($settlement->status === 'disputed' ? 'bg-secondary' : 'bg-warning text-dark')))
            <tr>
                <td>#{{ $settlement->order_id }}<br><small class="text-muted">{{ $settlement->order?->order_status ? translate(str_replace('_', ' ', $settlement->order->order_status)) : '—' }}</small></td>
                <td>{{ $settlement->seller?->email ?? '#'.$settlement->seller_id }}</td>
                <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $settlement->amount)) }}</td>
                <td><span class="badge {{ $statusClass }}">{{ translate($settlement->status) }}</span>@if($settlement->due_at && $settlement->due_at->isPast() && in_array($settlement->status, ['held','disputed']))<br><small class="text-danger">{{ translate('overdue') }}</small>@endif</td>
                <td>
                    {{ $settlement->due_at?->format('Y-m-d H:i') ?? '—' }}
                    <br><small class="text-muted">{{ $settlement->timezone_snapshot ?: data_get($settlement->metadata, 'timezone', config('app.timezone')) }} · {{ translate('alert_before') }} {{ $settlement->pre_due_days_snapshot ?: data_get($settlement->metadata, 'pre_due_alert_days', 5) }} {{ translate('days') }}</small>
                    @if(in_array($settlement->status, ['held','disputed']))
                        <form method="post" action="{{ route('admin.vendors.financial-center.settlements.due-date', $settlement->id) }}" class="mt-1 d-flex gap-1">@csrf<input type="datetime-local" name="due_at" class="form-control form-control-sm" value="{{ $settlement->due_at?->format('Y-m-d\TH:i') }}" required><button class="btn btn-sm btn-outline-primary">{{ translate('save') }}</button></form>
                    @endif
                </td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                    @if($settlement->status === 'held' && $settlement->due_at?->isPast())<form method="post" action="{{ route('admin.vendors.financial-center.settlements.release', $settlement->id) }}">@csrf<button class="btn btn-sm btn-success">{{ translate('release') }}</button></form>@endif
                    @if($settlement->status === 'held')<form method="post" action="{{ route('admin.vendors.financial-center.settlements.dispute', $settlement->id) }}">@csrf<input type="hidden" name="reason" value="{{ translate('manual_finance_review') }}"><button class="btn btn-sm btn-outline-warning">{{ translate('dispute') }}</button></form>@endif
                    @if(in_array($settlement->status, ['held','disputed','released']))<form method="post" action="{{ route('admin.vendors.financial-center.settlements.reverse', $settlement->id) }}" class="d-flex gap-1">@csrf<input class="form-control form-control-sm" name="reason" required placeholder="{{ translate('reason') }}"><button class="btn btn-sm btn-outline-danger">{{ translate('reverse') }}</button></form>@endif
                    </div>
                    @if($settlement->review_note)<small class="text-muted d-block mt-1">{{ $settlement->review_note }}</small>@endif
                </td>
            </tr>
        @empty <tr><td colspan="6" class="text-center text-muted py-4">{{ translate('no_seller_settlements_found') }}</td></tr>@endforelse
        </tbody></table></div>
        @if($settlements->hasPages())<div class="card-footer">{{ $settlements->links() }}</div>@endif
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header"><h3 class="mb-0">{{ translate('deposit_summary') }}</h3></div><div class="table-responsive">
                <table class="table table-sm mb-0"><thead><tr><th>{{ translate('status') }}</th><th>{{ translate('count') }}</th><th>{{ translate('amount') }}</th></tr></thead><tbody>
                @forelse($depositSummary as $status => $summary)
                    <tr><td>{{ translate($status) }}</td><td>{{ $summary['count'] }}</td><td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $summary['amount'])) }}</td></tr>
                @empty <tr><td colspan="3" class="text-muted">{{ translate('no_data_found') }}</td></tr>@endforelse
                </tbody></table>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header"><h3 class="mb-0">{{ translate('withdrawal_summary') }}</h3></div><div class="table-responsive">
                <table class="table table-sm mb-0"><thead><tr><th>{{ translate('status') }}</th><th>{{ translate('count') }}</th><th>{{ translate('amount') }}</th></tr></thead><tbody>
                @foreach([0 => 'Pending', 1 => 'Approved', 2 => 'Denied'] as $status => $label)
                    @php($summary = $withdrawSummary->get($status))
                    <tr><td>{{ translate($label) }}</td><td>{{ $summary?->count ?? 0 }}</td><td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $summary?->amount ?? 0)) }}</td></tr>
                @endforeach
                </tbody></table>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header d-flex justify-content-between"><h3 class="mb-0">{{ translate('Recent_Deposits') }}</h3><a href="{{ route('admin.vendors.balance-deposits.index') }}">{{ translate('View_All') }}</a></div><div class="table-responsive"><table class="table table-sm mb-0"><tbody>
            @forelse($recentDeposits as $deposit)<tr><td>{{ $deposit->seller?->email ?? '#'.$deposit->seller_id }}<small class="d-block">{{ $deposit->transaction_number }}</small></td><td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $deposit->amount)) }}</td><td>{{ translate($deposit->status) }}</td></tr>@empty<tr><td class="text-muted">{{ translate('no_data_found') }}</td></tr>@endforelse
        </tbody></table></div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header d-flex justify-content-between"><h3 class="mb-0">{{ translate('Recent_Withdrawals') }}</h3><a href="{{ route('admin.vendors.withdraw_list') }}">{{ translate('View_All') }}</a></div><div class="table-responsive"><table class="table table-sm mb-0"><tbody>
            @forelse($recentWithdrawals as $withdrawal)<tr><td>{{ $withdrawal->seller?->email ?? '#'.$withdrawal->seller_id }}<small class="d-block">{{ $withdrawal->transaction_number }}</small></td><td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $withdrawal->amount)) }}</td><td>{{ translate($withdrawal->approved === 0 ? 'pending' : ($withdrawal->approved === 1 ? 'approved' : 'denied')) }}</td></tr>@empty<tr><td class="text-muted">{{ translate('no_data_found') }}</td></tr>@endforelse
        </tbody></table></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label">{{ translate('Bucket') }}</label><select name="bucket" class="form-select"><option value="">{{ translate('All') }}</option>@foreach(['sales_total','pending','available','operating','order_insurance_credit','pending_withdraw','withdrawn'] as $bucket)<option value="{{ $bucket }}" @selected(request('bucket') === $bucket)>{{ translate($bucket) }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">{{ translate('Event_Type') }}</label><input class="form-control" name="event_type" value="{{ request('event_type') }}"></div>
                <div class="col-md-2"><label class="form-label">{{ translate('From') }}</label><input type="date" class="form-control" name="from" value="{{ request('from') }}"></div>
                <div class="col-md-2"><label class="form-label">{{ translate('To') }}</label><input type="date" class="form-control" name="to" value="{{ request('to') }}"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary text-white shadow-sm fw-semibold w-100" style="background-color:#0177cd;border-color:#0177cd;color:#fff">{{ translate('Filter') }}</button></div>
            </form>
        </div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>{{ translate('Date') }}</th><th>{{ translate('Seller') }}</th><th>{{ translate('Bucket') }}</th><th>{{ translate('Direction') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Event_Type') }}</th><th>{{ translate('Reference') }}</th></tr></thead><tbody>
        @forelse($entries as $entry)
            <tr><td>{{ $entry->created_at }}</td><td>{{ $entry->seller?->email ?? '#'.$entry->seller_id }}</td><td>{{ translate($entry->bucket) }}</td><td>{{ translate($entry->direction) }}</td><td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: (float) $entry->amount)) }}</td><td>{{ translate(str_replace('_', ' ', $entry->event_type)) }}<br><small class="text-muted">{{ translate($entry->reporting_category ?: 'balance') }}</small></td><td><code>{{ $entry->transaction_number }}</code></td></tr>
        @empty <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('no_ledger_entries_found') }}</td></tr>@endforelse
        </tbody></table></div>
        @if($entries->hasPages())<div class="card-footer">{{ $entries->links() }}</div>@endif
    </div>
</div>
@endsection
