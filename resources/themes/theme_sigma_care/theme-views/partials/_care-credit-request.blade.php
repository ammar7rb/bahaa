@php($creditArabic = getLanguageCode(country_code:getDefaultLanguage()) === 'ar')
<div class="care-credit-action">
    @if(auth('customer')->check())
        <form id="care-credit-form" method="post" action="{{ route('credit-request.store') }}">
            @csrf
            <button class="btn btn-primary care-credit-cta" type="submit" data-label="{{ $creditArabic ? 'تحقق من الحد الائتماني الآن' : 'Check credit score now' }}" data-loading="{{ $creditArabic ? 'جارٍ إرسال طلبك…' : 'Sending your request…' }}">{{ $creditArabic ? 'تحقق من الحد الائتماني الآن' : 'Check credit score now' }} <i class="bi bi-arrow-{{ $creditArabic ? 'left' : 'right' }}" aria-hidden="true"></i></button>
            <p id="care-credit-status" class="care-credit-status" role="status" aria-live="polite" tabindex="-1" hidden></p>
        </form>
    @else
        <button class="btn btn-primary care-credit-cta" type="button" data-bs-toggle="modal" data-bs-target="#registerModal">{{ $creditArabic ? 'تحقق من الحد الائتماني الآن' : 'Check credit score now' }} <i class="bi bi-arrow-{{ $creditArabic ? 'left' : 'right' }}" aria-hidden="true"></i></button>
        <p class="care-credit-signup">{{ $creditArabic ? 'أنشئ حسابًا لإرسال طلبك، أو سجّل الدخول إذا كان لديك حساب بالفعل.' : 'Create an account to submit your request, or sign in if you already have one.' }}</p>
    @endif
</div>
@push('script')
    <script src="{{ theme_asset('assets/js/care-credit.js') }}?v=1" defer></script>
@endpush
