@extends('layouts.front-end.app')
@section('title', 'الباقات الشهرية')
@push('css_or_js')
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-subscription.css') }}">
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-transfer.css') }}">
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-packages.css') }}?v=20261012">
@endpush
@section('content')
<main class="monthly-page package-page" dir="rtl">
    <a class="monthly-back" href="{{ route('user-profile') }}">العودة إلى الملف الشخصي</a>
    <header class="package-heading"><h1>باقة تناسبك، وحد ائتماني أكبر</h1><p>اختر الباقة المناسبة لاحتياجاتك. الحد الائتماني لا يُضاف إلى رصيد المحفظة، وتُدفع قيمة الطلب كاملة.</p></header>
    @if($errors->any())<div class="monthly-error" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('subscription_message'))<div class="monthly-notice" role="status">{{ session('subscription_message') }}</div>@endif
    @if($subscription['active'])
    <section class="package-account" aria-label="باقتك الحالية">
        <div class="package-account-title"><h2>{{ $subscription['package_name'] }}</h2><span class="package-paid">@include('customer-subscription.icon',['name'=>'check']) مدفوع</span><p>متبقي {{ $subscription['remaining_days'] }} يومًا · تنتهي {{ \Carbon\Carbon::parse($subscription['expires_at'])->format('Y/m/d') }}</p></div>
        <dl class="package-account-facts"><div><dt>الحد الائتماني</dt><dd><bdi>{{ number_format($subscription['allowance']['granted_limit'],2) }}</bdi> <small>ج.م</small></dd></div><div><dt>المستخدم أو المحجوز</dt><dd><bdi>{{ number_format($subscription['allowance']['committed_limit'],2) }}</bdi> <small>ج.م</small></dd></div><div><dt>المتاح الآن</dt><dd><bdi>{{ number_format($subscription['allowance']['available_limit'],2) }}</bdi> <small>ج.م</small></dd></div></dl>
    </section>
    @endif
    @php($open = $subscription['open_payment'])
    @if($open)
    <section class="package-pending"><h2>{{ $open->terms_name ?: 'عملية دفع سابقة' }}</h2><p>المبلغ <bdi>{{ number_format((float)$open->quoted_amount,2) }}</bdi> {{ $open->currency_code }} · الحد الائتماني <bdi>{{ number_format((float)$open->granted_allowance,2) }}</bdi> ج.م</p>
        @if($open->status === 'pending_review')<div class="monthly-notice" role="status">الدفع قيد المراجعة. سيظهر قرار الإدارة هنا.</div>
        @elseif($open->package_id || $open->offer_id)<button class="monthly-button" type="button" data-payment-open="subscription-methods">{{ $open->purchase_kind === 'increase' ? 'دفع زيادة الحد الائتماني' : ($open->purchase_kind === 'upgrade' ? 'دفع الترقية' : 'دفع الباقة') }}</button>@include('customer-subscription.payment-dialogs')
        @endif
        @if($open->status === 'draft')<form method="post" action="{{ route('customer.subscription.cancel',$open->id) }}">@csrf<button class="package-text-button" type="submit">إلغاء المسودة واختيار عرض آخر</button></form>@endif
    </section>
    @endif
    @if($subscription['active'])
    <section class="package-upgrade-guide"><div><h2>طوّر باقتك على راحتك</h2><p>تحتاج حدًا أكبر؟ اختر زيادة تناسبك أو انتقل إلى باقة أعلى.</p></div><div class="package-guide-actions"><a href="#credit-increase" class="package-link-primary">زيادة الحد الائتماني</a><a href="#monthly-plans" class="package-link-secondary">ترقية الباقة</a></div></section>
    @endif
    <section id="monthly-plans" class="package-plans" aria-labelledby="plans-title"><h2 id="plans-title">{{ $subscription['active'] ? 'اختر باقتك للترقية' : 'اختر باقتك' }}</h2>
    <div class="package-grid">
    @forelse($packages as $package)
        @php($isActive = $subscription['active'] && $subscription['package_id'] === $package->id)
        @php($isSelected = $open && $open->package_id === $package->id)
        @php($eligible = !$subscription['active'] || ($package->id !== $subscription['package_id'] && $package->purchase_limit > $subscription['allowance']['base_limit']))
        <article class="package-choice {{ $isActive ? 'package-active' : '' }} {{ $isSelected ? 'package-selected' : '' }}" @if($isActive) aria-label="الباقة الحالية" @endif>
            <div class="package-card-top"><h3 dir="auto">{{ $isActive ? $subscription['package_name'] : ($isSelected ? $open->terms_name : $package->name) }}</h3>@if($isActive)<span class="package-badge paid">مدفوع</span>@elseif($isSelected)<span class="package-badge">مختارة</span>@elseif($subscription['active'] && $eligible)<span class="package-badge">ترقية متاحة</span>@endif</div>
            <p class="package-description">{{ $package->description ?: 'مساحة أكبر لاحتياجاتك، وحد ائتماني واضح.' }}</p>
            <div class="package-price"><bdi>{{ number_format($isActive ? $subscription['allowance']['package_price'] : ($isSelected ? $open->quoted_amount : $package->package_price),2) }}</bdi><span>ج.م</span></div><p class="package-price-label">سعر الباقة</p>
            @if($eligible && !$isActive && !$open)<form method="post" action="{{ route('customer.subscription.checkout') }}">@csrf<input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="package_id" value="{{ $package->id }}"><button class="monthly-button" type="submit">{{ $subscription['active'] ? 'ترقية' : 'اختيار الباقة' }}</button></form>
            @else<div class="package-card-state">{{ $isActive ? 'باقتك الحالية' : ($isSelected ? 'تابع الدفع أعلاه' : ($open ? 'أكمل عملية الدفع الحالية' : 'باقة غير متاحة للترقية')) }}</div>@endif
            <div class="package-credit"><span>@include('customer-subscription.icon',['name'=>'check']) الحد الائتماني</span><strong><bdi>{{ number_format($isActive ? $subscription['allowance']['base_limit'] : ($isSelected ? $open->granted_allowance : $package->purchase_limit),2) }}</bdi> <small>ج.م</small></strong></div>
            <p class="package-credit-note">حد متاح للشراء، وليس رصيدًا في المحفظة.</p>
        </article>
    @empty<div class="package-empty">لا توجد باقات متاحة حاليًا. يمكنك متابعة التصفح.</div>@endforelse
    </div></section>
    @if($subscription['active'])
    <section id="credit-increase" class="package-increase" aria-labelledby="increase-title"><div class="package-section-heading"><div><h2 id="increase-title">زيادة الحد الائتماني</h2><p>اختيارات بسيطة على قدر احتياجك. تنتهي الزيادة مع باقتك الحالية.</p></div></div>
        @if($increase_offers->isNotEmpty())
        @php($firstOffer = $increase_offers->first())
        <form method="post" action="{{ route('customer.subscription.checkout') }}" data-credit-increase data-offers='@json($increase_offers->map(fn($offer)=>["id"=>$offer->id,"price"=>(float)$offer->price,"allowance"=>(float)$offer->allowance])->values())'>@csrf<input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <label class="package-offer-label" for="increase-offer">عرض الزيادة</label><select id="increase-offer" name="offer_id" class="package-offer-select" @disabled($open)>@foreach($increase_offers as $offer)<option value="{{ $offer->id }}">{{ $offer->name }}</option>@endforeach</select>
            <fieldset class="package-increase-options" @disabled($open)><legend>حدد الزيادة المناسبة</legend>@for($quantity=1;$quantity<=5;$quantity++)<label class="package-increase-option"><input type="radio" name="quantity" value="{{ $quantity }}" @checked($quantity===1)><span><strong data-increase-allowance><bdi>{{ number_format($firstOffer->allowance*$quantity,0) }}</bdi> ج.م</strong><small>دفع <bdi data-increase-price>{{ number_format($firstOffer->price*$quantity,2) }}</bdi> ج.م</small></span></label>@endfor</fieldset>
            <div class="package-increase-footer"><p>المبلغ المطلوب <strong><bdi data-increase-total>{{ number_format($firstOffer->price,2) }}</bdi> ج.م</strong></p><button class="monthly-button" type="submit" @disabled($open)>المتابعة إلى الدفع</button></div>
        </form>
        @else<p class="package-empty">لا توجد عروض زيادة متاحة حاليًا.</p>@endif
    </section>
    @endif
    <details class="monthly-history package-history"><summary>سجل المدفوعات</summary>@forelse($payments as $payment)<div class="monthly-history-row"><span>{{ $payment->terms_name ?: 'اشتراك سابق' }}</span><span><bdi>{{ number_format((float)$payment->quoted_amount,2) }}</bdi> {{ $payment->currency_code }}</span><span>{{ ['approved'=>'معتمد','rejected'=>'مرفوض','pending_review'=>'قيد المراجعة','draft'=>'مسودة','cancelled'=>'ملغى'][$payment->status] ?? $payment->status }}</span>@if($payment->review_note)<small>{{ $payment->review_note }}</small>@endif</div>@empty<p>لا توجد مدفوعات بعد.</p>@endforelse{{ $payments->links() }}</details>
</main>
@endsection
@push('script')<script src="{{ asset('assets/front-end/js/customer-packages.js') }}?v=20261012" defer></script>@endpush
