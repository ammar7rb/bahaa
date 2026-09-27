@if(auth('customer')->check() && \Illuminate\Support\Facades\Schema::hasTable('customer_insurance_ledger_entries'))
    @php
        $insuranceNoticeCustomerId = (int) auth('customer')->id();
        $insuranceNoticeSummary = app(\App\Services\CustomerInsuranceBalanceService::class)->summary($insuranceNoticeCustomerId);
    @endphp
    <div id="customer-insurance-balance-notice" class="container mt-2 text-align-direction" hidden>
        <div class="alert alert-info d-flex align-items-center justify-content-between gap-3 py-2 mb-0 shadow-sm" role="status">
            <div class="d-flex align-items-center gap-2">
                <i class="fi fi-sr-shield-check fs-5"></i>
                <span>رصيد التأمين الخاص بك هو <strong>{{ webCurrencyConverter($insuranceNoticeSummary['available_balance'] ?? 0) }}</strong>، ويُستخدم لسداد التأمين فقط.</span>
            </div>
            <button type="button" class="btn-close flex-shrink-0" aria-label="إغلاق التنبيه"></button>
        </div>
    </div>
    <script>
        (function () {
            const notice = document.getElementById('customer-insurance-balance-notice');
            if (!notice) return;
            const key = 'ba-insurance-balance-notice-dismissed-{{ $insuranceNoticeCustomerId }}';
            let dismissed = false;
            try { dismissed = localStorage.getItem(key) === '1'; } catch (_) {}
            if (!dismissed) notice.hidden = false;
            notice.querySelector('.btn-close')?.addEventListener('click', function () {
                notice.remove();
                try { localStorage.setItem(key, '1'); } catch (_) {}
            });
        }());
    </script>
@endif
