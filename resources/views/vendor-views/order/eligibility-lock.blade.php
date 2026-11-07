@php($eligibility = $row['fulfillment_eligibility'])
<section class="vf-section vendor-eligibility-lock" dir="rtl" aria-label="جزء مقيد من طلب التنفيذ">
    <div class="d-flex align-items-center mb-3" style="gap:8px;flex-wrap:wrap"><i class="fi fi-rr-lock" aria-hidden="true"></i><strong>{{ $eligibility['pending_on_vendor_side'] ? 'طلبات معلقة من جهة التاجر' : 'تفاصيل تنفيذ مقيدة' }}</strong><span class="badge bg-danger text-white">!</span></div>
    <p>{{ $eligibility['details_locked'] ? 'إجمالي طلب التنفيذ المستحق لك' : 'إجمالي الجزء المقيد فقط، منفصل عن المنتجات المتاحة' }}: <strong><bdi>{{ webCurrencyConverter($eligibility['restricted_amount']) }}</bdi></strong></p>
    <p class="text-muted">وقت تحويل الإدارة: <bdi>{{ \Carbon\Carbon::parse($eligibility['forwarded_at'])->format('Y-m-d H:i') }}</bdi></p>
    <div aria-hidden="true" style="filter:blur(5px);user-select:none;pointer-events:none;padding:18px;background:#f1f5fa;border-radius:12px;margin-bottom:16px">تفاصيل المنتجات والتنفيذ<br>ــــــــــــــــــــــــــــــــــ<br>ــــــــــــــــــــ</div>
    <button class="btn btn-outline-primary" type="button" onclick="alert('برجاء اختيار إحدى الباقات')"><i class="fi fi-rr-lock" aria-hidden="true"></i> عرض التفاصيل</button>
    <a class="btn btn-primary" href="{{ route('vendor.packages.index') }}">اختيار باقة</a>
    <p class="text-muted mt-3 mb-0">برجاء اختيار إحدى الباقات. يظل الطلب محفوظًا دون إلغاء تلقائي أو غرامة بسبب القفل.</p>
</section>
