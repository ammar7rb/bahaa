@extends('layouts.admin.app')
@section('title', __('operations.inquiries'))
@section('content')
<div class="content container-fluid operations-workspace">@include('admin-views.operations._feedback')
<div class="operations-toolbar"><h1 class="h3">{{ __('operations.inquiries') }} #{{ $inquiry->id }}</h1><a href="{{ route('admin.operations.medical-devices.inquiries') }}">{{ __('operations.inquiries') }}</a></div>
<div class="card card-body"><h2 class="h4">{{ $inquiry->device_name }}</h2><p>{{ __('operations.submitted') }}: {{ $inquiry->created_at->format('Y-m-d H:i') }}</p>
<h2 class="h5">{{ __('operations.customer') }}</h2><p>{{ $inquiry->customer?->f_name }} {{ $inquiry->customer?->l_name }} #{{ $inquiry->customer_id }}<br><span dir="ltr">{{ $inquiry->customer?->phone }} · {{ $inquiry->customer?->email }}</span></p>
<div class="d-flex flex-wrap gap-3 mb-4">@if($inquiry->customer)<a href="{{ route('admin.customer.view', $inquiry->customer_id) }}">{{ __('operations.customer_details') }}</a>@endif
@if($inquiry->device && !$inquiry->device->trashed())<a href="{{ route('admin.operations.medical-devices.edit', $inquiry->device_id) }}">{{ __('operations.device_details') }}</a>@else<span>{{ __('operations.removed_device') }}</span>@endif
@if($inquiry->conversation)<a class="btn btn-primary" href="{{ route('admin.support-ticket.singleTicket', $inquiry->support_ticket_id) }}">{{ __('operations.open_conversation') }}</a>@endif</div>
@if($reportPurchase)
<div class="alert alert-success">تم دفع ثمن التقرير الفني: {{ webCurrencyConverter($reportPurchase->amount) }}. {{ $reportPurchase->purchase_requested_at ? 'قدم العميل طلب شراء الجهاز.' : 'لم يقدم العميل طلب شراء بعد.' }}</div>
@if($reportPurchase->confirmed_at)<form method="post" enctype="multipart/form-data" action="{{ route('admin.operations.medical-devices.report', $inquiry) }}" class="mb-4">@csrf<label>إرسال التقرير الفني إلى محادثة العميل <input type="file" name="report" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="form-control" required></label><button class="btn btn-primary">إرسال التقرير</button></form>@else<a class="btn btn-outline-primary mb-4" href="{{ route('admin.orders.special.inspections') }}">راجع الدفع وأكده أولًا</a>@endif
@endif
<form action="{{ route('admin.operations.medical-devices.status', $inquiry) }}" method="post" data-operations-form>@csrf<label>{{ __('operations.status') }}<select name="status" class="form-control">@foreach(['new','following_up','closed'] as $status)<option value="{{ $status }}" @selected($inquiry->status === $status)>{{ __('operations.'.$status) }}</option>@endforeach</select></label><button type="submit" class="btn btn-primary">{{ __('operations.save') }}</button></form>
</div></div>@endsection
