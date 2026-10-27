@extends(in_array(theme_root_path(), ['theme_sigma_care', 'theme_aster'], true) ? 'theme-views.layouts.app' : 'layouts.front-end.app')
@section('title', 'إنشاء حساب لإتمام الشراء | '.$web_config['company_name'])
@section('content')
<main class="main-content py-4" dir="rtl">
    <section class="container" aria-labelledby="checkout-register-title">
        <div class="row justify-content-center"><div class="col-md-8 col-lg-6">
            <a class="btn-link d-inline-flex align-items-center gap-2 mb-3" href="{{ route('shop-cart') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i>العودة إلى السلة</a>
            <div class="card"><div class="card-body p-4">
                <h1 class="h3 mb-2" id="checkout-register-title">أنشئ حسابك، وأكمل طلبك</h1>
                <p class="text-muted mb-4">بيانات بسيطة للمتابعة. المنتجات التي اخترتها محفوظة في سلتك.</p>
                @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <form method="post" action="{{ route('customer.auth.checkout-register') }}" data-checkout-onboarding-form>
                    @csrf
                    @foreach($policies as $policy)<input type="hidden" name="policy_version_ids[]" value="{{ $policy->id }}">@endforeach
                    <div class="form-group mb-4">
                        <label for="customer-name">الاسم</label>
                        <input class="form-control fs-16" id="customer-name" name="name" autocomplete="name" maxlength="100" required value="{{ old('name') }}" @error('name') aria-invalid="true" @enderror>
                    </div>
                    <div class="form-group mb-4">
                        <label for="customer-phone">رقم الموبايل</label>
                        <input class="form-control fs-16" id="customer-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" required value="{{ old('phone') }}" aria-describedby="checkout-phone-hint" @error('phone') aria-invalid="true" @enderror>
                        <small class="text-muted" id="checkout-phone-hint">رقم موبايل مصري يبدأ بـ 010 أو 011 أو 012 أو 015.</small>
                    </div>
                    <div class="form-group mb-4">
                        <label for="customer-password">كلمة المرور</label>
                        <input class="form-control fs-16" id="customer-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required aria-describedby="checkout-password-hint" @error('password') aria-invalid="true" @enderror>
                        <small class="text-muted" id="checkout-password-hint">8 أحرف على الأقل. استخدمها لتسجيل الدخول لاحقًا.</small>
                    </div>
                    <label class="d-flex align-items-start gap-2 mb-3" for="checkout-terms">
                        <input class="mt-1" id="checkout-terms" type="checkbox" name="terms_accepted" value="1" required @checked(old('terms_accepted'))>
                        <span>أوافق على <a href="{{ route('business-page.view', ['slug' => 'terms-and-conditions']) }}" target="_blank" rel="noopener">الشروط والأحكام</a> و<a href="{{ route('business-page.view', ['slug' => 'privacy-policy']) }}" target="_blank" rel="noopener">سياسة الخصوصية</a>.</span>
                    </label>
                    @foreach($policies as $policy)<details class="mb-3"><summary>{{ $policy->title }}</summary><p class="mt-2">{{ strip_tags($policy->content) }}</p></details>@endforeach
                    <button class="btn btn-primary w-100" type="submit" data-loading-label="جارٍ إنشاء الحساب…">إنشاء الحساب واستكمال الطلب</button>
                    <p class="small text-muted mt-2 mb-0" data-checkout-form-status role="status" aria-live="polite"></p>
                </form>
                <p class="mt-4 mb-0">رقمك مسجل بالفعل؟ <a href="{{ route('customer.auth.login') }}">سجّل الدخول لاستكمال طلبك</a></p>
            </div></div>
            <p class="text-muted small mt-3"><i class="bi bi-shield-check" aria-hidden="true"></i> تستكمل تفعيل حسابك بعد مراجعة دفع الطلب، ثم تختار الباقة المناسبة.</p>
        </div></div>
    </section>
</main>
@endsection
@push('script')<script src="{{ asset('assets/front-end/js/checkout-onboarding.js') }}" defer></script>@endpush
