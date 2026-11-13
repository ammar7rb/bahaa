<div class="medical-device-grid">
@forelse($devices as $device)
@php
    $origin = $device->origin_country ? (collect(COUNTRIES)->firstWhere('code', $device->origin_country)['name'] ?? $device->origin_country) : null;
@endphp
<article class="medical-device-card">
    <a class="medical-device-media" href="{{ route('medical-devices.show', $device) }}" aria-label="{{ $device->name }}">
        <img src="{{ $device->image_url }}" alt="{{ $device->name }}" loading="lazy" width="400" height="300">
        @if($device->device_condition)<span class="medical-device-chip medical-device-chip--{{ $device->device_condition === 'new' ? 'new' : 'used' }}">{{ $device->device_condition === 'new' ? 'جديد' : 'مستعمل' }}</span>@endif
    </a>
    <div class="medical-device-body">
        <h2><a href="{{ route('medical-devices.show', $device) }}">{{ $device->name }}</a></h2>
        @if($origin)<p class="medical-device-origin">بلد المنشأ: <strong>{{ $origin }}</strong></p>@endif
        @if($device->description)<p class="medical-device-excerpt">{{ \Illuminate\Support\Str::limit($device->description, 100) }}</p>@endif
        <div class="medical-device-footer">
            @if($device->price !== null)<div class="medical-device-price"><span>سعر الجهاز</span><strong>{{ webCurrencyConverter($device->price) }}</strong></div>@endif
            <a class="btn btn-primary medical-device-cta" href="{{ route('medical-devices.show', $device) }}">طلب تقرير فني</a>
        </div>
    </div>
</article>
@empty
<p class="medical-device-empty">{{ __('operations.empty_devices') }}</p>
@endforelse
</div>
<div class="mt-4">{{ $devices->links() }}</div>
