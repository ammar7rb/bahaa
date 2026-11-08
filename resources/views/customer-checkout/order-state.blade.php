@if($order->order_status === 'delivered')
    <div class="alert alert-success" role="status" dir="rtl">تم التسليم</div>
@endif
@if($order->customer_checkout_operation_id && in_array($order->order_status, ['pending','confirmed','processing'], true))
    @php($purchaseState = app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order))
    <div class="alert {{ $purchaseState['stage'] === 'ready_for_fulfillment' ? 'alert-success' : 'alert-warning' }} d-flex flex-wrap justify-content-between align-items-center gap-2" dir="rtl" role="status">
        <span>{{ $purchaseState['message'] }}</span>
        @include('customer-checkout.next-action')
        @if($purchaseState['can_cancel_for_package'] ?? false)
            <form method="post" action="{{ route('customer.order.package-cancellation', $order->id) }}" onsubmit="return confirm('تأكيد إلغاء الطلب وطلب رد المبلغ؟')">@csrf<button class="btn btn-outline-danger" type="submit">إلغاء الطلب وطلب رد المبلغ</button></form>
        @endif
    </div>
@endif
@if($order->payment_status === 'paid' && in_array($order->order_status, ['pending','confirmed','processing'], true) && !$order->shipment_reference && !$order->third_party_delivery_tracking_id && $order->customer_cancel_request_status !== 'pending' && !($purchaseState['can_cancel_for_package'] ?? false))
    <form method="post" action="{{ route('customer.order.paid-cancellation', $order->id) }}" class="card p-3 mb-3" dir="rtl" onsubmit="return confirm('تأكيد إلغاء الطلب المدفوع وإرساله لمراجعة الرصيد؟')">
        @csrf
        <label for="paid-cancel-reason-{{ $order->id }}">سبب إلغاء الطلب</label>
        <textarea id="paid-cancel-reason-{{ $order->id }}" name="reason" class="form-control mb-2" required minlength="5" maxlength="2000">{{ old('reason') }}</textarea>
        @error('reason')<div class="text-danger">{{ $message }}</div>@enderror
        <button class="btn btn-outline-danger" type="submit">إلغاء الطلب وطلب مراجعة الرصيد</button>
    </form>
@endif

@if($order->customer_checkout_operation_id && $order->payment_status !== 'paid' && $order->activation_status === 'offline_payment_rejected' && !in_array($order->order_status, ['canceled','failed','returned','delivered'], true))
    <section class="card p-3 mb-3" dir="rtl">
        <h3 class="h5">إعادة تقديم إثبات الدفع</h3>
        <p>أرسل الإثبات لنفس الطلب دون إنشاء طلب جديد.</p>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <form method="post" enctype="multipart/form-data" action="{{ route('customer.order.payment-retry', $order->id) }}" data-checkout-onboarding-form>
            @csrf<input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="transfer_schema" value="sender_v2">
            <label for="retry-amount-{{ $order->id }}">مبلغ الطلب</label><input id="retry-amount-{{ $order->id }}" class="form-control fs-16 mb-3" readonly value="{{ webCurrencyConverter($order->order_amount) }}">
            <label for="retry-phone-{{ $order->id }}">الرقم الذي حولت منه</label><input id="retry-phone-{{ $order->id }}" class="form-control fs-16 mb-3" name="sender_phone" type="tel" dir="ltr" required maxlength="40" value="{{ old('sender_phone') }}">
            <label for="retry-name-{{ $order->id }}">اسم المحفظة أو الحساب الخاص بك</label><input id="retry-name-{{ $order->id }}" class="form-control fs-16 mb-3" name="sender_name" required maxlength="150" value="{{ old('sender_name') }}">
            <label for="retry-proof-{{ $order->id }}">صورة إثبات الدفع</label><input class="form-control fs-16 mb-3" type="file" id="retry-proof-{{ $order->id }}" name="payment_proof" required accept="image/jpeg,image/png,image/webp">
            <button class="btn btn-primary" type="submit" data-loading-label="جارٍ إرسال الإثبات…">إرسال للمراجعة</button>
            <p class="small mt-2 mb-0" data-checkout-form-status role="status" aria-live="polite"></p>
        </form>
    </section>
@endif
@if(session('payment_retry_message'))<div class="alert alert-success" role="status">{{ session('payment_retry_message') }}</div>@endif
@once
@push('script')<script src="{{ asset('assets/front-end/js/checkout-onboarding.js') }}" defer></script>@endpush
@endonce

@if(session('customer_cancel_message'))<div class="alert alert-success" role="status">{{ session('customer_cancel_message') }}</div>@endif
@if($order->order_status === 'canceled' && $order->customer_cancel_request_status === 'pending' && !$order->customer_checkout_operation_id)
    <div class="alert alert-info" dir="rtl" role="status">أُلغِي الطلب، وتراجع الإدارة مبلغ الرد والرسوم قبل إضافته إلى رصيدك.</div>
@endif
@if($order->customer_checkout_operation_id && $order->order_status === 'canceled')
    @php($refundState = app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order)['package_refund'] ?? null)
    @if($refundState)<div class="alert alert-info">{{ $refundState['status'] === 'completed' ? 'تم تسجيل رد المبلغ.' : 'طلب رد المبلغ قيد مراجعة الإدارة؛ المدة من ١٥ إلى ٣٠ يومًا من تاريخ طلب الإلغاء.' }}</div>@endif
@endif
