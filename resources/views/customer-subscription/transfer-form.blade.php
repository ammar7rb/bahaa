@php
    $transferId = $method->id.'-'.\Illuminate\Support\Str::random(6);
    $instapay = $method->payment_channel === 'instapay';
    $transferMode = $transferMode ?? 'subscription';
    $isDeposit = $transferMode === 'deposit';
    $nested = $nested ?? false;
    $amount = $transferAmount ?? (float)$open->quoted_amount;
    $digits = abs(round($amount,3) - round($amount,2)) > 0.00001 ? 3 : 2;
    $currency = $transferCurrency ?? $open->currency_code;
    $action = $transferAction ?? route('customer.subscription.offline',$open->id);
@endphp
<div class="transfer-experience {{ $instapay ? 'transfer-instapay' : 'transfer-cash' }}" data-transfer>
    <header class="transfer-hero"><span class="transfer-provider">{{ $instapay ? 'InstaPay' : $method->method_name }}</span><h3>{{ $instapay ? 'تحويل سريع، وخطوات واضحة' : 'ادفع من محفظتك الإلكترونية' }}</h3><p>{{ $isDeposit ? 'حدد مبلغ الإيداع، ثم حوّله إلى بيانات الاستقبال التالية.' : 'حوّل المبلغ المطلوب إلى بيانات الاستقبال التالية.' }}</p>@unless($isDeposit)<strong class="transfer-amount"><bdi>{{ number_format($amount,$digits) }}</bdi> <span>{{ $currency === 'EGP' ? 'ج.م' : $currency }}</span></strong>@endunless<small>{{ $transferMode === 'subscription' ? 'الاشتراك الشهري · 30 يومًا من اعتماد الدفع' : ($isDeposit ? 'إيداع في المحفظة' : 'المبلغ الإجمالي المطلوب سداده') }}</small></header>
    <div class="transfer-content">
        @include('customer-subscription.transfer-recipient')
        <button type="button" class="transfer-guide-button" data-show-proof>قم بالتحويل وأرفق صورة لإثبات الدفع <span aria-hidden="true">↓</span></button>
        @if(!$nested)<form method="post" action="{{ $action }}" enctype="multipart/form-data" class="transfer-proof-form">@csrf @else<div class="transfer-proof-form">@endif
            <input type="hidden" name="transfer_schema" value="sender_v2">
            @if($isDeposit)<input type="hidden" name="wallet_type" value="purchase"><input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">@endif
            <input type="hidden" name="method_id" value="{{ $method->id }}">
            <h4>تفاصيل التحويل</h4><p class="transfer-form-intro">بعد إتمام التحويل، أضف بيانات المرسل والإيصال.</p>
            <label for="amount-{{ $transferId }}">مبلغ التحويل ({{ $currency }})</label>
            <input id="amount-{{ $transferId }}" name="{{ $isDeposit ? 'amount' : 'transferred_amount' }}" type="number" min="0.01" step="{{ $isDeposit ? '0.01' : '0.001' }}" required value="{{ $isDeposit ? old('amount') : number_format($amount,$digits,'.','') }}" inputmode="decimal" @readonly(!$isDeposit)>
            <div class="transfer-input-grid">
                <div><label for="sender-phone-{{ $transferId }}">الرقم الذي حولت منه</label><input id="sender-phone-{{ $transferId }}" name="sender_phone" type="tel" dir="ltr" required maxlength="40" autocomplete="tel" value="{{ old('sender_phone') }}" placeholder="أضف رقمك للمتابعة"></div>
                <div><label for="sender-name-{{ $transferId }}">{{ $instapay ? 'اسم الحساب الخاص بك' : 'اسم المحفظة الخاصة بك' }}</label><input id="sender-name-{{ $transferId }}" name="sender_name" required maxlength="150" value="{{ old('sender_name') }}" autocomplete="name"></div>
            </div>
            <label class="transfer-upload" for="proof-{{ $transferId }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5"/></svg><strong>أرفق صورة إيصال التحويل</strong><span>اضغط لاختيار الصورة · JPG أو PNG أو WebP حتى 5 ميجابايت</span><input id="proof-{{ $transferId }}" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp" required data-proof-input><span data-proof-name aria-live="polite">لم يتم اختيار صورة بعد</span><img class="transfer-proof-preview" alt="معاينة إثبات التحويل" hidden></label>
            <label for="note-{{ $transferId }}">ملاحظة للإدارة (اختياري)</label><textarea id="note-{{ $transferId }}" name="payment_note" maxlength="1000" rows="2"></textarea>
            <button class="transfer-submit" type="submit" @disabled(empty($method->method_fields))>إرسال إثبات الدفع للمراجعة ←</button><small class="transfer-review-note">{{ $transferMode === 'subscription' ? 'يبدأ الاشتراك بعد مراجعة الإدارة واعتماد الدفع.' : 'الدفع قيد المراجعة بعد إرسال الإيصال، ويتم تأكيده بواسطة الإدارة.' }}</small>
        @if(!$nested)</form>@else</div>@endif
    </div>
</div>

