<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>إنشاء حساب لإتمام الشراء — SIGMA</title>
    <link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-checkout-registration.css') }}">
</head>
<body>
<header class="page-header">
    <a class="brand" href="{{ route('home') }}" aria-label="{{ $web_config['company_name'] ?: 'SIGMA' }} — الرئيسية">
        <img src="{{ getStorageImages(path: $web_config['web_logo'], type: 'logo') }}" width="164" height="52" alt="{{ $web_config['company_name'] ?: 'SIGMA' }}">
    </a>
    <a class="back-link" href="{{ route('shop-cart') }}"><span>العودة</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 7-5 5 5 5"/></svg></a>
</header>
<main>
    <section class="registration-card" aria-labelledby="registration-title">
        <span class="eyebrow">إتمام الشراء</span>
        <h1 id="registration-title">أنشئ حسابك، وأكمل طلبك</h1>
        <p class="intro">بيانات بسيطة للمتابعة. المنتجات التي اخترتها ستبقى في سلتك.</p>
        @if ($errors->any())
            <div class="errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="post" action="{{ route('customer.auth.checkout-register') }}">
            @csrf
            @foreach ($policies as $policy)
                <input type="hidden" name="policy_version_ids[]" value="{{ $policy->id }}">
            @endforeach
            <label for="customer-name">الاسم</label>
            <input id="customer-name" name="name" autocomplete="name" maxlength="100" placeholder="اكتب اسمك" required value="{{ old('name') }}">
            <label for="customer-phone">رقم الموبايل</label>
            <input id="customer-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" required value="{{ old('phone') }}" aria-describedby="phone-hint">
            <small id="phone-hint">قم بإضافة رقمك للمتابعة</small>
            <label for="customer-password">كلمة المرور</label>
            <input id="customer-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required aria-describedby="password-hint">
            <small id="password-hint">8 أحرف على الأقل. استخدمها لتسجيل الدخول لاحقًا.</small>
            <label class="consent"><input type="checkbox" name="terms_accepted" value="1" required><span>أوافق على
            <a href="{{ route('business-page.view', ['slug' => 'terms-and-conditions']) }}">الشروط والأحكام</a>
            <a href="{{ route('business-page.view', ['slug' => 'privacy-policy']) }}">سياسة الخصوصية</a>
            </span></label>
            @foreach ($policies as $policy)
                <details><summary>{{ $policy->title }}</summary><div>{{ strip_tags($policy->content) }}</div></details>
            @endforeach
            <button type="submit">إنشاء الحساب والعودة إلى طلبك</button>
        </form>
        <p class="login">لديك حساب؟ <a href="{{ route('customer.auth.login') }}">سجّل الدخول</a></p>
    </section>
    <aside class="checkout-note">
        <div class="cart-symbol" aria-hidden="true"><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l2.4 12h11.2l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="m11 10 2 2 3-3"/></svg></div>
        <h2>اختياراتك في مكانها</h2>
        <p>سلتك محفوظة أثناء إنشاء الحساب، لتعود إلى طلبك وتكمل الشراء بسهولة.</p>
        <div class="note-detail"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 4 8 9 8 9s8-5 8-9V6z"/><path d="m8 12 3 3 5-6"/></svg><span>يمكنك استكمال باقي بيانات حسابك مع الإدارة عند التفعيل.</span></div>
    </aside>
</main>
<script src="{{ asset('assets/front-end/js/egypt-phone-validation.js') }}" defer></script>
</body></html>
