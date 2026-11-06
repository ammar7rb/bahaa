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
        @foreach($packages as $package)
        <form id="package-{{ $package?->id ?? 'new' }}" method="post" action="{{ route('admin.monthly-packages.save', $package?->id) }}" class="card card-body mb-3" style="scroll-margin-top:100px">@csrf
            <h3 class="h5">{{ $package ? $package->name : 'إضافة باقة' }}</h3>
            <div class="row g-3">
                <label class="col-md-3">الاسم<input class="form-control" readonly value="{{ $package->name }}"></label>
                <label class="col-md-3">السعر (ج.م)<input class="form-control" type="number" min="0.001" step="0.001" name="package_price" required value="{{ $package?->package_price }}"></label>
                <label class="col-md-3">الحد الائتماني (ج.م)<input class="form-control" type="number" min="0.001" step="0.001" name="purchase_limit" required value="{{ $package?->purchase_limit }}"></label>
                <label class="col-md-3">رسوم تجديد الحد (ج.م)<input class="form-control" type="number" min="0.001" step="0.001" name="renewal_price" value="{{ $package->renewal_price }}"><small>يُجدد الحد فقط حتى تاريخ انتهاء الاشتراك الحالي. اتركه فارغًا لتعطيل التجديد.</small></label>
                <label class="col-md-3">الحالة<select class="form-control" name="status"><option value="1" @selected($package?->status !== false)>متاحة</option><option value="0" @selected($package?->status === false)>معطّلة</option></select></label>
                <label class="col-md-9">الوصف<textarea class="form-control" name="description" maxlength="2000">{{ $package?->description }}</textarea></label>
                <label class="col-md-3">ترتيب العرض<input class="form-control" type="number" name="sort_order" min="0" value="{{ $package?->sort_order ?? 0 }}"></label>
            </div><button class="btn btn-primary mt-3 align-self-start" type="submit">حفظ الباقة</button>
        </form>
        @endforeach
        <h2 id="credit-offers" class="h4 mt-5" style="scroll-margin-top:100px">عروض زيادة الحد الائتماني</h2><p>عدّل السعر والحد الأساسيين ثم اضغط حفظ العرض؛ تُحدّث الاختيارات الخمسة للعميل تلقائيًا. تحتفظ الزيادة بتاريخ انتهاء الباقة المرتبطة بها.</p>
        @foreach($offers->concat([null]) as $offer)
        <form id="offer-{{ $offer?->id ?? 'new' }}" method="post" action="{{ route('admin.monthly-packages.offer',$offer?->id) }}" class="card card-body mb-3" style="scroll-margin-top:100px">@csrf
            <h3 class="h5">{{ $offer ? 'تعديل العرض: '.$offer->name : 'إضافة عرض زيادة جديد' }}</h3>
            @unless($offer)<p>املأ البيانات لإنشاء عرض إضافي مستقل. هذا النموذج لا يعدّل العرض الموجود أعلاه.</p>@endunless
            <div class="row g-3">
                <label class="col-md-3">الاسم<input class="form-control" name="name" required maxlength="150" value="{{ $offer?->name }}"></label>
                <label class="col-md-3">سعر الدفع (ج.م)<input class="form-control" type="number" name="price" min="0.001" step="0.001" required value="{{ $offer?->price }}"></label>
                <label class="col-md-3">الحد الإضافي (ج.م)<input class="form-control" type="number" name="allowance" min="0.001" step="0.001" required value="{{ $offer?->allowance }}"></label>
                <label class="col-md-3">الحالة<select class="form-control" name="active"><option value="1" @selected($offer?->active !== 0)>متاح</option><option value="0" @selected($offer?->active === 0)>معطّل</option></select></label>
            </div>
            @if($offer)<div class="table-responsive mt-3"><table class="table table-sm mb-0"><caption class="text-start">الاختيارات الخمسة التي تظهر للعميل</caption><thead><tr><th>المضاعف</th><th>الزيادة في الحد الائتماني</th><th>سعر الدفع</th></tr></thead><tbody>@for($quantity=1;$quantity<=5;$quantity++)<tr><td>×{{ $quantity }}</td><td>{{ number_format($offer->allowance*$quantity,2) }} ج.م</td><td>{{ number_format($offer->price*$quantity,2) }} ج.م</td></tr>@endfor</tbody></table></div>@endif
            <button class="btn btn-primary mt-3 align-self-start" type="submit">{{ $offer ? 'حفظ تعديلات العرض' : 'إضافة العرض' }}</button>
        </form>
        @if($offer)<form method="post" action="{{ route('admin.monthly-packages.offer-delete',$offer->id) }}" class="mb-4">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit">حذف عرض {{ $offer->name }}</button><p class="small text-muted mt-2">إذا ارتبط العرض بمدفوعات، عطّله من الحالة أعلاه بدل حذفه للحفاظ على السجل المالي.</p></form>@endif
        @endforeach
    @endif
</div>
@endsection
