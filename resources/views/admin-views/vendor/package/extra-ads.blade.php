@extends('layouts.admin.app')
@section('title', 'المساحات الإضافية للبائعين')
@section('content')
<div class="content container-fluid">
    <h1>المساحات الإضافية للبائعين</h1>
    <p>سعر نهائي بالجنيه، ومدة مرتبطة بنهاية اشتراك البائع. تغيير الإعدادات للمشتريات الجديدة فقط.</p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="card card-body mb-4"><h2>إعدادات الشراء</h2>
        <form method="post" action="{{ route('admin.vendors.extra-ads.settings') }}" class="row g-3 align-items-end">@csrf
            <div class="col-md-4"><label for="extra-price" class="form-label">سعر المساحة النهائي EGP</label><input id="extra-price" type="number" step="0.01" min="0.01" max="999999999.99" name="unit_price" class="form-control" value="{{ old('unit_price', $settings['unit_price']) }}" required></div>
            <div class="col-md-4"><label for="extra-max" class="form-label">أقصى إضافات لكل دورة</label><input id="extra-max" type="number" min="1" max="10000" name="max_slots" class="form-control" value="{{ old('max_slots', $settings['max_slots']) }}" required></div>
            <div class="col-md-4"><label class="d-block mb-3"><input type="checkbox" name="enabled" value="1" @checked($settings['enabled'])> تفعيل شراء الإضافات</label><button class="btn btn-primary">حفظ الإعدادات</button></div>
        </form>
    </section>
    <section class="card card-body"><h2>المشتريات ومراجعة التحويلات</h2>
        <p>المراجعة هنا لتأكيد وصول المال. تتم مراجعة المحتوى من مراجعة المنتجات؛ رفض المنتج يحرر مساحته ويحفظ بياناته. إذا انتهى الاشتراك قبل الاعتماد يبقى التحويل معلقًا لتسوية يدوية دون إضافة رصيد أو مساحة.</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>المرجع والبائع</th><th>العدد والسعر</th><th>الحالة والنهاية</th><th>الدفع والمراجعة</th></tr></thead><tbody>
        @forelse($purchases as $purchase)
            <tr><td>{{ $purchase->admin_reference }}<div>#{{ $purchase->seller_id }} {{ $purchase->seller?->f_name }} {{ $purchase->seller?->l_name }}</div></td><td>{{ $purchase->quantity }} × {{ $purchase->unit_price }} EGP<div>الإجمالي {{ $purchase->total_price }} EGP</div></td>
            <td>{{ ['paid'=>'مدفوعة', 'pending_review'=>'بانتظار تأكيد التحويل', 'manual_settlement'=>'معلقة لتسوية يدوية', 'payment_rejected'=>'التحويل غير مؤكد'][$purchase->status] ?? $purchase->status }}<div>{{ $purchase->expires_at->format('Y-m-d H:i') }}</div><p>{{ $purchase->review_note }}</p></td>
            <td>{{ $purchase->payment_method === 'operating_balance' ? 'رصيد المدفوعات' : ($purchase->payment_details['method_name'] ?? 'تحويل خارجي') }}
            @if($purchase->payment_details)
                @foreach(($purchase->payment_details['method_information'] ?? []) as $name => $value)<div>{{ $name }}: {{ $value }}</div>@endforeach
                @if($purchase->payment_details['payment_proof'] ?? null)
                    @php($proof = $purchase->payment_details['payment_proof'])
                    @php($storedProof = storageLink('seller-package/payment-proof', $proof['image_name'], $proof['storage'] ?? 'public'))
                    @php($proofUrl = is_array($storedProof) ? ($storedProof['path'] ?? '') : $storedProof)
                    <a href="{{ $proofUrl }}" target="_blank" rel="noopener">عرض إثبات التحويل</a>@endif
                <p>{{ $purchase->payment_details['payment_note'] ?? '' }}</p>
                @if($purchase->payment_details['review_reference'] ?? null)<p>رقم التحويل للمراجعة: {{ $purchase->payment_details['review_reference'] }}</p>@endif
            @endif
            @if($purchase->status === 'pending_review' && $purchase->payment_method === 'offline_payment')
                <form method="post" action="{{ route('admin.vendors.extra-ads.approve', $purchase->id) }}" class="mb-3">@csrf
                    <label class="d-block">رقم التحويل المؤكد<input name="payment_reference" class="form-control" maxlength="191" required></label>
                    <label class="d-block">ملاحظة المراجعة<input name="review_note" class="form-control" maxlength="1000"></label>
                    <button class="btn btn-primary mt-2">تأكيد وصول التحويل</button>
                </form>
                <details><summary>التحويل غير مؤكد الوصول</summary><form method="post" action="{{ route('admin.vendors.extra-ads.reject', $purchase->id) }}">@csrf<label class="d-block">سبب عدم التأكيد<textarea name="review_note" class="form-control" minlength="3" maxlength="1000" required></textarea></label><button class="btn btn-outline-danger mt-2">تسجيل عدم وصول المال</button></form></details>
            @endif</td></tr>
        @empty<tr><td colspan="4">لا توجد مشتريات إضافية.</td></tr>@endforelse
        </tbody></table></div>{{ $purchases->links() }}
    </section>
</div>
@endsection
