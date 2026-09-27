@extends('layouts.admin.app')

@section('title', translate('Insurance_Details'))

@section('content')
@php
    $isCustomer = $partyType === 'customer';
    $ownerCode = ($isCustomer ? 'C' : 'V').($party?->id ?? '—');
    $paidAt = $isCustomer ? $insurance->payment_completed_at : $insurance->paid_at;
    $remainingConfiscatable = max(0, (float)$insurance->amount - (float)($insurance->confiscated_amount ?? 0));
@endphp
<div class="content container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h1 class="mb-1">{{ translate($isCustomer ? 'Customer_Insurance_Details' : 'Seller_Insurance_Details') }} #{{ $insurance->id }}</h1><p class="text-muted mb-0">{{ translate('insurance_details_help') }}</p></div>
        <a class="btn btn-outline-primary" href="{{ route('admin.insurance-center.index',['tab'=>$isCustomer?'customers':'sellers']) }}">{{ translate('Back_To_Insurance_List') }}</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><h3 class="mb-0">{{ translate('Insurance_And_Order_Information') }}</h3></div>
                <div class="card-body"><div class="row g-3">
                    @foreach([
                        ['Owner',$ownerCode.' — '.(trim(($party?->f_name ?? '').' '.($party?->l_name ?? '')) ?: ($party?->email ?? translate('Unknown')))],
                        ['Order_ID','#'.$insurance->order_id],
                        ['Insurance_Amount',setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insurance->amount))],
                        ['Order_Amount',setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insurance->order_amount))],
                        ['Payment_Status',translate($insurance->payment_status)],
                        ['Insurance_Status',translate($insurance->status)],
                        ['Payment_Method',translate($insurance->payment_method ?: 'Not_Set')],
                        ['Paid_At',$paidAt ?: '—'],
                        ['Created_At',$insurance->created_at],
                        ['Maturity_Or_Reuse_Date',$isCustomer ? ($insurance->matures_at ?: '—') : ($insurance->reusable_at ?: '—')],
                        ['Confiscation_Status',translate($insurance->confiscation_status ?: 'none')],
                        ['Confiscated_Insurance_Amount',setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insurance->confiscated_amount ?? 0))],
                    ] as [$label,$value])
                        <div class="col-md-4"><small class="text-muted d-block">{{ translate($label) }}</small><strong>{{ $value }}</strong></div>
                    @endforeach
                </div></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-header"><h3 class="mb-0">{{ translate('Owner_Insurance_Balance') }}</h3></div><div class="card-body">
                <div class="d-flex justify-content-between mb-3"><span>{{ translate('Available_Insurance_Balance') }}</span><strong>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$availableBalance)) }}</strong></div>
                <div class="d-flex justify-content-between mb-3"><span>{{ translate('Administratively_Held') }}</span><strong>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$heldBalance)) }}</strong></div>
                <div class="alert alert-info mb-0">{{ translate('insurance_record_confiscation_help') }}</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @if($isCustomer && $insurance->payment_method === 'offline_payment' && $insurance->payment_status === 'unpaid' && $insurance->status === 'pending_review')
        <div class="col-12">
            <div class="card border-warning"><div class="card-header"><h3 class="mb-1">{{ translate('Review_Customer_Insurance_Offline_Payment') }}</h3><p class="text-muted mb-0">{{ translate('review_customer_insurance_offline_payment_help') }}</p></div><div class="card-body">
                @php($offline = $insurance->metadata['offline_payment'] ?? [])
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><small class="text-muted d-block">{{ translate('Payment_Method') }}</small><strong>{{ $offline['method_name'] ?? '—' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">{{ translate('Payment_Note') }}</small><strong>{{ $offline['payment_note'] ?? '—' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">{{ translate('Submitted_At') }}</small><strong>{{ $offline['submitted_at'] ?? '—' }}</strong></div>
                </div>
                <form method="post" action="{{ route('admin.insurance-center.balance-actions.customer-insurance.offline-review',$insurance) }}">@csrf
                    <div class="row g-3 align-items-end"><div class="col-md-8"><label class="form-label">{{ translate('Review_Reason') }}</label><textarea class="form-control" name="reason" rows="2" required></textarea></div><div class="col-md-4 d-flex gap-2"><button class="btn btn-success flex-fill" name="decision" value="approve">{{ translate('Approve') }}</button><button class="btn btn-danger flex-fill" name="decision" value="reject">{{ translate('Reject') }}</button></div></div>
                </form>
            </div></div>
        </div>
        @endif
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header"><h3 class="mb-1">{{ translate('Confiscate_From_This_Insurance') }}</h3><p class="text-muted mb-0">{{ translate('insurance_confiscation_affects_insurance_only_not_purchase_value') }}</p></div><div class="card-body">
                @if($insurance->payment_status === 'paid' && $remainingConfiscatable > 0)
                    <form method="post" action="{{ $isCustomer ? route('admin.insurance-center.balance-actions.customer-insurance.confiscate',$insurance) : route('admin.insurance-center.balance-actions.seller-insurance.confiscate',$insurance) }}">@csrf
                        <input type="hidden" name="request_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
                        <div class="mb-3"><label class="form-label">{{ translate('Amount') }}</label><input class="form-control" type="number" name="amount" min="0.01" step="0.01" max="{{ $remainingConfiscatable }}" value="{{ $remainingConfiscatable }}" required><small class="text-muted">{{ translate('Maximum') }}: {{ $remainingConfiscatable }}</small></div>
                        <div class="mb-3"><label class="form-label">{{ translate('Reason') }}</label><textarea class="form-control" name="reason" rows="3" required></textarea></div>
                        <div class="mb-3"><label class="form-label">{{ translate('Evidence_Note') }}</label><textarea class="form-control" name="evidence_note" rows="2"></textarea></div>
                        <button class="btn btn-danger w-100" onclick="return confirm('{{ translate('confirm_insurance_confiscation') }}')">{{ translate('Confirm_Insurance_Confiscation') }}</button>
                    </form>
                @else
                    <div class="alert alert-warning mb-0">{{ translate($insurance->payment_status !== 'paid' ? 'only_paid_insurance_can_be_confiscated' : 'insurance_is_already_fully_confiscated') }}</div>
                @endif
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header"><h3 class="mb-1">{{ translate('Notify_Insurance_Owner') }}</h3><p class="text-muted mb-0">{{ translate($isCustomer ? 'customer_insurance_notice_help' : 'seller_insurance_notice_help') }}</p></div><div class="card-body">
                <form method="post" action="{{ $isCustomer ? route('admin.insurance-center.customers.notify',$insurance) : route('admin.insurance-center.sellers.notify',$insurance) }}">@csrf
                    <div class="mb-3"><label class="form-label">{{ translate('Message') }}</label><textarea class="form-control" name="message" rows="7" required></textarea></div>
                    <button class="btn btn-primary w-100">{{ translate('Send_Notice') }}</button>
                </form>
            </div></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="mb-1">{{ translate('Rule_Snapshot') }}</h3><p class="text-muted mb-0">{{ translate('rule_snapshot_help') }}</p></div>
        <div class="card-body"><pre class="bg-light rounded p-3 mb-0" style="white-space:pre-wrap">{{ json_encode($insurance->rule_snapshot ?: [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="mb-0">{{ translate('Insurance_Governance_History') }}</h3></div>
        <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>#</th><th>{{ translate('Action') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Reason') }}</th><th>{{ translate('Reference') }}</th><th>{{ translate('Created_By') }}</th><th>{{ translate('Created_At') }}</th></tr></thead><tbody>
            @forelse($actions as $action)<tr><td>{{ $action->id }}</td><td>{{ translate('insurance_action_'.$action->action) }}</td><td>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$action->amount)) }}</td><td>{{ $action->reason }}</td><td>{{ $action->reference }}</td><td>{{ $action->admin_id ?: translate('System') }}</td><td>{{ $action->created_at }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted">{{ translate('no_data_found') }}</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>
@endsection
