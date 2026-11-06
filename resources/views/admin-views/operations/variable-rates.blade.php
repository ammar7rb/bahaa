@extends('layouts.admin.app')
@section('title','النسب والمبالغ المتغيرة')
@section('content')
<div class="content container-fluid" dir="rtl"><h1 class="h3 mb-4">العمليات / النسب والمبالغ المتغيرة</h1>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form class="card card-body mb-4" method="post" action="{{ route('admin.operations.variable-rates.update') }}">@csrf
<h2 class="h4">غرامة إلغاء التاجر بعد القبول الصريح</h2>
<p>اختر طريقة واحدة فقط: مبلغ ثابت بالجنيه أو نسبة من مستحق التاجر لبضائع الجزء الملغى، دون الشحن والضرائب وهامش الشركة. لا تجمع الطريقتان.</p>
<p>تطبق عند إلغاء التاجر بعد قبوله. الرفض قبل القبول مجاني، وإلغاء الإدارة أو العميل لا يفرض غرامة تلقائية. تحفظ نسخة القاعدة مع كل تقييم؛ التعديلات التالية لا تغير التاريخ.</p>
<label class="mb-3"><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$penalty['enabled']))> تفعيل الغرامة</label>
<div class="row"><div class="col-md-4"><label class="form-label" for="penalty-mode">طريقة الحساب</label><select class="form-control mb-3" name="mode" id="penalty-mode"><option value="fixed" @selected(old('mode',$penalty['mode'])==='fixed')>مبلغ ثابت</option><option value="percentage" @selected(old('mode',$penalty['mode'])==='percentage')>نسبة مئوية</option></select></div>
<div class="col-md-4"><label class="form-label" for="penalty-fixed">المبلغ الثابت / ج.م</label><input class="form-control mb-3" id="penalty-fixed" name="fixed_amount" type="number" min="0" max="999999999.99" step="0.01" value="{{ old('fixed_amount',$penalty['fixed_amount']) }}"></div>
<div class="col-md-4"><label class="form-label" for="penalty-percentage">النسبة / %</label><input class="form-control mb-3" id="penalty-percentage" name="percentage" type="number" min="0" max="100" step="0.0001" value="{{ old('percentage',$penalty['percentage']) }}"></div></div>
<p class="text-muted">الخصم من رصيد المدفوعات فقط؛ يسجل العجز دينًا ويسدد من الإيداع المؤكد التالي. رقم القاعدة الحالي: {{ $penalty['revision'] }}.</p><button class="btn btn-primary align-self-start">حفظ قاعدة الغرامة</button></form>
<form class="card card-body" method="post" action="{{ route('admin.operations.variable-rates.extra-slots') }}">@csrf<h2 class="h4">المساحات الإعلانية الإضافية</h2><p>المدة حتى انتهاء الاشتراك. عند امتلاء المساحات، يعرض للتاجر السعر والخصم والدين قبل تأكيد التخصيص. لا استبدال صامت ولا مساحة مجانية.</p>
<label class="mb-3"><input type="checkbox" name="enabled" value="1" @checked($extra['enabled'])> تفعيل المساحات الإضافية</label><div class="row"><div class="col-md-6"><label>سعر المساحة بالجنيه<input class="form-control my-2" type="number" name="unit_price" min="0.01" max="999999999.99" step="0.01" value="{{ $extra['unit_price'] }}" required></label></div><div class="col-md-6"><label>الحد الأقصى للمساحات الإضافية في الدورة<input class="form-control my-2" type="number" name="max_slots" min="1" max="10000" value="{{ $extra['max_slots'] }}" required></label></div></div><button class="btn btn-primary align-self-start">حفظ سعر المساحة وحدّها</button></form>
</div>
@endsection
