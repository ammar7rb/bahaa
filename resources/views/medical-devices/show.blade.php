@extends(theme_base_path() === 'default' ? 'layouts.front-end.app' : 'theme-views.layouts.app')
@section('title', $device->name)
@include('medical-devices._styles')
@section('content')
<main class="container medical-device-catalog"><nav class="mb-4"><a href="{{ route('medical-devices.index') }}">{{ __('operations.medical_devices') }}</a></nav>
@if(session('device_inquiry_id'))<div class="alert alert-success" role="status">{{ __('operations.inquiry_success') }} <a href="{{ route('support-ticket.index', session('device_conversation_id')) }}">{{ __('operations.open_conversation') }}</a></div>@endif
@if(session('purchase_message'))<div class="alert alert-success" role="status">{{ session('purchase_message') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="medical-device-detail"><div><img src="{{ $device->image_url }}" class="medical-device-main-image" alt="{{ $device->name }}" width="500" height="500"><div class="medical-device-images">@foreach(array_slice($device->images ?? [], 1) as $image)<a href="{{ Storage::disk('public')->url($image) }}"><img src="{{ Storage::disk('public')->url($image) }}" alt="{{ $device->name }}" loading="lazy"></a>@endforeach</div></div>
<div><h1>{{ $device->name }}</h1><p class="medical-device-copy">{{ $device->description }}</p>@if($device->specifications)<h2 class="h4 mt-4">{{ __('operations.specifications') }}</h2><p class="medical-device-copy">{{ $device->specifications }}</p>@endif
@if($device->price !== null)<p>سعر الجهاز: {{ webCurrencyConverter($device->price) }}</p>@endif
@if($device->device_condition)<p>الحالة: {{ $device->device_condition === 'new' ? 'جديد' : 'مستعمل' }}</p>@endif
@if($device->origin_country)<p>بلد المنشأ: {{ collect(COUNTRIES)->firstWhere('code', $device->origin_country)['name'] ?? $device->origin_country }}</p>@endif
@guest('customer')<p class="mt-4">{{ __('operations.sign_in_hint') }}</p>@endguest
@if($reportPurchase)
    <p class="text-success">تم سداد ثمن التقرير الفني لهذا الجهاز.</p>
    @if($reportPurchase->purchase_requested_at)<p>تم استلام طلبكم وسيتم التواصل معكم في أقرب وقت.</p>
    @elseif($reportPurchase->report_sent_at && !$reportPurchase->report_hidden_at)<form action="{{ route('medical-devices.purchase-request', $device) }}" method="post" class="mt-4">@csrf<button type="submit" class="btn btn-primary">طلب شراء الجهاز</button></form>
    @else<p>{{ $reportPurchase->confirmed_at ? 'جاري إعداد التقرير الفني.' : 'الإدارة تراجع الدفع.' }}</p>@endif
@elseif($device->report_price > 0)
    <p>ثمن التقرير الفني: {{ webCurrencyConverter($device->report_price) }}، ويُدفع من محفظة الموقع. ثمن التقرير غير قابل للاسترجاع.</p>
    <form action="{{ route('medical-devices.inquire', $device) }}" method="post" class="mt-4" id="medical-device-inquiry">@csrf<input type="hidden" name="report_price" value="{{ $device->report_price }}"><button type="submit" class="btn btn-primary" onclick="return confirm('سيتم خصم ثمن التقرير من محفظتك. ثمن التقرير غير قابل للاسترجاع. هل تريد المتابعة؟')">طلب تقرير فني ودفع قيمته</button></form>
@else<p>التقرير الفني غير متاح للطلب حاليًا.</p>@endif
</div></div></main>@endsection
@push('script')<script>const reportForm=document.getElementById('medical-device-inquiry');if(reportForm){reportForm.addEventListener('submit',function(event){const button=this.querySelector('button');if(button.disabled){event.preventDefault();return;}button.disabled=true;button.setAttribute('aria-busy','true');});window.addEventListener('pageshow',function(){const button=reportForm.querySelector('button');button.disabled=false;button.removeAttribute('aria-busy');});}</script>@endpush
