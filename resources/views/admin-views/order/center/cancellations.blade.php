@extends('layouts.admin.app')
@section('title', 'معالجة الإلغاء والأرصدة')
@section('content')
<div class="content container-fluid" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4"><h1 class="page-header-title">معالجة الإلغاء والأرصدة</h1><a href="{{ route('admin.orders.center', ['queue'=>'canceled']) }}">مركز الطلبات</a></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="get" class="card card-body mb-3"><div class="d-flex gap-2"><input name="q" value="{{ request('q') }}" class="form-control" maxlength="100" placeholder="رقم الطلب"><button class="btn btn-primary">بحث</button></div></form>
    @forelse($items as $item)
        @php($order = $item->order)
        @php($customerCase = $settlements['customer:'.$item->order_id] ?? null)
        @php($sellerCase = $settlements['seller:'.$item->id] ?? null)
        <div class="card mb-3"><div class="card-body"><div class="d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h5"><a href="{{ route('admin.orders.details', $item->order_id) }}">طلب #{{ $item->order_id }}</a> · منتج #{{ $item->product_id }}</h2><p class="text-muted mb-2">العميل: {{ trim(($order?->customer?->f_name ?? '').' '.($order?->customer?->l_name ?? '')) }} · التاجر: {{ $item->seller?->shop?->name ?? '#'.$item->seller_id }}</p></div><div>تاريخ الإلغاء: {{ $item->updated_at?->format('Y-m-d H:i') }}</div></div>
            <div class="row g-3"><div class="col-lg-6"><h3 class="h6">إلغاء العميل ورد الرصيد</h3>
                @if($customerCase)<div class="alert alert-success">تم رد {{ webCurrencyConverter($customerCase->refund_amount) }} · الرسوم {{ webCurrencyConverter($customerCase->fee_amount) }} · الخصم {{ webCurrencyConverter($customerCase->discount_amount) }} · صلاحية حتى {{ $customerCase->credit_expires_at }}</div>
                @elseif($order?->payment_status === 'paid' && !$order?->is_guest && $order?->order_status === 'canceled' && ($sellerCancellationOrders[$item->order_id] ?? collect())->isEmpty())
                    <p class="small text-muted">القيمة المقترحة: {{ webCurrencyConverter($order->init_order_amount ?: $order->order_amount) }}</p>
                    <form method="post" action="{{ route('admin.orders.cancellations.refund', $item->order_id) }}" class="row g-2">@csrf
                        <div class="col-sm-4"><label class="form-label">مبلغ الرد</label><input name="refund_amount" type="number" min="0.001" step="0.001" class="form-control" required></div>
                        <div class="col-sm-4"><label class="form-label">الرسوم</label><input name="fee_amount" type="number" min="0" step="0.001" value="0" class="form-control" required></div>
                        <div class="col-sm-4"><label class="form-label">الخصومات</label><input name="discount_amount" type="number" min="0" step="0.001" value="0" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label">انتهاء صلاحية الرصيد</label><input name="credit_expires_on" type="date" min="{{ now()->addDay()->toDateString() }}" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label">سبب الإلغاء</label><input name="reason" maxlength="2000" class="form-control" required></div>
                        <div class="col-12"><button class="btn btn-primary btn-sm">تسجيل الرد</button></div>
                    </form>
                @else<p class="text-muted">لا تتوفر شروط رد الرصيد لهذه الحصة.</p>@endif
            </div><div class="col-lg-6"><h3 class="h6">إلغاء التاجر ورسومه</h3>
                @if($sellerCase)<div class="alert alert-info">الرسوم {{ webCurrencyConverter($sellerCase->fee_amount) }} · خصم من المتاح {{ webCurrencyConverter($sellerCase->seller_debited_amount) }} · مديونية {{ webCurrencyConverter($sellerCase->seller_debt_amount) }}</div>
                @elseif(($sellerCancellationOrders[$item->order_id] ?? collect())->contains(fn($event) => (int) $event->actor_id === (int) $item->seller_id))<form method="post" action="{{ route('admin.orders.cancellations.seller-fee', $item->id) }}" class="row g-2">@csrf<div class="col-sm-5"><label class="form-label">رسوم الإلغاء</label><input name="fee_amount" type="number" min="0" step="0.001" value="0" class="form-control" required></div><div class="col-sm-7"><label class="form-label">السبب</label><input name="reason" maxlength="2000" class="form-control" required></div><div class="col-12"><button class="btn btn-outline-primary btn-sm">خصم الرسوم وتسجيل المديونية</button></div></form>@else<p class="text-muted">لا يوجد إلغاء موثق من التاجر لهذه الحصة.</p>@endif
                <div class="mt-2"><a href="{{ route('admin.orders.details', $item->order_id) }}">فتح الطلب لإعادة تعيين المنتج</a></div>
            </div></div>
            @if($item->events->isNotEmpty())<div class="border-top mt-3 pt-3"><h3 class="h6">السجل الزمني</h3><ol class="small mb-0">@foreach($item->events as $event)<li>{{ $event->created_at?->format('Y-m-d H:i') }} · {{ $event->event_key }} · {{ $event->actor_type }}</li>@endforeach</ol></div>@endif
        </div></div>
    @empty<div class="card card-body text-center p-5">لا توجد طلبات ملغاة.</div>@endforelse
    {{ $items->links() }}
</div>
@endsection
