@extends('layouts.vendor.app')
@section('title','تخصيص المساحة وعرض تفاصيل الطلب')
@section('content')
<div class="content container-fluid" dir="rtl"><section class="card card-body" style="max-width:700px;margin:auto"><h1 class="h3">تخصيص المساحة وعرض التفاصيل</h1>
@if($quote['already_unlocked'] ?? false)<p>التفاصيل متاحة بالفعل. ارجع إلى الطلب لمراجعته.</p>@else
<p>{{ $quote['message'] }}</p><dl><dt>المساحات الإضافية المطلوبة</dt><dd>{{ $quote['extra_count'] }}</dd><dt>سعر المساحة</dt><dd>{{ number_format((float)$quote['unit_price'],2) }} ج.م</dd><dt>إجمالي الرسوم</dt><dd>{{ number_format((float)$quote['total'],2) }} ج.م</dd><dt>خصم من رصيد المدفوعات</dt><dd>{{ number_format((float)$quote['wallet_debit'],2) }} ج.م</dd><dt>يسجل دينًا</dt><dd>{{ number_format((float)$quote['debt'],2) }} ج.م</dd><dt>تنتهي المساحة مع الاشتراك في</dt><dd>{{ \Carbon\Carbon::parse($quote['expires_at'])->format('Y-m-d H:i') }}</dd></dl>
<p>يسدد الدين من الإيداع المؤكد التالي. تأكيد التخصيص يتيح التفاصيل للمراجعة ولا يعد قبولًا للطلب؛ تستطيع الرفض قبل القبول دون غرامة.</p>
<form method="post" action="{{ route('vendor.fulfillment.unlock',$id) }}">@csrf<input type="hidden" name="token" value="{{ $quote['token'] }}"><button class="btn btn-primary">تأكيد التخصيص وعرض التفاصيل</button><a class="btn btn-outline-secondary" href="{{ url()->previous() }}">رجوع دون تنفيذ</a></form>
@endif</section></div>
@endsection
