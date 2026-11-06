<dialog class="proof-dialog" id="payment-proof-preview" dir="rtl" aria-labelledby="payment-proof-title">
    <header><h2 id="payment-proof-title">إيصال التحويل</h2><button type="button" data-proof-close aria-label="إغلاق معاينة الإيصال">@include('customer-subscription.icon',['name'=>'close'])</button></header>
    <div class="proof-dialog-body"><p data-proof-loading role="status">جارٍ تحميل الإيصال…</p><p data-proof-error role="alert" hidden>تعذر تحميل الإيصال. أغلق المعاينة وحاول مجددًا.</p><img data-proof-image alt="إيصال التحويل" hidden></div>
</dialog>
