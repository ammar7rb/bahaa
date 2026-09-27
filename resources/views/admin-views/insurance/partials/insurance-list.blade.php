@php
    $isCustomer = $partyType === 'customer';
    $prefix = $isCustomer ? 'customer' : 'seller';
    $paidCount = $insuranceStats[$prefix.'_paid'];
    $paidAmount = $insuranceStats[$prefix.'_paid_amount'];
    $confiscatedAmount = $insuranceStats[$prefix.'_confiscated'];
@endphp

<div class="alert alert-info mb-3">
    <h3 class="fs-16 mb-1">{{ translate($isCustomer ? 'Customer_Insurance_Tab' : 'Seller_Insurance_Tab') }}</h3>
    <p class="mb-0">{{ translate($isCustomer ? 'customer_insurance_tab_help' : 'seller_insurance_tab_help') }}</p>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ translate('All_Insurance_Records') }}</small><h2>{{ $insuranceStats[$prefix.'_total'] }}</h2></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ translate('Paid_Insurances') }}</small><h2>{{ $paidCount }}</h2></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ translate('Paid_Insurance_Amount') }}</small><h3>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$paidAmount)) }}</h3></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ translate('Confiscated_Insurance_Amount') }}</small><h3>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$confiscatedAmount)) }}</h3></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="{{ route('admin.insurance-center.index') }}">
            <input type="hidden" name="tab" value="{{ $isCustomer ? 'customers' : 'sellers' }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-5"><label class="form-label">{{ translate('Search') }}</label><input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ translate('insurance_search_placeholder') }}"></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Payment_Status') }}</label><select class="form-select" name="payment_status"><option value="">{{ translate('All') }}</option>@foreach(['paid','unpaid'] as $status)<option value="{{ $status }}" @selected(request('payment_status')===$status)>{{ translate($status) }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">{{ translate('Insurance_Status') }}</label><input class="form-control" name="status" value="{{ request('status') }}" placeholder="{{ translate('Status') }}"></div>
                <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1">{{ translate('Filter') }}</button><a class="btn btn-outline-secondary" href="{{ route('admin.insurance-center.index',['tab'=>$isCustomer?'customers':'sellers']) }}">{{ translate('Reset') }}</a></div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="mb-0">{{ translate($isCustomer ? 'Customer_Insurance_Records' : 'Seller_Insurance_Records') }}</h3></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>#</th><th>{{ translate('Owner') }}</th><th>{{ translate('Order_ID') }}</th><th>{{ translate('Insurance_Amount') }}</th><th>{{ translate('Payment_Status') }}</th><th>{{ translate('Insurance_Status') }}</th><th>{{ translate('Paid_At') }}</th><th>{{ translate('Confiscated') }}</th><th>{{ translate('Action') }}</th></tr></thead>
            <tbody>
            @forelse($insurances as $insurance)
                @php($owner = $isCustomer ? $insurance->customer : $insurance->seller)
                <tr>
                    <td>{{ $insurance->id }}</td>
                    <td><strong>{{ $isCustomer ? 'C' : 'V' }}{{ $owner?->id }}</strong><div>{{ trim(($owner?->f_name ?? '').' '.($owner?->l_name ?? '')) ?: ($owner?->email ?? translate('Unknown')) }}</div><small class="text-muted">{{ $owner?->phone }}</small></td>
                    <td><a href="{{ route('admin.orders.details',['id'=>$insurance->order_id]) }}">#{{ $insurance->order_id }}</a></td>
                    <td>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insurance->amount)) }}</td>
                    <td><span class="badge badge-soft-{{ $insurance->payment_status === 'paid' ? 'success' : 'warning' }}">{{ translate($insurance->payment_status) }}</span></td>
                    <td>{{ translate($insurance->status) }}</td>
                    <td>{{ $isCustomer ? ($insurance->payment_completed_at ?? '—') : ($insurance->paid_at ?? '—') }}</td>
                    <td>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insurance->confiscated_amount ?? 0)) }}</td>
                    <td><a class="btn btn-outline-primary btn-sm" href="{{ $isCustomer ? route('admin.insurance-center.customers.show',$insurance) : route('admin.insurance-center.sellers.show',$insurance) }}">{{ translate('View_Insurance_Details') }}</a></td>
                </tr>
            @empty<tr><td colspan="9" class="text-center text-muted py-5">{{ translate('no_data_found') }}</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($insurances->hasPages())<div class="card-footer">{{ $insurances->links() }}</div>@endif
</div>
