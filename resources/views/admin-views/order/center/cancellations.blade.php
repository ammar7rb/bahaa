@extends('layouts.admin.app')
@section('title', 'معالجة الإلغاء والأرصدة')
@section('content')
<div class="content container-fluid" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4"><h1 class="page-header-title">معالجة الإلغاء والأرصدة</h1><div><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.orders.cancellations.seller-debts') }}">مديونيات التجار</a> <a href="{{ route('admin.orders.center', ['queue'=>'canceled']) }}">مركز الطلبات</a></div></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="get" class="card card-body mb-3"><div class="d-flex gap-2"><input name="q" value="{{ request('q') }}" class="form-control" maxlength="100" placeholder="رقم الطلب"><button class="btn btn-primary">بحث</button></div></form>
    @forelse($items as $item)
        @php($order = $item->order)
        @php($customerCase = $settlements['customer:'.$item->order_id] ?? null)
        @php($sellerCase = $settlements['seller:'.$item->id] ?? null)
        @php($vendorCancel = $vendorCancellations[$item->order_detail_id] ?? null)
        @php($cancelEvent = ($cancellationEvents[$item->order_id] ?? collect())->first())
        <div class="card mb-3"><div class="card-body"><div class="d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h5"><a href="{{ route('admin.orders.details', $item->order_id) }}">طلب #{{ $item->order_id }}</a> · منتج #{{ $item->product_id }}</h2><p class="text-muted mb-2">العميل: {{ trim(($order?->customer?->f_name ?? '').' '.($order?->customer?->l_name ?? '')) }} · التاجر: {{ $item->seller?->shop?->name ?? '#'.$item->seller_id }}</p><p class="small mb-2">طرف الإلغاء: {{ $vendorCancel ? 'التاجر' : match($cancelEvent?->actor_type) { 'customer' => 'العميل', 'seller' => 'التاجر', 'admin' => 'الإدارة', default => 'غير محدد' } }} · السبب: {{ $cancelEvent?->note ?: '—' }}</p></div><div>تاريخ الإلغاء: {{ $vendorCancel?->canceled_at ?? $cancelEvent?->created_at?->format('Y-m-d H:i') ?? $item->updated_at?->format('Y-m-d H:i') }}</div></div>
            <div class="row g-3"><div class="col-lg-6"><h3 class="h6">إلغاء العميل ورد الرصيد</h3>
                @if($customerCase)<div class="alert alert-success">تم رد {{ webCurrencyConverter($customerCase->refund_amount) }} · الرسوم {{ webCurrencyConverter($customerCase->fee_amount) }} · الخصم {{ webCurrencyConverter($customerCase->discount_amount) }} · متاح للسحب بدءًا من {{ $customerCase->credit_expires_at }}</div>
                @elseif($order?->payment_status === 'paid' && !$order?->is_guest && $order?->order_status === 'canceled' && $cancelEvent?->actor_type === 'customer' && (int) $cancelEvent->actor_id === (int) $order?->customer_id)
                    <p class="small text-muted">القيمة المقترحة: {{ webCurrencyConverter((float) $order->paid_amount > 0 ? min((float) ($order->init_order_amount ?: $order->order_amount), (float) $order->paid_amount) : ($order->init_order_amount ?: $order->order_amount)) }}</p>
                    <form method="post" action="{{ route('admin.orders.cancellations.refund', $item->order_id) }}" class="row g-2">@csrf
                        <div class="col-sm-4"><label class="form-label">مبلغ الرد</label><input name="refund_amount" type="number" min="0.001" step="0.001" class="form-control" required></div>
                        <div class="col-sm-4"><label class="form-label">الرسوم</label><input name="fee_amount" type="number" min="0" step="0.001" value="0" class="form-control" required></div>
                        <div class="col-sm-4"><label class="form-label">الخصومات</label><input name="discount_amount" type="number" min="0" step="0.001" value="0" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label">تاريخ إتاحة الرصيد للسحب</label><input name="credit_expires_on" type="date" min="{{ now()->addDay()->toDateString() }}" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label">سبب الإلغاء</label><input name="reason" maxlength="2000" class="form-control" required></div>
                        <div class="col-12"><button class="btn btn-primary btn-sm">تسجيل الرد</button></div>
                    </form>
                @else<p class="text-muted">لا تتوفر شروط رد الرصيد لهذه الحصة.</p>@endif
            </div><div class="col-lg-6"><h3 class="h6">إلغاء التاجر ورسومه</h3>
                @if($sellerCase)<div class="alert alert-info">الرسوم {{ webCurrencyConverter($sellerCase->fee_amount) }} · خصم من المتاح {{ webCurrencyConverter($sellerCase->seller_debited_amount) }} · مديونية {{ webCurrencyConverter($sellerCase->seller_debt_amount) }}</div>
                @elseif($vendorCancel || ($cancelEvent?->actor_type === 'seller' && (int) $cancelEvent->actor_id === (int) $item->seller_id))<form method="post" action="{{ route('admin.orders.cancellations.seller-fee', $item->id) }}" class="row g-2">@csrf<div class="col-sm-5"><label class="form-label">رسوم الإلغاء</label><input name="fee_amount" type="number" min="0" step="0.001" value="{{ $vendorCancel?->fee_amount ?? 0 }}" class="form-control"  required></div><div class="col-sm-7"><label class="form-label">السبب</label><input name="reason" maxlength="2000" class="form-control" required></div><div class="col-12"><button class="btn btn-outline-primary btn-sm">{{ $vendorCancel?->fee_amount !== null ? 'اعتماد الغرامة المسجلة والانتقال لإعادة التعيين' : 'خصم الرسوم وتسجيل المديونية' }}</button></div></form>@else<p class="text-muted">لا يوجد إلغاء موثق من التاجر لهذه الحصة.</p>@endif
                @if($sellerCase)<div class="mt-2"><a class="btn btn-outline-warning btn-sm" href="{{ route('admin.orders.reassignment.show', $item->id) }}">تعيين المنتج لتاجر بديل</a></div>@endif
            </div></div>
            @if($item->events->isNotEmpty())<div class="border-top mt-3 pt-3"><h3 class="h6">السجل الزمني</h3><ol class="small mb-0">@foreach($item->events as $event)<li>{{ $event->created_at?->format('Y-m-d H:i') }} · {{ $event->event_key }} · {{ $event->actor_type }}</li>@endforeach</ol></div>@endif
        </div></div>
    @empty<div class="card card-body text-center p-5">لا توجد طلبات ملغاة.</div>@endforelse
    {{ $items->links() }}
</div>
@endsection
