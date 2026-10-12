@extends('layouts.admin.app')
@section('title','الباقات الشهرية')
@section('content')
<div class="content container-fluid" dir="rtl">
    <h1 class="h3 mb-4">الباقات الشهرية</h1>
    <nav class="nav nav-tabs mb-4" aria-label="نوع الحساب">
        <a class="nav-link {{ $tab === 'customers' ? 'active' : '' }}" href="{{ route('admin.monthly-packages.index',['tab'=>'customers']) }}">العملاء</a>
        <a class="nav-link {{ $tab === 'vendors' ? 'active' : '' }}" href="{{ route('admin.monthly-packages.index',['tab'=>'vendors']) }}">التجار</a>
    </nav>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('subscription_message'))<div class="alert alert-success" role="status">{{ session('subscription_message') }}</div>@endif
    @if($tab === 'vendors')
        <p>إعداد باقات التجار متاح في التحديث القادم.</p>
    @else
        <p>كل باقة تمنح 30 يومًا. الحد الائتماني سماح بالشراء، وتُدفع الطلبات بالكامل بوسيلة دفع فعلية.</p>
        <a class="btn btn-outline-primary mb-4" href="{{ route('admin.customer.monthly-subscriptions.index') }}">سجل المدفوعات والمراجعة</a>
        <h2 class="h4">باقات العملاء</h2>
        @foreach($packages->concat([null]) as $package)
        <form method="post" action="{{ route('admin.monthly-packages.save', $package?->id) }}" class="card card-body mb-3">@csrf
            <h3 class="h5">{{ $package ? $package->name : 'إضافة باقة' }}</h3>
            <div class="row g-3">
                <label class="col-md-3">الاسم<input class="form-control" name="name" required maxlength="150" value="{{ $package?->name }}"></label>
                <label class="col-md-3">السعر (ج.م)<input class="form-control" type="number" min="0.001" step="0.001" name="package_price" required value="{{ $package?->package_price }}"></label>
                <label class="col-md-3">الحد الائتماني (ج.م)<input class="form-control" type="number" min="0.001" step="0.001" name="purchase_limit" required value="{{ $package?->purchase_limit }}"></label>
                <label class="col-md-3">الحالة<select class="form-control" name="status"><option value="1" @selected($package?->status !== false)>متاحة</option><option value="0" @selected($package?->status === false)>معطّلة</option></select></label>
                <label class="col-md-9">الوصف<textarea class="form-control" name="description" maxlength="2000">{{ $package?->description }}</textarea></label>
                <label class="col-md-3">ترتيب العرض<input class="form-control" type="number" name="sort_order" min="0" value="{{ $package?->sort_order ?? 0 }}"></label>
            </div><button class="btn btn-primary mt-3 align-self-start" type="submit">حفظ الباقة</button>
        </form>
        @if($package)<form method="post" action="{{ route('admin.monthly-packages.delete',$package->id) }}" class="mb-4">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit">حذف الباقة غير المستخدمة</button></form>@endif
        @endforeach
        <h2 class="h4 mt-5">عروض زيادة الحد</h2><p>تحتفظ الزيادة بتاريخ انتهاء الباقة المرتبطة بها.</p>
        @foreach($offers->concat([null]) as $offer)
        <form method="post" action="{{ route('admin.monthly-packages.offer',$offer?->id) }}" class="card card-body mb-3">@csrf
            <div class="row g-3">
                <label class="col-md-3">الاسم<input class="form-control" name="name" required maxlength="150" value="{{ $offer?->name }}"></label>
                <label class="col-md-3">سعر الدفع (ج.م)<input class="form-control" type="number" name="price" min="0.001" step="0.001" required value="{{ $offer?->price }}"></label>
                <label class="col-md-3">الحد الإضافي (ج.م)<input class="form-control" type="number" name="allowance" min="0.001" step="0.001" required value="{{ $offer?->allowance }}"></label>
                <label class="col-md-3">الحالة<select class="form-control" name="active"><option value="1" @selected($offer?->active !== 0)>متاح</option><option value="0" @selected($offer?->active === 0)>معطّل</option></select></label>
            </div><button class="btn btn-primary mt-3 align-self-start" type="submit">حفظ العرض</button>
        </form>
        @endforeach
    @endif
</div>
@endsection
