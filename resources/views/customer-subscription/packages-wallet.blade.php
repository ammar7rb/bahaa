@extends('layouts.front-end.app')
@section('title','الباقات الشهرية')
@push('css_or_js')
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-subscription.css') }}">
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-packages.css') }}?v=20261014b">
@endpush
@section('content')
@php
    $money = fn($value) => rtrim(rtrim(number_format((float)$value,3,'.',','),'0'),'.');
@endphp
<main class="monthly-page package-page" dir="rtl">
    <a class="monthly-back" href="{{ route('user-profile') }}">العودة إلى الملف الشخصي</a>
    <header class="package-heading"><h1>الباقات الشهرية</h1><p>تُخصم معاملات الباقات من محفظة المنصة وتُفعّل فورًا. الحد الائتماني لا يُضاف إلى رصيد المحفظة.</p></header>
    @if($errors->any() && str_contains($errors->first(), 'رصيد محفظة المنصة غير كاف'))
        <dialog open class="package-funds-dialog" aria-labelledby="package-funds-title"><div><h2 id="package-funds-title">برجاء شحن رصيدكم لإتمام العملية</h2><a class="monthly-button" href="{{ route('wallet') }}#purchase-balance">شحن المحفظة</a><button type="button" onclick="this.closest('dialog').close()">إغلاق</button></div></dialog>
        <style>.package-funds-dialog{position:fixed;inset:0;z-index:9999;width:100%;height:100%;max-width:none;max-height:none;border:0;margin:0;background:rgba(15,30,54,.45);backdrop-filter:blur(5px);display:grid;place-items:center}.package-funds-dialog:not([open]){display:none}.package-funds-dialog div{background:white;border-radius:18px;padding:32px;text-align:center;max-width:440px}.package-funds-dialog h2{font-size:20px;margin-bottom:22px}.package-funds-dialog button{border:0;background:transparent;padding:12px;color:#1a428a}</style>
    @elseif($errors->any())<div class="monthly-error" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('subscription_message'))<div class="monthly-notice" role="status">{{ session('subscription_message') }}</div>@endif
    <section class="package-wallet-bar" aria-label="رصيد محفظتك">
        <div class="package-wallet-balance"><span>رصيد محفظتك</span><strong><bdi>{{ $money($purchase_balance) }}</bdi> <small>ج.م</small></strong></div>
        <a class="package-wallet-topup" href="{{ route('wallet') }}#purchase-balance">شحن محفظة المنصة</a>
    </section>
    @unless($purchase_wallet_enabled)<p class="monthly-error">محفظة المنصة غير متاحة حاليًا.</p>@endunless
    @if($subscription['active'])
    @php
        $currentPackage = $packages->firstWhere('id', $subscription['package_id']);
    @endphp
    <section class="package-account" aria-label="باقتك الحالية">
        <div class="package-account-title"><h2>{{ $subscription['package_name'] }}</h2>
            @if($currentPackage)<form method="post" action="{{ route('customer.subscription.transact') }}" data-package-transaction>@csrf
                @include('customer-subscription.transaction-fields', ['operation'=>'purchase','quoteToken'=>$currentPackage->quote_token,'quoteAmount'=>$currentPackage->package_price])
                <input type="hidden" name="package_id" value="{{ $currentPackage->id }}"><button type="submit" class="monthly-button" @disabled(!$purchase_wallet_enabled)>تجديد</button>
            </form>@endif
            <span class="package-paid">مدفوع</span><p>بدأت {{ \Carbon\Carbon::parse($subscription['starts_at'])->format('Y/m/d') }} · تنتهي {{ \Carbon\Carbon::parse($subscription['expires_at'])->format('Y/m/d') }} · متبقي {{ $subscription['remaining_days'] }} يومًا</p></div>
        <dl class="package-account-facts">@foreach(['base_limit'=>'حد الباقة الأساسي','committed_limit'=>'المستخدم أو المحجوز خلال الاشتراك','available_limit'=>'المتاح الآن'] as $key=>$label)<div><dt>{{ $label }}</dt><dd><bdi>{{ $money($subscription['allowance'][$key]) }}</bdi> <small>ج.م</small></dd>
            @if($key === 'available_limit' && $currentPackage && $currentPackage->renewal_price > 0)<form method="post" action="{{ route('customer.subscription.transact') }}" data-package-transaction>@csrf
                @include('customer-subscription.transaction-fields', ['operation'=>'renew_limit','quoteToken'=>$currentPackage->quote_token,'quoteAmount'=>$currentPackage->renewal_price])
                <input type="hidden" name="package_id" value="{{ $currentPackage->id }}"><button type="submit" class="monthly-button" @disabled(!$purchase_wallet_enabled)>تجديد</button>
            </form>@endif
        </div>@endforeach</dl>
    </section>
    @endif
    @if($subscription['active'] && !$auto_renew_enabled)
        <form class="monthly-notice" method="post" action="{{ route('customer.subscription.auto-renew') }}">
            @csrf
            <label><input type="checkbox" name="auto_renew" value="1" required onchange="if(this.checked &amp;&amp; this.form.requestSubmit) this.form.requestSubmit()"> تجديد تلقائي من رصيد المحفظة عند انتهاء الباقة</label>
            <button type="submit" class="monthly-button">تفعيل التجديد التلقائي</button>
        </form>
    @endif
    @if($subscription['legacy_open_payment'] ?? null)<p class="monthly-notice">توجد عملية سابقة محفوظة في السجل. لا تُخصم تلقائيًا؛ تابع حالتها مع الإدارة.</p>@endif
    @if($subscription['active'])<p class="monthly-notice">يبدأ التجديد أو تغيير الباقة فترة جديدة مدتها 30 يومًا فورًا.</p>@endif
    <section id="monthly-plans" class="package-plans" aria-labelledby="plans-title"><h2 id="plans-title">{{ $subscription['active'] ? 'باقتك والترقية' : 'اختر باقتك' }}</h2><div class="package-grid">
    @forelse($packages as $package)
        @php($current=$subscription['active'] && $subscription['package_id']===$package->id)
        @php($higher = $subscription['active'] && $package->purchase_limit > $subscription['allowance']['base_limit'])
        <article class="package-choice {{ $current ? 'package-active' : '' }}">
            <div class="package-card-top"><h3>{{ $current ? $subscription['package_name'] : $package->name }}</h3>@if($current)<span class="package-badge paid">الباقة الحالية</span>@endif</div>
            <p class="package-description">{{ $package->description }}</p><div class="package-price"><bdi>{{ $money($current ? $subscription['allowance']['package_price'] : $package->package_price) }}</bdi><span>ج.م</span></div><p class="package-price-label">سعر الباقة · الاشتراك الجديد 30 يومًا</p>
            <div class="package-credit"><span>الحد الائتماني</span><strong><bdi>{{ $money($current ? $subscription['allowance']['base_limit'] : $package->purchase_limit) }}</bdi> ج.م</strong></div>
            @if($current)
                <form id="renew-current-package" method="post" action="{{ route('customer.subscription.transact') }}" data-package-transaction>
                    @csrf
                    @include('customer-subscription.transaction-fields', ['operation' => 'purchase', 'quoteToken' => $package->quote_token, 'quoteAmount' => $package->package_price])
                    <input type="hidden" name="package_id" value="{{ $package->id }}">
                    <button class="monthly-button" type="submit" @disabled(!$purchase_wallet_enabled)>تجديد الباقة بـ{{ $money($package->package_price) }} ج.م</button>
                </form>
                <p>تجديد الحد فقط؛ يبقى تاريخ انتهاء الباقة {{ \Carbon\Carbon::parse($subscription['expires_at'])->format('Y/m/d') }}.</p>
                @if($package->renewal_price>0)<form id="renew-current-limit" method="post" action="{{ route('customer.subscription.transact') }}" data-package-transaction>@csrf
                    @include('customer-subscription.transaction-fields',['operation'=>'renew_limit','quoteToken'=>$package->quote_token,'quoteAmount'=>$package->renewal_price])<input type="hidden" name="package_id" value="{{ $package->id }}"><button class="monthly-button" type="submit" @disabled(!$purchase_wallet_enabled)>تجديد الحد بـ{{ $money($package->renewal_price) }} ج.م</button></form>
                @else<p id="renew-current-limit">لم تحدد الإدارة رسوم تجديد الحد بعد.</p>@endif
            @else
                <form method="post" action="{{ route('customer.subscription.transact') }}" data-package-transaction>@csrf
                    @include('customer-subscription.transaction-fields',['operation'=>'purchase','quoteToken'=>$package->quote_token,'quoteAmount'=>$package->package_price])<input type="hidden" name="package_id" value="{{ $package->id }}"><button class="monthly-button" type="submit" @disabled(!$purchase_wallet_enabled)>{{ $higher ? 'ترقية الباقة' : 'اشترك الآن' }}</button></form>
            @endif
        </article>
    @empty<p>لا توجد باقات متاحة حاليًا.</p>@endforelse
    </div></section>
    @if($subscription['active'])
    <section id="credit-increase" class="package-increase"><h2>زيادة الحد الائتماني</h2><p>تنتهي الزيادة مع باقتك الحالية.</p>
        @if($increase_offers->isNotEmpty())
        @php($firstOffer=$increase_offers->first())
        @php($offerChoices=$increase_offers->map(fn($offer)=>['id'=>$offer->id,'price'=>(float)$offer->price,'allowance'=>(float)$offer->allowance,'quote_token'=>$offer->quote_token])->values())
        <form method="post" action="{{ route('customer.subscription.transact') }}" data-credit-increase data-package-transaction data-offers='@json($offerChoices)'>@csrf
            @include('customer-subscription.transaction-fields',['operation'=>'purchase','quoteToken'=>$firstOffer->quote_token,'quoteAmount'=>$firstOffer->price])
            <label class="package-offer-label" for="increase-offer">عرض الزيادة</label><select id="increase-offer" name="offer_id" class="package-offer-select">@foreach($increase_offers as $offer)<option value="{{ $offer->id }}">{{ $offer->name }}</option>@endforeach</select>
            <fieldset class="package-increase-options"><legend>حدد الزيادة المناسبة</legend>@for($quantity=1;$quantity<=5;$quantity++)<label class="package-increase-option"><input type="radio" name="quantity" value="{{ $quantity }}" @checked($quantity===1)><span><strong data-increase-allowance>{{ $money($firstOffer->allowance*$quantity) }} ج.م</strong><small>دفع <bdi data-increase-price>{{ $money($firstOffer->price*$quantity) }}</bdi> ج.م</small></span></label>@endfor</fieldset>
            <div class="package-increase-footer"><p>المبلغ المطلوب <strong><bdi data-increase-total>{{ $money($firstOffer->price) }}</bdi> ج.م</strong></p><button class="monthly-button" type="submit" @disabled(!$purchase_wallet_enabled)>زيادة الحد من رصيد المحفظة</button></div>
        </form>@else<p>لا توجد عروض زيادة متاحة حاليًا.</p>@endif
    </section>@endif
    <details class="monthly-history package-history"><summary>سجل معاملات الباقات</summary>@forelse($payments as $payment)<div class="monthly-history-row"><span>{{ $payment->terms_name ?: 'عملية سابقة' }} · {{ ['activation'=>'اشتراك','upgrade'=>'ترقية','downgrade'=>'تغيير لباقة أقل','renew_package'=>'تجديد الباقة','renew_limit'=>'تجديد الحد','increase'=>'زيادة الحد'][$payment->purchase_kind] ?? 'عملية سابقة' }}</span><span>{{ $money($payment->quoted_amount) }} {{ $payment->currency_code }}</span><span>{{ ['approved'=>'معتمد','pending_review'=>'طلب سابق قيد المراجعة','draft'=>'مسودة سابقة','rejected'=>'مرفوض','cancelled'=>'ملغى'][$payment->status] ?? $payment->status }}</span></div>@empty<p>لا توجد معاملات بعد.</p>@endforelse{{ $payments->links() }}</details>
</main>
@endsection
@push('script')<script src="{{ asset('assets/front-end/js/customer-packages.js') }}?v=20261014" defer></script>@endpush
