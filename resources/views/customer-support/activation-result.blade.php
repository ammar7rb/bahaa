<section class="support-activation-result" data-activation-result @if($hidden ?? false) hidden @endif role="status" aria-live="polite" tabindex="-1">
    <span class="support-activation-check">@include('customer-subscription.icon',['name'=>'check'])</span>
    <h2 data-activation-heading>تم تفعيل الحساب بنجاح</h2>
    <p data-activation-description>جارٍ نقلك إلى الرئيسية…</p>
    <a href="{{ $redirectUrl }}" data-activation-continue>الانتقال الآن</a>
</section>
