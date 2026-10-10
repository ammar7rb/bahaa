@extends('layouts.admin.app')
@section('title','الاشتراكات الشهرية')
@section('content')
<div class="content container-fluid" dir="rtl">
    <h1 class="h2 mb-4">الاشتراكات الشهرية</h1>
    @if(session('subscription_message'))<div class="alert alert-success" role="status">{{ session('subscription_message') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if(\App\Utils\Helpers::module_permission_check('business_settings'))
    <div class="card mb-4"><div class="card-body"><h2 class="h4">أسعار الاشتراك</h2><p class="text-muted">كل دفعة معتمدة تبدأ 30 يومًا جديدة. تعديل السعر لا يغير دفعة بدأت بالفعل.</p>
        <form method="post" action="{{ route('admin.customer.monthly-subscriptions.pricing') }}">@csrf
            <div class="row"><div class="col-md-6"><label for="customer-price">سعر اشتراك العميل ({{ getCurrencyCode(type:'default') }})</label><input id="customer-price" class="form-control" type="number" step="0.001" min="0.001" max="999999999" name="customer_price" value="{{ old('customer_price',$pricing['customer_price']) }}"><small class="text-muted">اتركه فارغًا لإيقاف بدء دفعات جديدة.</small></div>
            <div class="col-md-6"><label for="vendor-price">سعر اشتراك التاجر المستقبلي ({{ getCurrencyCode(type:'default') }})</label><input id="vendor-price" class="form-control" type="number" step="0.001" min="0.001" max="999999999" name="vendor_price" value="{{ old('vendor_price',$pricing['vendor_price']) }}"><small class="text-muted">محفوظ للمرحلة القادمة؛ لا يفرض اشتراكًا على التجار.</small></div></div>
            <button class="btn btn--primary mt-3">حفظ الأسعار</button>
        </form>
    </div></div>
    @endif
    <div class="card"><div class="card-body"><h2 class="h4">مدفوعات اشتراك العملاء</h2>
        <form method="get" class="d-flex gap-2 mb-3"><select class="form-control" name="status" aria-label="حالة الدفع"><option value="">كل الحالات</option>@foreach(['pending_review'=>'قيد المراجعة','approved'=>'معتمد','rejected'=>'مرفوض','draft'=>'لم يقدم الدفع'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select><button class="btn btn-outline-primary">تصفية</button></form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>العميل</th><th>الحساب والاشتراك</th><th>الدفع</th><th>المراجعة</th></tr></thead><tbody>
        @forelse($payments as $payment)
        @php($state = app(\App\Services\CustomerMonthlySubscriptionService::class)->status($payment->customer_id))
        <tr><td>{{ $payment->customer?->f_name }} {{ $payment->customer?->l_name }}<br><bdi>{{ $payment->customer?->canonical_phone ?: $payment->customer?->phone }}</bdi><br><small>{{ ['checkout_first'=>'تسجيل من الدفع','standard'=>'تسجيل معتاد','legacy'=>'حساب سابق'][$payment->customer?->onboarding_journey ?? 'legacy'] ?? 'حساب سابق' }}</small></td>
        <td>{{ $payment->customer?->activation_status === 'active' ? 'الحساب مفعّل' : 'بانتظار التفعيل' }}<br>{{ $payment->customer?->is_active ? 'دون تجميد إداري' : 'مجمّد إداريًا' }}<br>{{ $state['active'] ? 'اشتراك فعال · '.$state['remaining_days'].' يومًا' : 'لا يوجد اشتراك فعال' }}@if($state['expires_at'])<br><bdi>{{ \Carbon\Carbon::parse($state['expires_at'])->format('Y/m/d H:i') }}</bdi>@endif</td>
        <td>#{{ $payment->id }} · {{ $payment->quoted_amount }} {{ $payment->currency_code }}<br>{{ $payment->method_name ?: $payment->payment_method }}<br><bdi>{{ $payment->payment_reference }}</bdi><br>{{ ['draft'=>'لم يُقدم الدفع','pending_review'=>'الدفع قيد المراجعة','approved'=>'معتمد','rejected'=>'مرفوض'][$payment->status] }}@if($payment->payment_proof)<br><a href="{{ route('admin.customer.monthly-subscriptions.proof',$payment->id) }}" target="_blank" rel="noopener">عرض إثبات الدفع</a>@endif
        @foreach($payment->method_information ?? [] as $name=>$value)<br><small>{{ ['sender_name'=>'اسم المحفظة أو الحساب المرسل','sender_phone'=>'الرقم الذي تم التحويل منه','transferred_amount'=>'مبلغ التحويل'][$name] ?? $name }}: {{ $value }}</small>@endforeach @if($payment->payment_note)<p>{{ $payment->payment_note }}</p>@endif</td>
        <td>@if($payment->status === 'pending_review')<form method="post" action="{{ route('admin.customer.monthly-subscriptions.review',$payment->id) }}">@csrf<label class="sr-only" for="review-{{ $payment->id }}">ملاحظة المراجعة</label><input id="review-{{ $payment->id }}" name="review_note" class="form-control mb-2" maxlength="1000" placeholder="سبب الرفض مطلوب عند الرفض"><button class="btn btn-sm btn-success" name="decision" value="approve">اعتماد الدفع</button> <button class="btn btn-sm btn-outline-danger" name="decision" value="reject">رفض</button></form>@else{{ $payment->review_note }}@endif</td></tr>
        @empty<tr><td colspan="4" class="text-center text-muted">لا توجد دفعات اشتراك بعد.</td></tr>@endforelse
        </tbody></table></div>{{ $payments->links() }}
    </div></div>
</div>
@endsection
