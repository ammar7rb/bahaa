@extends('layouts.admin.app')

@section('title', translate('orders_waiting_for_admin_decision'))

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h1 class="page-header-title mb-1">{{ translate('orders_waiting_for_admin_decision') }}</h1><p class="text-muted mb-0">{{ translate('complete_shipping_before_vendor_release') }}</p></div>
        <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="{{ route('admin.report.vendor-fulfillment.index') }}">متابعة تنفيذ التجار والمطابقة</a><a class="btn btn-outline-primary" href="{{ route('admin.orders.logistics.index') }}">{{ translate('logistics_center') }}</a></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('admin.orders.admin-gate.index', ['fulfillment'=>'restricted']) }}">طلبات التاجر المقيدة كليًا أو جزئيًا</a><a class="btn btn-outline-danger" href="{{ route('admin.orders.admin-gate.index', ['fulfillment'=>'pending_vendor']) }}">طلبات معلقة من جهة التاجر</a></div>
        @foreach([['waiting','pending_admin_review','primary'],['assignment','assignment_in_progress','warning'],['investigations','open_investigations','danger']] as [$key,$label,$tone])
            <div class="col-md-4"><div class="card h-100 border-{{ $tone }}"><div class="card-body"><span class="text-muted">{{ translate($label) }}</span><h2 class="mb-0">{{ $counts[$key] }}</h2></div></div></div>
        @endforeach
    </div>

    <form class="card card-body mb-3" method="get">
        @if(in_array(request('fulfillment'), ['restricted','pending_vendor'], true))<input type="hidden" name="fulfillment" value="{{ request('fulfillment') }}">@endif
        <div class="row g-2 align-items-end">
            <div class="col-lg-2"><label class="form-label">{{ translate('search') }}</label><input class="form-control" name="q" value="{{ request('q') }}" placeholder="ORD-100 / transaction"></div>
            <div class="col-lg-2"><label class="form-label">{{ translate('payment_status') }}</label><select class="form-control" name="payment_status"><option value="">{{ translate('all') }}</option><option value="paid" @selected(request('payment_status')==='paid')>{{ translate('paid') }}</option><option value="unpaid" @selected(request('payment_status')==='unpaid')>{{ translate('unpaid') }}</option></select></div>
            <div class="col-lg-2"><label class="form-label">{{ translate('shipping') }}</label><select class="form-control" name="shipping_status"><option value="">{{ translate('all') }}</option><option value="pending" @selected(request('shipping_status')==='pending')>{{ translate('pending') }}</option><option value="assigned" @selected(request('shipping_status')==='assigned')>{{ translate('assigned') }}</option></select></div>
            <div class="col-lg-2"><label class="form-label">{{ translate('risk_level') }}</label><select class="form-control" name="risk_level"><option value="">{{ translate('all') }}</option>@foreach(['low','medium','high','critical'] as $level)<option value="{{ $level }}" @selected(request('risk_level')===$level)>{{ translate($level) }}</option>@endforeach</select></div>
            <div class="col-lg-1"><button class="btn btn-primary w-100">{{ translate('filter') }}</button></div>
        </div>
    </form>

    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>{{ translate('order') }}</th><th>{{ translate('customer') }}</th><th>{{ translate('vendor') }}</th><th>{{ translate('purchase_payment') }}</th><th>{{ translate('shipping') }}</th><th>{{ translate('flow_status') }}</th><th>{{ translate('action') }}</th></tr></thead>
        <tbody>
        @forelse($orders as $order)
            @php($openRisk = $order->riskInvestigations->firstWhere('status','open'))
            @php($eligibilitySummary = app(\App\Services\VendorFulfillmentEligibilityService::class)->summary($order))
            <tr>
                <td><strong>ORD-{{ $order->id }}</strong><div class="text-muted fs-12">{{ dateFormat($order->created_at) }}</div>@include('admin-views.order.partials._checkout-origin')</td>
                <td>C{{ $order->customer_id }}<div class="text-muted fs-12">{{ $order->customer?->f_name }} {{ $order->customer?->l_name }}</div></td>
                <td>M{{ $order->seller_id }}<div class="text-muted fs-12">{{ $order->seller?->shop?->name }}</div>@if($eligibilitySummary['has_restrictions'] ?? false)<span class="badge bg-danger">{{ $eligibilitySummary['pending_on_vendor_side'] ? 'طلبات معلقة من جهة التاجر' : ($eligibilitySummary['partially_locked'] ? 'مقيد جزئيًا' : 'مقيد كليًا') }}</span><div class="small">تحويل: {{ $eligibilitySummary['forwarded_at'] }}</div>@endif</td>
                <td><span class="badge bg-{{ $order->payment_status==='paid'?'success':'warning' }}">{{ translate($order->payment_status) }}</span><div>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $order->order_amount)) }}</div></td>
                <td><span class="badge bg-{{ ($order->shipping_assignment_status??'pending')==='assigned'?'success':'warning' }}">{{ translate($order->shipping_assignment_status??'pending') }}</span></td>
                <td><span class="badge bg-{{ $openRisk?'danger':'info' }}">{{ translate($order->commerce_flow_status) }}</span>@if($openRisk)<div class="text-danger fs-12">{{ translate($openRisk->risk_level) }}</div>@endif</td>
                <td><a class="btn btn-sm btn-primary" href="{{ route('admin.orders.admin-gate.show',$order->id) }}">{{ translate('review_and_decide') }}</a></td>
            </tr>
        @empty<tr><td colspan="7" class="text-center p-5">{{ translate('no_data_found') }}</td></tr>@endforelse
        </tbody>
    </table></div><div class="card-footer">{{ $orders->links() }}</div></div>
</div>
@endsection
