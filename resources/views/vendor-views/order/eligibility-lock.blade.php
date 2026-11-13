@php($eligibility = $row['fulfillment_eligibility'])
@php($fullyLocked = (bool) ($eligibility['details_locked'] ?? false))
<section class="vo-lock" dir="rtl" aria-labelledby="vo-lock-title-{{ $row['id'] }}">
    <div class="vo-lock__head">
        <span class="vo-lock__badge" aria-hidden="true"><i class="fi fi-rr-lock"></i></span>
        <div>
            <h3 id="vo-lock-title-{{ $row['id'] }}" class="vo-lock__title">{{ $fullyLocked ? 'عندك طلب جديد في انتظارك!' : 'جزء من الطلب في انتظار الباقة' }}</h3>
            <p class="vo-lock__sub">{{ $eligibility['pending_on_vendor_side'] ? 'الطلب بانتظارك — اشترك في باقة لتبدأ التنفيذ.' : 'اشترك في باقة وتفتح لك التفاصيل كاملة فورًا.' }}</p>
        </div>
    </div>

    <div class="vo-lock__amount">
        <span>{{ $fullyLocked ? 'إجمالي الطلب المستحق لك' : 'إجمالي الجزء المخفي' }}</span>
        <strong><bdi>{{ webCurrencyConverter($eligibility['restricted_amount']) }}</bdi></strong>
    </div>

    <div class="vo-lock__preview" aria-hidden="true">
        <div class="vo-lock__skeleton">
            <div class="vo-lock__row"><i></i><b style="width:46%"></b><b style="width:18%"></b></div>
            <div class="vo-lock__row"><i></i><b style="width:58%"></b><b style="width:14%"></b></div>
            <div class="vo-lock__row"><i></i><b style="width:38%"></b><b style="width:20%"></b></div>
            <div class="vo-lock__row vo-lock__row--wide"><b style="width:70%"></b></div>
            <div class="vo-lock__row vo-lock__row--wide"><b style="width:52%"></b></div>
        </div>
        <div class="vo-lock__veil"><span class="vo-lock__veil-icon"><i class="fi fi-rr-lock"></i></span><strong>التفاصيل مخفية</strong><small>المنتجات • بيانات العميل • عنوان التسليم</small></div>
    </div>


    <div class="vo-lock__actions">
        <a class="vo-lock__cta" href="{{ route('vendor.packages.index') }}">اشترك الآن وافتح الطلب</a>
    </div>
</section>
