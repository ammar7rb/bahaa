@if($order->customer_checkout_operation_id && in_array($order->order_status, ['pending','confirmed','processing'], true))
    @php($purchaseState = app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order))
    <div class="alert {{ $order->customer_eligible_at ? 'alert-success' : 'alert-warning' }} d-flex flex-wrap justify-content-between align-items-center gap-2" dir="rtl" role="status">
        <span>{{ $purchaseState['message'] }}</span>
        @if(in_array('account_activation', $purchaseState['pending_reasons'], true))
            @php($activationInfo = app(\App\Services\CustomerActivationService::class)->payload(auth('customer')->user()))
            <a class="btn btn-outline-dark btn-sm" href="{{ route('support-ticket.index', array_filter(['id' => $activationInfo['ticket_id'] ?? null])) }}">تواصل لتفعيل الحساب</a>
        @endif
        @if(in_array('monthly_subscription', $purchaseState['pending_reasons'], true))
            <a class="btn btn-primary btn-sm" href="{{ route('customer.subscription.index') }}">الاشتراك الشهري</a>
        @endif
    </div>
@endif

@if($order->customer_checkout_operation_id && $order->payment_status !== 'paid' && $order->activation_status === 'offline_payment_rejected' && !in_array($order->order_status, ['canceled','failed','returned','delivered'], true))
    <section class="card p-3 mb-3" dir="rtl">
        <h3 class="h5">إعادة تقديم إثبات الدفع</h3>
        <p>أرسل الإثبات لنفس الطلب دون إنشاء طلب جديد.</p>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <form method="post" enctype="multipart/form-data" action="{{ route('customer.order.payment-retry', $order->id) }}">
            @csrf<input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <label for="retry-reference-{{ $order->id }}">مرجع التحويل</label><input class="form-control mb-3" id="retry-reference-{{ $order->id }}" name="payment_reference" required maxlength="191" dir="ltr">
            <label for="retry-proof-{{ $order->id }}">صورة إثبات الدفع</label><input class="form-control mb-3" type="file" id="retry-proof-{{ $order->id }}" name="payment_proof" required accept="image/jpeg,image/png,image/webp">
            <button class="btn btn-primary" type="submit">إرسال للمراجعة</button>
        </form>
    </section>
@endif
