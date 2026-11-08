@extends('layouts.admin.app')

@section('title', translate('admin_order_gate').' ORD-'.$order->id)

@section('content')
@php($openInvestigation = $order->riskInvestigations->firstWhere('status','open'))
@php($sellerResponseStatus = $order->seller_shipping_response_status)
<div class="content container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h1 class="page-header-title mb-1">{{ translate('admin_order_gate') }} — ORD-{{ $order->id }}</h1><p class="text-muted mb-0">{{ translate('complete_shipping_before_vendor_release') }}</p></div>
        <a class="btn btn-outline-secondary" href="{{ route('admin.orders.admin-gate.index') }}">{{ translate('back_to_queue') }}</a>
    </div>

    @if($openInvestigation)<div class="alert alert-danger"><strong>{{ translate('order_is_under_investigation') }}</strong><br>{{ $openInvestigation->reason }}</div>@endif
    @include('admin-views.order.partials._checkout-origin')

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><span class="text-muted">{{ translate('purchase_amount') }}</span><h4>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $order->order_amount)) }}</h4><span class="badge bg-{{ $order->payment_status==='paid'?'success':'warning' }}">{{ translate($order->payment_status) }}</span></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><span class="text-muted">{{ translate('shipping_decision') }}</span><h4>{{ translate($order->shipping_fulfillment_mode??'pending') }}</h4><span class="badge bg-{{ ($order->shipping_assignment_status??'pending')==='assigned'?'success':'warning' }}">{{ translate($order->shipping_assignment_status??'pending') }}</span>@if($sellerResponseStatus)<div class="small mt-2">{{ translate('seller_response') }}: <strong>{{ translate($sellerResponseStatus) }}</strong></div>@endif
        @if(in_array($sellerResponseStatus, ['pending','overdue'], true))<div class="small text-{{ $sellerResponseStatus === 'overdue' ? 'danger' : 'warning' }} mt-2">{{ translate($sellerResponseStatus === 'overdue' ? 'seller_has_not_started_preparing_overdue' : 'seller_has_not_started_preparing') }}</div>@endif
        @if($order->seller_shipping_response_due_at)<div class="small text-muted">{{ translate('due_date') }}: {{ $order->seller_shipping_response_due_at->format('Y-m-d H:i') }}</div>@endif
        @if($order->seller_shipping_support_ticket_id)<div class="small text-danger">{{ translate('support_ticket') }} #{{ $order->seller_shipping_support_ticket_id }}</div>@endif</div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-4">
            <div><span class="text-muted d-block">{{ translate('customer') }}</span><strong>{{ trim(($order->customer?->f_name ?? '').' '.($order->customer?->l_name ?? '')) ?: translate('guest_customer') }}</strong></div>
            <div><span class="text-muted d-block">{{ translate('email') }}</span><strong>{{ $order->customer?->email ?? '—' }}</strong></div>
            <div><span class="text-muted d-block">{{ translate('phone') }}</span><strong>{{ $order->customer?->phone ?? '—' }}</strong></div>
            <div><span class="text-muted d-block">{{ translate('order_items') }}</span><strong>{{ $order->orderDetails->count() }}</strong></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            @if(\Illuminate\Support\Facades\Schema::hasTable('order_work_items'))
            <div class="card mb-3"><div class="card-header"><h4 class="mb-0">حصص المنتجات وقرارات التجار</h4></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>المنتج</th><th>التاجر</th><th>الحالة</th><th>رد التاجر قبل</th><th>العميل / صافي التاجر</th></tr></thead><tbody>
                @foreach($order->workItems()->with(['line','seller.shop'])->get() as $workItem)
                @php($workLine = $workItem->line)
                <tr><td>{{ data_get(json_decode($workLine?->getRawOriginal('product_details') ?? '{}', true), 'name', 'منتج #'.$workItem->product_id) }}<div class="small text-muted">#{{ $workItem->order_detail_id }}</div></td><td>{{ $workItem->seller?->shop?->name ?? ('#'.$workItem->seller_id) }}</td><td>{{ ['awaiting_admin'=>'مراجعة الإدارة','sent_to_vendor'=>'مرسل','accepted'=>'مقبول','rejected'=>'مرفوض','overdue'=>'متأخر','canceled'=>'ملغي','completed'=>'مكتمل'][$workItem->status] ?? $workItem->status }}</td><td>{{ $workItem->vendor_response_due_at?->format('Y-m-d H:i') ?? '—' }}</td><td>{{ $workItem->customer_total === null ? '—' : webCurrencyConverter($workItem->customer_total) }} / {{ $workItem->vendor_net_amount === null ? '—' : webCurrencyConverter($workItem->vendor_net_amount) }}</td></tr>
                @endforeach
            </tbody></table></div></div>
            @endif
            @if(\Illuminate\Support\Facades\Schema::hasTable('order_reassignment_transfers'))
            @php($transfers = \Illuminate\Support\Facades\DB::table('order_reassignment_transfers')->where('source_order_id', $order->id)->orWhere('target_order_id', $order->id)->orderByDesc('id')->get())
            @if($transfers->isNotEmpty())
            <div class="card mb-3"><div class="card-header"><h4 class="mb-0">سجل نقل المنتجات والمبالغ</h4></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>التوقيت</th><th>المنتج</th><th>من طلب</th><th>إلى طلب</th><th>مبلغ العميل</th><th>الشحن</th><th>السبب</th></tr></thead><tbody>
                @foreach($transfers as $transfer)
                <tr><td>{{ $transfer->created_at }}</td><td>#{{ $transfer->order_detail_id }}</td><td><a href="{{ route('admin.orders.admin-gate.show', $transfer->source_order_id) }}">#{{ $transfer->source_order_id }}</a></td><td><a href="{{ route('admin.orders.admin-gate.show', $transfer->target_order_id) }}">#{{ $transfer->target_order_id }}</a></td><td>{{ webCurrencyConverter($transfer->customer_amount_transferred) }}</td><td>{{ webCurrencyConverter($transfer->shipping_amount_transferred) }}</td><td>{{ $transfer->reason }}</td></tr>
                @endforeach
            </tbody></table></div></div>
            @endif
            @endif
            @include('admin-views.order.partials._approval-form')
            @if(\Illuminate\Support\Facades\Schema::hasTable('order_work_item_events'))
            @php($lineEvents = \App\Models\OrderWorkItemEvent::query()->whereIn('work_item_id', $order->workItems()->pluck('id'))->orderByDesc('id')->limit(100)->get())
            <div class="card mb-3"><div class="card-header"><h4 class="mb-0">السجل الزمني لحصص الطلب</h4></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>التوقيت</th><th>المنتج</th><th>الحدث</th><th>من</th><th>إلى</th><th>المنفذ</th></tr></thead><tbody>
                @forelse($lineEvents as $lineEvent)
                <tr><td>{{ $lineEvent->created_at?->format('Y-m-d H:i') }}</td><td>#{{ $lineEvent->order_detail_id }}</td><td>{{ $lineEvent->event_key }}</td><td>{{ $lineEvent->status_before ?? '—' }}</td><td>{{ $lineEvent->status_after ?? '—' }}</td><td>{{ $lineEvent->actor_type }} {{ $lineEvent->actor_id ? '#'.$lineEvent->actor_id : '' }}</td></tr>
                @empty<tr><td colspan="6" class="text-center">لا توجد حركات مسجلة.</td></tr>@endforelse
            </tbody></table></div></div>
            @endif

            <div class="card mb-3"><div class="card-header"><h4 class="mb-0">{{ translate('decision_history') }}</h4></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ translate('date') }}</th><th>{{ translate('action') }}</th><th>{{ translate('admin') }}</th><th>{{ translate('status') }}</th><th>{{ translate('note') }}</th></tr></thead><tbody>@forelse($order->adminGateDecisions as $decision)<tr><td>{{ $decision->created_at->format('Y-m-d H:i') }}</td><td>{{ translate($decision->action) }}</td><td>{{ $decision->admin?->name??'—' }}</td><td>{{ translate($decision->status_after) }}</td><td>{{ $decision->note }}</td></tr>@empty<tr><td colspan="5" class="text-center">{{ translate('no_data_found') }}</td></tr>@endforelse</tbody></table></div></div>
        </div>
        <div class="col-xl-4">


            @if(in_array($sellerResponseStatus, ['pending','overdue'], true))
            <form class="card border-warning mb-3" method="post" action="{{ route('admin.orders.admin-gate.seller-follow-up',$order->id) }}">@csrf
                <div class="card-header"><h4 class="mb-0">{{ translate('contact_seller_about_shipping') }}</h4></div>
                <div class="card-body"><p class="text-muted mb-2">{{ translate($sellerResponseStatus === 'overdue' ? 'seller_has_not_started_preparing_overdue' : 'seller_has_not_started_preparing') }}</p><textarea class="form-control mb-3" name="message" rows="3" required placeholder="{{ translate('message') }}"></textarea><button class="btn btn-warning w-100">{{ translate('send_message') }}</button></div>
            </form>
            @endif

            @if(!$openInvestigation)
            <form class="card border-danger mb-3" method="post" action="{{ route('admin.orders.admin-gate.investigation.open',$order->id) }}">@csrf<div class="card-header"><h4 class="text-danger mb-0">{{ translate('open_investigation') }}</h4></div><div class="card-body"><label class="form-label">{{ translate('risk_level') }}</label><select class="form-control mb-2" name="risk_level">@foreach(['low','medium','high','critical'] as $level)<option value="{{ $level }}">{{ translate($level) }}</option>@endforeach</select><label class="form-label">{{ translate('reason') }}</label><textarea class="form-control mb-3" name="reason" required></textarea><button class="btn btn-outline-danger w-100">{{ translate('freeze_and_open_investigation') }}</button></div></form>
            @else
            <div class="card border-danger mb-3"><div class="card-header"><h4 class="text-danger mb-0">{{ translate('investigation_center') }}</h4></div><div class="card-body">
                <p><strong>{{ translate('reason') }}:</strong> {{ $openInvestigation->reason }}</p>
                <form method="post" action="{{ route('admin.orders.admin-gate.investigation.message',$openInvestigation->id) }}" class="mb-3">@csrf<label class="form-label">{{ translate('recipient') }}</label><select class="form-control mb-2" name="recipient_type"><option value="customer">{{ translate('customer') }}</option><option value="seller">{{ translate('vendor') }}</option></select><textarea class="form-control mb-2" name="message" required placeholder="{{ translate('message') }}"></textarea><button class="btn btn-outline-primary w-100">{{ translate('send_message') }}</button></form>
                <form method="post" action="{{ route('admin.orders.admin-gate.investigation.resolve',$openInvestigation->id) }}">@csrf<label class="form-label">{{ translate('final_decision') }}</label><select class="form-control mb-2" name="action"><option value="resume">{{ translate('resume_order') }}</option><option value="cancel_and_refund">{{ translate('cancel_and_refund_purchase') }}</option></select><textarea class="form-control mb-2" name="note" required placeholder="{{ translate('mandatory_decision_reason') }}"></textarea><button class="btn btn-danger w-100">{{ translate('save_final_decision') }}</button></form>
            </div></div>
            @endif
        </div>
    </div>
</div>
@endsection
