<section class="transfer-recipient" aria-label="بيانات الاستقبال">
    <div class="transfer-section-heading">@include('customer-subscription.icon',['name'=>$instapay ? 'transfer' : 'wallet'])<h4>التحويل إلى</h4></div>
    @forelse($method->method_fields ?? [] as $field)
        @php($copyable = app(\App\Services\CustomerTransferInformationService::class)->recipientIsNumber($field))
        <div class="transfer-destination">
            <span>{{ $copyable ? ($instapay ? 'رقم التحويل البنكي' : 'رقم المحفظة') : (preg_match('/name|owner|holder|اسم|صاحب/iu',$field['input_name'] ?? '') ? 'اسم صاحب الحساب' : translatePaymentText($field['input_name'] ?? '')) }}</span>
            @if($copyable)<button type="button" class="transfer-copy" data-copy-value="{{ $field['input_data'] ?? '' }}" aria-label="نسخ {{ $instapay ? 'رقم التحويل البنكي' : 'رقم المحفظة' }}"><bdi dir="ltr">{{ $field['input_data'] ?? '' }}</bdi><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V3H3v13h5"/></svg></button>
            @else<strong class="transfer-owner">{{ $field['input_data'] ?? '' }}</strong>@endif
        </div>
    @empty<p role="alert">بيانات الاستقبال غير متاحة؛ تواصل مع الإدارة قبل التحويل.</p>@endforelse
    <p class="transfer-copy-status" aria-live="polite"></p>
</section>
