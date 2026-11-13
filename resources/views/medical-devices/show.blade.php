@extends(theme_base_path() === 'default' ? 'layouts.front-end.app' : 'theme-views.layouts.app')
@section('title', $device->name)
@include('medical-devices._styles')
@section('content')
<main class="container medical-device-catalog">
<nav class="medical-device-crumbs" aria-label="breadcrumb"><a href="{{ route('medical-devices-accessories') }}">{{ __('operations.medical_devices') }}</a><span aria-hidden="true">/</span><span>{{ $device->name }}</span></nav>
@if(session('device_inquiry_id'))<div class="alert alert-success" role="status">{{ __('operations.inquiry_success') }} <a href="{{ route('support-ticket.index', session('device_conversation_id')) }}">{{ __('operations.open_conversation') }}</a></div>@endif
@if(session('purchase_message'))<div class="alert alert-success" role="status">{{ session('purchase_message') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
@php
    $origin = $device->origin_country ? (collect(COUNTRIES)->firstWhere('code', $device->origin_country)['name'] ?? $device->origin_country) : null;
    $gallery = collect($device->images ?? [])->map(fn ($image) => \Illuminate\Support\Facades\Storage::disk('public')->url($image))->values();
@endphp
<div class="medical-device-detail">
    <section class="medical-device-gallery" aria-label="{{ $device->name }}">
        <div class="medical-device-stage"><img src="{{ $device->image_url }}" id="medical-device-main" class="medical-device-main-image" alt="{{ $device->name }}" width="600" height="450"></div>
        @if($gallery->count() > 1)<div class="medical-device-images">@foreach($gallery as $index => $url)<button type="button" class="medical-device-thumb{{ $index === 0 ? ' is-active' : '' }}" data-src="{{ $url }}" aria-label="{{ $device->name }} {{ $index + 1 }}"><img src="{{ $url }}" alt="" loading="lazy" width="80" height="80"></button>@endforeach</div>@endif
    </section>
    <section class="medical-device-info">
        <h1>{{ $device->name }}</h1>
        <ul class="medical-device-facts">
            @if($device->device_condition)<li><span>الحالة</span><strong class="medical-device-chip medical-device-chip--{{ $device->device_condition === 'new' ? 'new' : 'used' }}">{{ $device->device_condition === 'new' ? 'جديد' : 'مستعمل' }}</strong></li>@endif
            @if($origin)<li><span>بلد المنشأ</span><strong>{{ $origin }}</strong></li>@endif
            @if($device->price !== null)<li class="medical-device-fact-price"><span>سعر الجهاز</span><strong>{{ webCurrencyConverter($device->price) }}</strong></li>@endif
        </ul>
        @if($device->description)<div class="medical-device-block"><h2>الوصف</h2><p class="medical-device-copy">{{ $device->description }}</p></div>@endif
        @if($device->specifications)<div class="medical-device-block"><h2>{{ __('operations.specifications') }}</h2><p class="medical-device-copy">{{ $device->specifications }}</p></div>@endif
        <div class="medical-device-action">
            @guest('customer')<p class="medical-device-note">{{ __('operations.sign_in_hint') }}</p>@endguest
            @if($reportPurchase)
                <p class="medical-device-note text-success">تم سداد ثمن التقرير الفني لهذا الجهاز.</p>
                @if($reportPurchase->purchase_requested_at)<p class="medical-device-note">تم استلام طلبكم وسيتم التواصل معكم في أقرب وقت.</p>
                @elseif($reportPurchase->report_sent_at && !$reportPurchase->report_hidden_at)<form action="{{ route('medical-devices.purchase-request', $device) }}" method="post">@csrf<button type="submit" class="btn btn-primary medical-device-cta">طلب شراء الجهاز</button></form>
                @else<p class="medical-device-note">{{ $reportPurchase->confirmed_at ? 'جاري إعداد التقرير الفني.' : 'الإدارة تراجع الدفع.' }}</p>@endif
            @elseif($device->report_price > 0)
                <p class="medical-device-note">ثمن التقرير الفني: <strong>{{ webCurrencyConverter($device->report_price) }}</strong> يُدفع من محفظة الموقع، وهو غير قابل للاسترجاع.</p>
                <form action="{{ route('medical-devices.inquire', $device) }}" method="post" id="medical-device-inquiry">@csrf<input type="hidden" name="report_price" value="{{ $device->report_price }}"><button type="submit" class="btn btn-primary medical-device-cta" onclick="return confirm('سيتم خصم ثمن التقرير من محفظتك. ثمن التقرير غير قابل للاسترجاع. هل تريد المتابعة؟')">طلب تقرير فني</button></form>
            @else<p class="medical-device-note">التقرير الفني غير متاح للطلب حاليًا.</p>@endif
        </div>
    </section>
</div>
</main>@endsection
@push('script')<script>document.querySelectorAll('.medical-device-thumb').forEach(function(b){b.addEventListener('click',function(){var m=document.getElementById('medical-device-main');if(m){m.src=b.dataset.src;}document.querySelectorAll('.medical-device-thumb').forEach(function(x){x.classList.remove('is-active');});b.classList.add('is-active');});});const reportForm=document.getElementById('medical-device-inquiry');if(reportForm){reportForm.addEventListener('submit',function(event){const button=this.querySelector('button');if(button.disabled){event.preventDefault();return;}button.disabled=true;button.setAttribute('aria-busy','true');});window.addEventListener('pageshow',function(){const button=reportForm.querySelector('button');button.disabled=false;button.removeAttribute('aria-busy');});}</script>@endpush
