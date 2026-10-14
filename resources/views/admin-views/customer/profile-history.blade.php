<section class="card card-body mb-3" data-customer-profile-history data-url="{{ route('admin.customer.profile-snapshot',$customer->id) }}">
    <h3 class="h5">أحدث بيانات العميل وسجل التحديثات</h3>
    <p data-profile-sync-status role="status">جارٍ تحميل أحدث البيانات…</p>
    <div data-profile-live-summary></div>
    <h4 class="h6 mt-3">العناوين المحفوظة</h4><div data-profile-live-addresses></div>
    <h4 class="h6 mt-3">ما الذي تغير ومتى؟</h4><div data-profile-change-list></div>
    <nav class="d-flex gap-2 mt-3" aria-label="صفحات سجل التحديثات"><button type="button" class="btn btn-outline-primary" data-history-previous>السابق</button><span data-history-page></span><button type="button" class="btn btn-outline-primary" data-history-next>التالي</button></nav>
</section>
@push('script')<script src="{{ asset('assets/back-end/js/customer-profile-history.js') }}?v=20261014" defer></script>@endpush
