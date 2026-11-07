<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تغيير كلمة المرور المؤقتة — سيجما</title><link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-checkout-registration.css') }}"></head>
<body><main><section class="registration-card"><h1>اختر كلمة مرور جديدة</h1><p>يجب تغيير الكلمة المؤقتة قبل متابعة استخدام حسابك. بعد الحفظ سجّل الدخول بالكلمة الجديدة.</p>
@if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('customer.password-recovery.change') }}">@csrf
<label for="temporary-password">الكلمة المؤقتة</label><input id="temporary-password" name="current_password" type="password" autocomplete="current-password" required>
<label for="new-password">كلمة المرور الجديدة</label><input id="new-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
<label for="confirm-password">تأكيد كلمة المرور الجديدة</label><input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" required><button type="submit">حفظ كلمة المرور الجديدة</button></form>
<a href="{{ route('customer.password-recovery.form') }}">انتهت صلاحية الكلمة؟ اطلب الاستعادة مجددًا</a></section></main></body></html>
