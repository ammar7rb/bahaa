@extends('layouts.admin.app')
@section('title', __('operations.medical_devices'))
@section('content')
@php($published = (string) old('is_published', $device->exists ? (int) $device->is_published : 1))
@php($err = fn ($f) => $errors->first($f))
<div class="content container-fluid operations-workspace device-form">
@include('admin-views.operations._feedback')
<div class="operations-toolbar"><h1 class="h3">{{ $device->exists ? __('operations.edit') : __('operations.create_device') }}</h1><a href="{{ route('admin.operations.medical-devices.index') }}">&larr; {{ __('operations.medical_devices') }}</a></div>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.operations.medical-devices.save', $device->exists ? $device : null) }}" data-operations-form>@csrf
<section class="card card-body device-card"><h2 class="device-card-title">البيانات الأساسية</h2>
<div class="operations-fields">
<label class="operations-wide">{{ __('operations.name') }} <b class="text-danger">*</b><input name="name" class="form-control @if($err('name')) is-invalid @endif" required maxlength="200" value="{{ old('name', $device->name) }}" placeholder="مثال: جهاز سونار محمول">@if($err('name'))<span class="invalid-feedback d-block">{{ $err('name') }}</span>@endif</label>
<label>حالة الجهاز <b class="text-danger">*</b><select name="device_condition" class="form-control @if($err('device_condition')) is-invalid @endif" required><option value="">اختر الحالة</option><option value="new" @selected(old('device_condition', $device->device_condition) === 'new')>جديد</option><option value="used" @selected(old('device_condition', $device->device_condition) === 'used')>مستعمل</option></select>@if($err('device_condition'))<span class="invalid-feedback d-block">{{ $err('device_condition') }}</span>@endif</label>
<label>بلد المنشأ <b class="text-danger">*</b><select name="origin_country" class="form-control @if($err('origin_country')) is-invalid @endif" required><option value="">اختر الدولة</option>@foreach(COUNTRIES as $country)<option value="{{ $country['code'] }}" @selected(old('origin_country', $device->origin_country) === $country['code'])>{{ $country['name'] }}</option>@endforeach</select>@if($err('origin_country'))<span class="invalid-feedback d-block">{{ $err('origin_country') }}</span>@endif</label>
</div></section>
<section class="card card-body device-card"><h2 class="device-card-title">الأسعار (بالجنيه المصري)</h2>
<div class="operations-fields">
<label>سعر الجهاز (ج.م) <b class="text-danger">*</b><input type="number" name="price" min="0" step="0.001" inputmode="decimal" class="form-control @if($err('price')) is-invalid @endif" required value="{{ old('price', $device->price) }}">@if($err('price'))<span class="invalid-feedback d-block">{{ $err('price') }}</span>@endif</label>
<label>سعر التقرير الفني (ج.م) <b class="text-danger">*</b><input type="number" name="report_price" min="0.001" step="0.001" inputmode="decimal" class="form-control @if($err('report_price')) is-invalid @endif" required value="{{ old('report_price', $device->report_price) }}"><span class="operations-muted">يدفعه العميل لفتح تقرير الجهاز.</span>@if($err('report_price'))<span class="invalid-feedback d-block">{{ $err('report_price') }}</span>@endif</label>
</div></section>
<section class="card card-body device-card"><h2 class="device-card-title">الوصف والمواصفات</h2>
<div class="operations-fields">
<label class="operations-wide">{{ __('operations.description') }}<textarea rows="4" class="form-control" name="description" maxlength="20000">{{ old('description', $device->description) }}</textarea></label>
<label class="operations-wide">{{ __('operations.specifications') }}<textarea rows="5" class="form-control" name="specifications" maxlength="20000">{{ old('specifications', $device->specifications) }}</textarea></label>
</div></section>
<section class="card card-body device-card"><h2 class="device-card-title">الصور</h2>
<div class="operations-gallery">@foreach($device->images ?? [] as $index => $image)<label><img src="{{ Storage::disk('public')->url($image) }}" alt="{{ $device->name }}"><span class="d-block"><input type="checkbox" name="remove_images[]" value="{{ $index }}"> {{ __('operations.remove_image') }}</span></label>@endforeach</div>
<label class="d-block">{{ __('operations.images') }}<input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-control @if($errors->has('images') || $errors->has('images.*')) is-invalid @endif" data-device-images><span class="operations-muted">{{ __('operations.max_images') }}</span>@foreach($errors->get('images.*') as $messages)<span class="invalid-feedback d-block">{{ $messages[0] }}</span>@endforeach @if($err('images'))<span class="invalid-feedback d-block">{{ $err('images') }}</span>@endif</label>
<div class="device-previews" data-device-previews></div>
</section>
<section class="card card-body device-card"><h2 class="device-card-title">النشر</h2>
<div class="operations-fields">
<label>{{ __('operations.status') }}<select class="form-control" name="is_published"><option value="1" @selected($published === '1')>{{ __('operations.published_label') }} (يظهر للعملاء)</option><option value="0" @selected($published === '0')>{{ __('operations.hidden') }} (لا يظهر للعملاء)</option></select></label>
<label>{{ __('operations.order') }}<input type="number" min="0" max="100000" name="sort_order" class="form-control" required value="{{ old('sort_order', $device->sort_order ?? 10) }}"></label>
</div></section>
<div class="device-actions"><button type="submit" class="btn btn-primary">{{ __('operations.save') }}</button><a class="btn btn-outline-secondary" href="{{ route('admin.operations.medical-devices.index') }}">إلغاء</a></div>
</form></div>
@endsection
