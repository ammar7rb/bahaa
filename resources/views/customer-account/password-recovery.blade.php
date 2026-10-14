<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>استعادة كلمة المرور — سيجما</title><link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-checkout-registration.css') }}"></head>
<body><main><section class="registration-card"><h1>استعادة كلمة المرور</h1><p>أدخل الموبايل أو البريد المسجل. ستراجع الإدارة طلبك وتتحقق من صاحب الحساب قبل إرسال كلمة مرور مؤقتة.</p>
@if(session('recovery_message'))<p role="status">{{ session('recovery_message') }}</p>@endif
@if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('customer.password-recovery.request') }}">@csrf<label for="recovery-identity">الموبايل أو البريد الإلكتروني المسجل</label><input id="recovery-identity" name="identity" required maxlength="191" autocomplete="username" value="{{ old('identity') }}"><button type="submit">إرسال طلب للإدارة</button></form>
<a href="{{ route('customer.auth.login') }}">العودة لتسجيل الدخول</a></section></main></body></html>
