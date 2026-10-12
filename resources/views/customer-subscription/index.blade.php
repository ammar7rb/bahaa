@extends('layouts.front-end.app')
@section('title', 'الاشتراك الشهري')
@push('css_or_js')<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-subscription.css') }}"><link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-transfer.css') }}?v=20261010.4">@endpush

@section('content')
<main class="monthly-page" dir="rtl">
    <a class="monthly-back" href="{{ route('user-profile') }}">@include('customer-subscription.icon',['name'=>'arrow']) العودة إلى الملف الشخصي</a>
    <header class="monthly-heading"><span class="monthly-heading-icon">@include('customer-subscription.icon',['name'=>'calendar'])</span><div><h1>الاشتراك الشهري</h1><p class="monthly-lead">30 يومًا تبدأ من اعتماد الدفع.</p></div></header>
    @if(session('subscription_message'))<div class="monthly-notice" role="status">{{ session('subscription_message') }}</div>@endif
    @if($errors->any())<div class="monthly-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="monthly-layout">
        <section class="monthly-summary" aria-labelledby="monthly-status">
            <div class="monthly-summary-top"><h2 id="monthly-status">حالة اشتراكك</h2><span class="monthly-status {{ $subscription['active'] ? 'active' : '' }}">@include('customer-subscription.icon',['name'=>$subscription['active'] ? 'check' : 'clock']){{ ['active'=>'فعال','expired'=>'منتهي','inactive'=>'غير مشترك'][$subscription['status']] }}</span></div>
            @if($subscription['active'])<p class="monthly-remaining">متبقي <strong>{{ $subscription['remaining_days'] }}</strong> يومًا</p>@endif
            <dl class="monthly-facts"><div><dt>سعر الاشتراك</dt><dd class="monthly-price">@if($subscription['price'] !== null)<bdi>{{ number_format((float)$subscription['price'], 2) }}</bdi><span>{{ $subscription['currency_code'] === 'EGP' ? 'ج.م' : $subscription['currency_code'] }}</span>@else<span>لم يُحدد بعد</span>@endif</dd></div><div><dt>@include('customer-subscription.icon',['name'=>'calendar']) مدة الاشتراك</dt><dd>30 يومًا</dd></div>@if($subscription['expires_at'])<div><dt>@include('customer-subscription.icon',['name'=>'clock']) تاريخ الانتهاء</dt><dd><bdi>{{ \Carbon\Carbon::parse($subscription['expires_at'])->timezone(config('app.timezone'))->format('Y/m/d H:i') }}</bdi></dd></div>@endif</dl>
        </section>
        <section class="monthly-payment" aria-labelledby="monthly-payment-title">
            @php($open = $subscription['open_payment'])
            <span class="monthly-payment-icon">@include('customer-subscription.icon',['name'=>'card'])</span><h2 id="monthly-payment-title">{{ $subscription['status'] !== 'inactive' ? 'جدّد اشتراكك' : 'ابدأ اشتراكك' }}</h2>
            @if($open && $open->status === 'pending_review')
                <div class="monthly-notice" role="status"><strong>الدفع قيد المراجعة</strong><p>تم استلام إثبات الدفع. يبدأ اشتراكك بعد اعتماد الإدارة.</p></div>
                <p>طلب دفع رقم: <bdi>#{{ $open->id }}</bdi></p><p>المبلغ المقدم: {{ number_format((float)$open->quoted_amount,2) }} {{ $open->currency_code }}</p>
            @elseif($open)
                <p class="monthly-quote">المبلغ المطلوب: <strong>{{ number_format((float)$open->quoted_amount,2) }} {{ $open->currency_code }}</strong></p>
                @if($subscription['active'])<div class="monthly-notice">الدفع الآن يبدأ 30 يومًا جديدة عند اعتماده، ولا يضيفها إلى تاريخ الانتهاء الحالي.</div>@endif
                <button class="monthly-button" type="button" data-payment-open="subscription-methods">@include('customer-subscription.icon',['name'=>'card']) اختر طريقة الدفع</button>
                @include('customer-subscription.payment-dialogs')
            @elseif($subscription['payment_available'])
                                <div class="monthly-channels" aria-label="وسائل دفع الاشتراك">
                    @if($purchase_wallet_enabled)<span>@include('customer-subscription.icon',['name'=>'wallet']) محفظة المشتريات</span>@endif
                    @foreach($offline_methods->unique('payment_channel') as $method)<span>@include('customer-subscription.icon',['name'=>$method->payment_channel === 'instapay' ? 'transfer' : 'wallet']) {{ $method->payment_channel === 'instapay' ? 'InstaPay' : 'محفظة إلكترونية' }}</span>@endforeach
                </div>
                <form method="post" action="{{ route('customer.subscription.checkout') }}">@csrf<input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}"><button class="monthly-button" type="submit">@include('customer-subscription.icon',['name'=>'card']) المتابعة إلى الدفع</button></form>
            @else
                <p class="monthly-muted">سيظهر الدفع هنا بعد أن تحدد الإدارة سعر اشتراك العميل.</p>
            @endif
        </section>
    </div>
    <section class="monthly-history"><h2>@include('customer-subscription.icon',['name'=>'history']) سجل دفعات الاشتراك</h2>
        @forelse($payments as $payment)
            <div class="monthly-history-row"><span>#{{ $payment->id }} · {{ $payment->created_at->format('Y/m/d') }}</span><strong>{{ number_format((float)$payment->quoted_amount,2) }} {{ $payment->currency_code }}</strong><span>{{ ['draft'=>'لم يُرسل الدفع بعد','pending_review'=>'الدفع قيد المراجعة','approved'=>'تم اعتماد الدفع','rejected'=>'رُفض الدفع'][$payment->status] }}</span>@if($payment->review_note)<small>{{ $payment->review_note }}</small>@endif</div>
        @empty<p class="monthly-muted">ستظهر دفعات اشتراكك هنا بعد بدء أول عملية دفع.</p>@endforelse
        {{ $payments->links() }}
    </section>
</main>
@endsection
