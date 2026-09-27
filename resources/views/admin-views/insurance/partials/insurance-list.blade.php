@php
    $isCustomer = $partyType === 'customer';
    $tab = $isCustomer ? 'customers' : 'sellers';
@endphp

<div class="mb-3">
    <h2 class="h4 mb-1">{{ translate($isCustomer ? 'Customer_Insurance_Records' : 'Seller_Insurance_Records') }}</h2>
    <p class="text-muted mb-0">{{ translate($isCustomer ? 'customer_insurance_tab_help' : 'seller_insurance_tab_help') }}</p>
</div>

<div class="card insurance-panel mb-3">
    <div class="card-body">
        <form method="get" action="{{ route('admin.insurance-center.index') }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="section" value="records">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5"><label class="form-label" for="insurance-search">{{ translate('Search') }}</label><input id="insurance-search" class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ translate('insurance_search_placeholder') }}"></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label" for="insurance-payment-status">{{ translate('Payment_Status') }}</label><select id="insurance-payment-status" class="form-select" name="payment_status"><option value="">{{ translate('All') }}</option>@foreach(['paid','unpaid'] as $status)<option value="{{ $status }}" @selected(request('payment_status')===$status)>{{ translate($status) }}</option>@endforeach</select></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label" for="insurance-record-status">{{ translate('Insurance_Status') }}</label><select id="insurance-record-status" class="form-select" name="status"><option value="">{{ translate('All') }}</option>@foreach($insuranceStatuses as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ translate($status) }}</option>@endforeach</select></div>
                <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1">{{ translate('Filter') }}</button><a class="btn btn-outline-secondary" href="{{ route('admin.insurance-center.index',['tab'=>$tab,'section'=>'records']) }}">{{ translate('Reset') }}</a></div>
            </div>
        </form>
    </div>
</div>

<div class="card insurance-panel mb-3">
    <div class="card-header"><h3 class="h5 mb-0">{{ translate($isCustomer ? 'Customer_Insurance_Records' : 'Seller_Insurance_Records') }}</h3></div>
    <div class="table-responsive">
        <table class="table table-hover insurance-table mb-0">
            <thead><tr><th>{{ translate('Owner') }}</th><th>{{ translate('Order_ID') }}</th><th>{{ translate('Insurance_Amount') }}</th><th>{{ translate('Insurance_Status') }}</th><th>{{ translate('Paid_At') }}</th><th>{{ translate('Action') }}</th></tr></thead>
            <tbody>
            @forelse($insurances as $insurance)
                @php($owner = $isCustomer ? $insurance->customer : $insurance->seller)
                <tr>
                    <td>@if($owner)<a href="{{ route('admin.insurance-center.accounts.show', [$partyType, $owner->id]) }}"><strong>{{ trim(($owner->f_name ?? '').' '.($owner->l_name ?? '')) ?: $owner->email }}</strong></a>@else<strong>{{ translate('Unknown') }}</strong>@endif<div class="small text-muted">{{ $isCustomer ? 'C' : 'V' }}{{ $owner?->id }} · #{{ $insurance->id }} @if($owner?->phone) · {{ $owner->phone }} @endif</div></td>
                    <td><a href="{{ route('admin.orders.details',['id'=>$insurance->order_id]) }}">#{{ $insurance->order_id }}</a></td>
                    <td><strong>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insurance->amount)) }}</strong>@if(($insurance->confiscated_amount ?? 0) > 0)<div class="small text-muted">{{ translate('Confiscated') }}: {{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insurance->confiscated_amount)) }}</div>@endif</td>
                    <td><span class="badge badge-soft-{{ $insurance->payment_status === 'paid' ? 'success' : 'warning' }}">{{ translate($insurance->payment_status) }}</span><div class="small mt-1">{{ translate($insurance->status) }}</div></td>
                    <td>{{ $isCustomer ? ($insurance->payment_completed_at?->format('Y-m-d') ?? '—') : ($insurance->paid_at?->format('Y-m-d') ?? '—') }}</td>
                    <td><a class="btn btn-outline-primary btn-sm" href="{{ $isCustomer ? route('admin.insurance-center.customers.show',$insurance) : route('admin.insurance-center.sellers.show',$insurance) }}">{{ translate('View_Insurance_Details') }}</a></td>
                </tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-5">{{ translate('no_data_found') }}</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($insurances->hasPages())<div class="card-footer">{{ $insurances->links() }}</div>@endif
</div>
