@php
    $welcomeSeller = auth('seller')->user();
    $isNewSeller = $welcomeSeller && $welcomeSeller->created_at && $welcomeSeller->created_at->gt(now()->subDays(14));
@endphp
@if($isNewSeller)
<section class="sv-welcome is-hidden" id="sv-welcome" data-key="sv-welcome-{{ $welcomeSeller->id }}" role="status">
    <span class="sv-welcome__icon" aria-hidden="true">🎉</span>
    <div class="sv-welcome__body">
        <h2 class="sv-welcome__title">{{ translate('vendor_welcome_title') }} <b>{{ translate('vendor_welcome_badge') }}</b></h2>
        <p class="sv-welcome__text">{{ translate('vendor_welcome_text') }}</p>
    </div>
    <a class="sv-welcome__cta" href="{{ route('vendor.products.add') }}">{{ translate('vendor_welcome_cta') }}</a>
    <button type="button" class="sv-welcome__close" aria-label="{{ translate('vendor_welcome_close') }}" data-sv-welcome-close>&times;</button>
</section>
<script>
(function(){var el=document.getElementById('sv-welcome');if(!el)return;var k=el.dataset.key,seen=false;
try{seen=localStorage.getItem(k)==='1'}catch(e){}
if(!seen)el.classList.remove('is-hidden');
el.querySelector('[data-sv-welcome-close]').addEventListener('click',function(){el.classList.add('is-hidden');try{localStorage.setItem(k,'1')}catch(e){}});})();
</script>
@endif
