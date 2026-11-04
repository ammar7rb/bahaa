@once
@push('css_or_js')
<link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/front-end/css/customer-transfer.css') }}?v={{ filemtime(public_path('assets/front-end/css/customer-transfer.css')) }}">
@endpush
@endonce
<dialog id="package-payment-{{ $package->id }}" class="vendor-payment-dialog subscription-transfer-dialog" aria-labelledby="payment-title-{{ $package->id }}">
    <header class="vendor-payment-dialog-header"><h2 id="payment-title-{{ $package->id }}" data-payment-title>اختر طريقة الدفع</h2><div class="vendor-payment-header-actions"><button type="button" class="vendor-payment-back" data-payment-back hidden>@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'arrow-left'])<span>تغيير طريقة الدفع</span></button><button type="button" data-payment-close aria-label="إغلاق">&times;</button></div></header>
    <p class="vendor-payment-amount">{{ $package->name }} <strong>{{ webCurrencyConverter(amount: $package->package_price) }}</strong></p>
    <div class="vendor-payment-options" role="group" aria-label="طرق الدفع">
        @foreach(['balance'=>'الدفع من خلال المحفظة','instapay'=>'إنستا باي','wallet'=>'محفظة إلكترونية'] as $channel=>$label)
        @php($available = $channel === 'balance' || $offlinePaymentMethods->where('payment_channel',$channel)->isNotEmpty())
        <button type="button" class="vendor-payment-option" data-payment-choice="{{ $channel }}" aria-pressed="false" @disabled(!$available)>
            @if($channel === 'instapay')<img src="{{ dynamicAsset(path: 'public/assets/back-end/img/withdrawal/instapay.png') }}" alt="" width="48" height="32" style="object-fit:cover">@else<span aria-hidden="true">@include('layouts.vendor.partials.v2._workspace-icon',['icon'=>'wallet'])</span>@endif
            <span>{{ $label }}@if(!$available)<small>غير متاحة حاليًا</small>@endif</span>
        </button>
        @endforeach
    </div>
    <div data-payment-panel="balance" hidden>
        <div class="vendor-payment-hero vendor-payment-hero-balance"><span class="vendor-payment-mark" aria-hidden="true">@include('layouts.vendor.partials.v2._workspace-icon',['icon'=>'wallet'])</span><div><h3>الدفع من خلال المحفظة</h3><p>راجع المبلغ ثم أكّد الدفع. يتم تأكيد العملية تلقائيًا.</p></div></div>
        <dl class="vendor-payment-receipt"><div><dt>رصيد المدفوعات الحالي</dt><dd>{{ webCurrencyConverter(amount: $operatingBalance) }}</dd></div><div><dt>مبلغ الاشتراك</dt><dd>{{ webCurrencyConverter(amount: $package->package_price) }}</dd></div>@if($operatingBalance >= $package->package_price)<div class="vendor-payment-receipt-total"><dt>الرصيد بعد الدفع</dt><dd>{{ webCurrencyConverter(amount: $operatingBalance - $package->package_price) }}</dd></div>@endif</dl>
        @if($operatingBalance < $package->package_price)<p role="status">رصيد المدفوعات غير كافٍ. اختر طريقة أخرى أو أضف رصيدًا إلى محفظة المدفوعات.</p>@endif
        <form method="post" action="{{ route('vendor.packages.pay') }}">@csrf<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="package_id" value="{{ $package->id }}"><input type="hidden" name="expected_package_price" value="{{ number_format($package->package_price, 2, '.', '') }}"><input type="hidden" name="payment_method" value="operating_balance"><button class="btn btn--primary w-100" @disabled($operatingBalance < $package->package_price || $summary['pending_review'])>ادفع الآن</button></form>
    </div>
    @foreach(['instapay','wallet'] as $channel)
    @php($methods = $offlinePaymentMethods->where('payment_channel',$channel)->values())
    <div data-payment-panel="{{ $channel }}" hidden>
        @if($methods->count()>1)<label class="d-block mb-3">حساب التحويل<select class="form-control" data-transfer-method>@foreach($methods as $method)<option value="{{ $method->id }}">{{ $method->method_name }}</option>@endforeach</select></label>@endif
        @foreach($methods as $method)
        <form class="transfer-experience {{ $channel === 'instapay' ? 'transfer-instapay' : 'transfer-cash' }}" data-transfer-form="{{ $method->id }}" @if(!$loop->first) hidden @endif method="post" enctype="multipart/form-data" action="{{ route('vendor.packages.offline-payment') }}">@csrf
            <header class="transfer-hero"><span class="transfer-provider">{{ $channel === 'instapay' ? 'InstaPay' : $method->method_name }}</span><h3>بيانات التحويل</h3><strong class="transfer-amount">{{ webCurrencyConverter(amount: $package->package_price) }}</strong><small>{{ $package->name }}</small></header>
            <input type="hidden" name="package_id" value="{{ $package->id }}"><input type="hidden" name="expected_package_price" value="{{ number_format($package->package_price, 2, '.', '') }}"><input type="hidden" name="method_id" value="{{ $method->id }}">
            <section class="vendor-payment-destination transfer-recipient" aria-label="بيانات التحويل"><h4>{{ $channel === 'instapay' ? 'حساب إنستا باي' : 'المحفظة الإلكترونية' }}</h4>
                @foreach(($method->method_fields ?? []) as $field)
                @if(trim((string)($field['input_data'] ?? '')) !== '')
                <div class="vendor-payment-recipient-row"><div><span>{{ ['wallet_number'=>'رقم المحفظة','instapay_account'=>'حساب إنستا باي','account_holder_name'=>'اسم صاحب الحساب','account_number'=>'الحساب / الرقم'][$field['input_name'] ?? ''] ?? ($field['input_name'] ?? '') }}</span><strong dir="auto" id="payment-recipient-{{ $package->id }}-{{ $method->id }}-{{ $loop->index }}">{{ $field['input_data'] }}</strong></div><button type="button" class="vendor-payment-copy" data-payment-copy="payment-recipient-{{ $package->id }}-{{ $method->id }}-{{ $loop->index }}">نسخ</button></div>
                @endif
                @endforeach
                <span class="vendor-payment-copy-status" aria-live="polite"></span>
            </section>
            <div class="transfer-proof-form">
            <h4 class="vendor-payment-form-title">حوّل المبلغ وأرفق صورة الإيصال</h4>
            @foreach(($method->method_informations ?? []) as $field)
                @if(!in_array(($field['customer_input'] ?? ''),['payment_screenshot','transfer_reference','transaction_reference','transaction_id','reference_number','reference'],true))<label class="d-block mb-3">{{ ($field['customer_input'] ?? '') === 'sender_name' ? 'اسم صاحب الحساب المُرسِل' : (($field['customer_input'] ?? '') === 'sender_wallet_or_phone' ? ($channel === 'instapay' ? 'حساب إنستا باي أو رقم الهاتف المُرسِل' : 'رقم المحفظة المُرسِلة') : ($field['customer_placeholder'] ?? $field['customer_input'] ?? '')) }}<input class="form-control" name="method_information[{{ $field['customer_input'] ?? '' }}]" maxlength="1000" @required($field['is_required'] ?? false)></label>@endif
            @endforeach
            <label class="vendor-payment-proof transfer-upload">صورة إيصال التحويل<small>JPG أو PNG أو WebP · حتى 5 ميجابايت</small><span class="vendor-proof-button">اختيار صورة التحويل</span><input class="sr-only" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required><span class="vendor-proof-file-name" data-payment-file-name aria-live="polite">لم يتم اختيار صورة</span></label>
            <label class="d-block mb-3">ملاحظة <span class="text-muted">(اختياري)</span><textarea class="form-control" name="payment_note" maxlength="1000" rows="2"></textarea></label>
            <button class="transfer-submit" @disabled($summary['pending_review'])>تم التحويل</button>
            </div>
        </form>
        @endforeach
    </div>
    @endforeach
</dialog>
