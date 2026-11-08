@auth('customer')
    @php($reportReservations = \Illuminate\Support\Facades\Schema::hasTable('medical_device_report_purchases') ? \App\Models\MedicalDeviceReportPurchase::query()->with('device')->where('customer_id', auth('customer')->id())->whereNotNull('confirmed_at')->whereNull('cart_removed_at')->latest('id')->get() : collect())
    @if($reportReservations->isNotEmpty())
        <section class="card card-body mb-4" dir="rtl" aria-label="الأجهزة المتخصصة قيد الفحص">
            <h2 class="h5">أجهزة متخصصة قيد الفحص</h2>
            <p class="text-muted small">تم سداد رسوم الفحص. هذه الأجهزة محفوظة في السلة إلى أن تُجهز التقارير، ولا تُحذف من جهة العميل.</p>
            @foreach($reportReservations as $reservation)
                <div class="d-flex align-items-center justify-content-between gap-3 border-top py-3">
                    <div><a href="{{ route('medical-devices.show', $reservation->device_id) }}">{{ $reservation->device?->name ?? 'جهاز #'.$reservation->device_id }}</a><div class="small text-muted">{{ $reservation->purchase_requested_at ? 'طلب شراء مرسل للإدارة' : (($reservation->report_sent_at && !$reservation->report_hidden_at) ? 'التقرير الفني متاح في الرسائل' : 'جاري إعداد التقرير الفني') }}</div></div>
                    <div class="small">رسوم الفحص: {{ webCurrencyConverter($reservation->amount) }}</div>
                </div>
            @endforeach
        </section>
    @endif
@endauth
