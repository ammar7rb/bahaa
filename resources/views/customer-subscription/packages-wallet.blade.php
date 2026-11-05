@extends(in_array(theme_root_path(), ['theme_aster', 'theme_sigma_care'], true) ? 'theme-views.layouts.app' : 'layouts.front-end.app')
@section('title','الباقات الشهرية')
@push('css_or_js')
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-subscription.css') }}">
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-packages.css') }}?v=20261107">
@endpush
@section('content')
@php
    $money = fn($value) => rtrim(rtrim(number_format((float)$value,3,'.',','),'0'),'.');
@endphp
<main class="container py-4" dir="rtl"><div class="row g-3">
    @includeFirst(['theme-views.partials._profile-aside', 'web-views.partials._profile-aside'])
    <section class="col-lg-9 monthly-page package-page">
    <header class="package-heading"><h1>الباقات الشهرية</h1></header>
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
        <form class="package-auto-renew" method="post" action="{{ route('customer.subscription.auto-renew') }}">
            @csrf
            <label><input type="checkbox" name="auto_renew" value="1" required onchange="if(this.checked) { if(this.form.requestSubmit) this.form.requestSubmit(); else this.form.submit(); }"> تجديد تلقائي من رصيد المحفظة عند انتهاء الباقة</label>
            <noscript><button type="submit" class="monthly-button">تفعيل التجديد التلقائي</button></noscript>
        </form>
    @endif
    @if($subscription['legacy_open_payment'] ?? null)<p class="monthly-notice">توجد عملية سابقة محفوظة في السجل. لا تُخصم تلقائيًا؛ تابع حالتها مع الإدارة.</p>@endif
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
                @if($package->renewal_price>0)<form id="renew-current-limit" method="post" action="{{ route('customer.subscription.transact') }}" data-package-transaction>@csrf
                    @include('customer-subscription.transaction-fields',['operation'=>'renew_limit','quoteToken'=>$package->quote_token,'quoteAmount'=>$package->renewal_price])<input type="hidden" name="package_id" value="{{ $package->id }}"><button class="monthly-button" type="submit" @disabled(!$purchase_wallet_enabled)>تجديد الحد بـ{{ $money($package->renewal_price) }} ج.م</button></form>
                @endif
            @else
                <form method="post" action="{{ route('customer.subscription.transact') }}" data-package-transaction>@csrf
                    @include('customer-subscription.transaction-fields',['operation'=>'purchase','quoteToken'=>$package->quote_token,'quoteAmount'=>$package->package_price])<input type="hidden" name="package_id" value="{{ $package->id }}"><button class="monthly-button" type="submit" @disabled(!$purchase_wallet_enabled)>{{ $higher ? 'ترقية الباقة' : 'اشترك الآن' }}</button></form>
            @endif
        </article>
    @empty<p>لا توجد باقات متاحة حاليًا.</p>@endforelse
    </div></section>
    <details class="monthly-history package-history"><summary>سجل معاملات الباقات</summary>@forelse($payments as $payment)<div class="monthly-history-row"><span>{{ $payment->terms_name ?: 'عملية سابقة' }} · {{ ['activation'=>'اشتراك','upgrade'=>'ترقية','downgrade'=>'تغيير لباقة أقل','renew_package'=>'تجديد الباقة','renew_limit'=>'تجديد الحد','increase'=>'زيادة الحد'][$payment->purchase_kind] ?? 'عملية سابقة' }}</span><span>{{ $money($payment->quoted_amount) }} {{ $payment->currency_code }}</span><span>{{ ['approved'=>'معتمد','pending_review'=>'طلب سابق قيد المراجعة','draft'=>'مسودة سابقة','rejected'=>'مرفوض','cancelled'=>'ملغى'][$payment->status] ?? $payment->status }}</span></div>@empty<p>لا توجد معاملات بعد.</p>@endforelse{{ $payments->links() }}</details>
    </section></div></main>
@endsection
@push('script')<script src="{{ asset('assets/front-end/js/customer-packages.js') }}?v=20261014" defer></script>@endpush
