@extends('layouts.admin.app')
@section('title', __('operations.medical_devices'))
@section('content')
<div class="content container-fluid operations-workspace">
@include('admin-views.operations._feedback')
<div class="operations-toolbar"><h1 class="h3">{{ $device->exists ? __('operations.edit') : __('operations.create_device') }}</h1><a href="{{ route('admin.operations.medical-devices.index') }}">{{ __('operations.medical_devices') }}</a></div>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.operations.medical-devices.save', $device->exists ? $device : null) }}" class="card card-body" data-operations-form>@csrf
<div class="operations-fields">
<label class="operations-wide">{{ __('operations.name') }}<input name="name" class="form-control" required maxlength="200" value="{{ old('name', $device->name) }}"></label>
<label>{{ __('operations.status') }}<select class="form-control" name="is_published"><option value="0" @selected(!old('is_published', $device->is_published))>{{ __('operations.hidden') }}</option><option value="1" @selected(old('is_published', $device->is_published))>{{ __('operations.published_label') }}</option></select></label>
<label>{{ __('operations.order') }}<input type="number" min="0" max="100000" name="sort_order" class="form-control" required value="{{ old('sort_order', $device->sort_order ?? 10) }}"></label>
<label>سعر الجهاز<input type="number" name="price" min="0" step="0.001" class="form-control" required value="{{ old('price', $device->price) }}"></label>
<label>سعر التقرير الفني<input type="number" name="report_price" min="0.001" step="0.001" class="form-control" required value="{{ old('report_price', $device->report_price) }}"></label>
<label>حالة الجهاز<select name="device_condition" class="form-control" required><option value="">اختر الحالة</option><option value="new" @selected(old('device_condition', $device->device_condition) === 'new')>جديد</option><option value="used" @selected(old('device_condition', $device->device_condition) === 'used')>مستعمل</option></select></label>
<label>بلد المنشأ<select name="origin_country" class="form-control" required><option value="">اختر الدولة</option>@foreach(COUNTRIES as $country)<option value="{{ $country['code'] }}" @selected(old('origin_country', $device->origin_country) === $country['code'])>{{ $country['name'] }}</option>@endforeach</select></label>
<label class="operations-wide">{{ __('operations.description') }}<textarea class="form-control" name="description" maxlength="20000">{{ old('description', $device->description) }}</textarea></label>
<label class="operations-wide">{{ __('operations.specifications') }}<textarea class="form-control" name="specifications" maxlength="20000">{{ old('specifications', $device->specifications) }}</textarea></label>
<div class="operations-wide operations-gallery">@foreach($device->images ?? [] as $index => $image)<label><img src="{{ Storage::disk('public')->url($image) }}" alt="{{ $device->name }}"><span class="d-block"><input type="checkbox" name="remove_images[]" value="{{ $index }}"> {{ __('operations.remove_image') }}</span></label>@endforeach</div>
<label class="operations-wide">{{ __('operations.images') }}<input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-control"><span class="operations-muted">{{ __('operations.max_images') }}</span></label>
</div><div class="mt-4"><button type="submit" class="btn btn-primary">{{ __('operations.save') }}</button></div></form>
</div>@endsection
