<section class="card vendor-profile-section" id="vendor-profile-packages">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3"><div><h3>الباقة والمساحات الإعلانية</h3><p class="text-muted small">كل بطاقة تمثل مساحة فعلية في {{ $subscription['active'] ? 'الاشتراك الحالي' : 'آخر اشتراك مدفوع' }}. الأساسية: {{ $capacity['included'] }} · الإضافية: {{ $capacity['additional'] }}.</p></div><button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#vendor-subscriptions-modal"><i class="fi fi-rr-time-past" aria-hidden="true"></i> سجل الاشتراكات</button></div>
    @if($vendorProfile['next'])<p class="vendor-profile-notice">اشتراك مدفوع قادم: <strong>{{ $vendorProfile['next']->package_name }}</strong> · يبدأ {{ $vendorProfile['next']->starts_at?->format('Y/m/d H:i') }} · ينتهي {{ $vendorProfile['next']->expires_at?->format('Y/m/d H:i') }}</p>@endif
    @if($vendorProfile['pendingPayments'])<p class="vendor-profile-notice">طلبات بانتظار الدفع أو المراجعة: {{ $vendorProfile['pendingPayments'] }}. لا تُحتسب ضمن الاشتراك الساري.</p>@endif
    <div class="vendor-slot-grid">
        @forelse($vendorProfile['displaySlots'] as $slot)
            @php
                $product=$slot->product;
                $paidNow=$slot->subscription?->payment_status === 'paid' && in_array($slot->subscription?->status,['active','scheduled'],true) && $slot->subscription?->starts_at?->lte(now()) && $slot->subscription?->expires_at?->gt(now());
                $availableNow=$paidNow && $slot->editable;
                $reserved=$availableNow && !$slot->product_id && app(\App\Services\VendorSlotReservationService::class)->isReserved($slot);
                $days=$availableNow ? max(0,(int)ceil(now()->diffInSeconds($slot->expires_at,false)/86400)) : 0;
            @endphp
            <article class="vendor-slot-card {{ $product ? 'has-product' : 'is-empty' }}">
                <header><span>مساحة {{ $slot->position }}</span><span class="profile-badge {{ $availableNow ? 'is-success' : 'is-warning' }}">{{ !$availableNow ? 'غير متاحة حاليًا' : ($product ? 'مرتبطة بمنتج' : ($reserved ? 'محجوزة للإضافة' : 'متاحة')) }}</span></header>
                @if($product)
                    <img class="vendor-slot-image" src="{{ getStorageImages(path:$product->thumbnail_full_url,type:'backend-basic') }}" alt="{{ $product->name }}" loading="lazy">
                    <h4>{{ $product->name }}</h4>
                    <dl class="vendor-slot-data"><div><dt>السعر</dt><dd><bdi>{{ $money($product->unit_price) }}</bdi></dd></div><div><dt>الكمية المتبقية بالمخزون</dt><dd>{{ $product->product_type === 'physical' ? $product->current_stock : 'لا ينطبق' }}</dd></div><div><dt>المتبقي من فترة المساحة</dt><dd>{{ $days }} يوم</dd></div></dl>
                    <button type="button" class="btn btn-outline-primary vendor-slot-eye" data-bs-toggle="modal" data-bs-target="#vendor-slot-product-{{ $slot->id }}" aria-label="عرض تفاصيل {{ $product->name }}"><i class="fi fi-rr-eye" aria-hidden="true"></i><span>تفاصيل المنتج</span></button>
                @else
                    <div class="vendor-slot-placeholder"><i class="fi fi-rr-box" aria-hidden="true"></i><h4>{{ $slot->product_id ? 'منتج غير متاح' : ($reserved ? 'مساحة محجوزة' : 'مساحة بدون منتج') }}</h4><p>{{ $slot->product_id ? 'مرجع المنتج: #'.$slot->product_id : 'لا يوجد منتج مرتبط بهذه المساحة.' }}</p></div>
                @endif
                <footer>{{ $slot->extra_ad_purchase_id ? 'مساحة إضافية' : 'ضمن الباقة' }} · حتى <time>{{ $slot->expires_at?->format('Y/m/d') }}</time></footer>
            </article>
        @empty<p class="text-muted">لا توجد مساحات مسجلة لهذه الفترة.</p>@endforelse
    </div>
    <p class="small text-muted mt-3 mb-0">ارتباط المنتج بمساحة لا يعني ظهوره قبل استيفاء المراجعة والنشر. استبدال المنتج لا يمدد فترة المساحة.</p>
</section>
@foreach($vendorProfile['displaySlots'] as $slot)
    @if($slot->product)
        @include('admin-views.vendor.profile.modal-start',['modalId'=>'vendor-slot-product-'.$slot->id,'modalTitle'=>$slot->product->name])
            <a class="btn btn-outline-primary mb-3" href="{{ route('admin.products.view',['addedBy'=>'vendor','id'=>$slot->product_id]) }}" target="_blank" rel="noopener">فتح تفاصيل المنتج في صفحة مستقلة</a>
            <iframe class="vendor-product-frame" title="تفاصيل {{ $slot->product->name }}" data-product-preview="{{ route('admin.products.view',['addedBy'=>'vendor','id'=>$slot->product_id]) }}" loading="lazy"></iframe>
        @include('admin-views.vendor.profile.modal-end')
    @endif
@endforeach
@include('admin-views.vendor.profile.subscriptions-modal')
