<dialog id="subscription-methods" class="subscription-dialog subscription-choice" dir="rtl" aria-labelledby="payment-choice-title" @if(session('subscription_message') === 'اختر طريقة دفع الاشتراك.') data-payment-auto-open @endif>
    <header class="payment-dialog-header"><div><h2 id="payment-choice-title">اختر طريقة الدفع</h2><p>المبلغ المطلوب: <strong>{{ number_format((float)$open->quoted_amount,2) }} {{ $open->currency_code === 'EGP' ? 'ج.م' : $open->currency_code }}</strong></p></div><button type="button" class="payment-dialog-close" data-payment-close aria-label="إغلاق">×</button></header>
    <div class="payment-choice-list">
        @if($purchase_wallet_enabled)<button type="button" class="payment-choice-option" data-payment-open="subscription-wallet">@include('customer-subscription.icon',['name'=>'wallet'])<span><strong>المحفظة</strong><small>الدفع من رصيدك المتاح</small></span>@include('customer-subscription.icon',['name'=>'arrow'])</button>@endif
        @foreach($offline_methods as $method)<button type="button" class="payment-choice-option {{ $method->payment_channel === 'instapay' ? 'choice-instapay' : 'choice-cash' }}" data-payment-open="subscription-transfer-{{ $method->id }}">@include('customer-subscription.icon',['name'=>$method->payment_channel === 'instapay' ? 'transfer' : 'wallet'])<span><strong>{{ $method->payment_channel === 'instapay' ? 'InstaPay' : 'محفظة إلكترونية' }}</strong><small>{{ $method->method_name }}</small></span>@include('customer-subscription.icon',['name'=>'arrow'])</button>@endforeach
        @if(!$purchase_wallet_enabled && $offline_methods->isEmpty())<p>لا توجد وسيلة دفع متاحة حاليًا. تواصل مع الإدارة.</p>@endif
    </div>
</dialog>
@if($purchase_wallet_enabled)
<dialog id="subscription-wallet" class="subscription-dialog subscription-choice" dir="rtl" aria-labelledby="payment-wallet-title">
    <header class="payment-dialog-header"><button type="button" class="payment-dialog-back" data-payment-open="subscription-methods">العودة</button><h2 id="payment-wallet-title">المحفظة</h2><button type="button" class="payment-dialog-close" data-payment-close aria-label="إغلاق">×</button></header>
    <div class="payment-wallet-content"><p>الرصيد المتاح: <strong>{{ webCurrencyConverter($purchase_balance) }}</strong></p><p>مبلغ الاشتراك: <strong>{{ number_format((float)$open->quoted_amount,2) }} {{ $open->currency_code }}</strong></p>
    @if($purchase_balance < (float)$open->wallet_debit_amount)<div class="monthly-error" role="alert">عذرًا، رصيد المحفظة لا يكفي. برجاء الإيداع فيها أو استخدام طريقة أخرى.</div><a class="monthly-button" href="{{ route('wallet') }}#purchase-balance">الإيداع في المحفظة</a><button type="button" class="payment-dialog-back" data-payment-open="subscription-methods">اختر طريقة أخرى</button>
    @else<form method="post" action="{{ route('customer.subscription.wallet',$open->id) }}">@csrf<button class="monthly-button" type="submit">تأكيد الدفع من المحفظة</button></form>@endif</div>
</dialog>
@endif
@foreach($offline_methods as $method)
<dialog id="subscription-transfer-{{ $method->id }}" class="subscription-dialog subscription-transfer-dialog" dir="rtl" aria-labelledby="payment-transfer-title-{{ $method->id }}">
    <header class="payment-dialog-header"><button type="button" class="payment-dialog-back" data-payment-open="subscription-methods">تغيير طريقة الدفع</button><h2 id="payment-transfer-title-{{ $method->id }}">{{ $method->payment_channel === 'instapay' ? 'InstaPay' : 'محفظة إلكترونية' }}</h2><button type="button" class="payment-dialog-close" data-payment-close aria-label="إغلاق">×</button></header>
    @include('customer-subscription.transfer-form')
</dialog>
@endforeach

