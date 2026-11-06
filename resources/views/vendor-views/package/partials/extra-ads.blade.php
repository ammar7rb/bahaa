<section class="card card-body mt-4" aria-labelledby="extra-ads-title">
    <h2 id="extra-ads-title">مساحات إضافية للدورة الحالية</h2>
    <p>تزيد عدد المنتجات المتاحة دون تغيير باقتك. السعر ثابت ونهائي لكل مساحة، حتى لو تبقى وقت قصير. تنتهي الإضافات مع الاشتراك ولا تتجدد تلقائيًا. لا تشمل موضعًا مميزًا مضمونًا في الرئيسية أو البحث.</p>
    @if($extraSettings['enabled'] && (float) ($extraSettings['unit_price'] ?? 0) > 0 && $summary['active_subscription'])
        <p><strong>{{ webCurrencyConverter(amount: $extraSettings['unit_price']) }} للمساحة</strong> • الحد {{ $extraSettings['max_slots'] }} إضافة للدورة • تنتهي {{ $summary['active_subscription']->expires_at?->format('Y-m-d H:i') }}</p>
        <form method="post" data-extra-ad-purchase data-unit-price="{{ $extraSettings['unit_price'] }}" action="{{ route('vendor.packages.extra-ads.store') }}" class="row align-items-end g-3">
            @csrf
            <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="subscription_id" value="{{ $summary['active_subscription']->id }}">
            <input type="hidden" name="expected_unit_price" value="{{ $extraSettings['unit_price'] }}">
            <input type="hidden" name="payment_method" value="operating_balance">
            <div class="col-sm-4"><label for="extra-quantity" class="form-label">عدد المساحات</label><input id="extra-quantity" class="form-control" type="number" name="quantity" value="1" min="1" max="{{ $extraSettings['max_slots'] }}" required></div>
            <div class="col-sm-8"><p>الإجمالي النهائي: <output data-extra-total></output> EGP</p><button class="btn btn--primary" @disabled($operatingBalance < $extraSettings['unit_price'])>شراء من رصيد المدفوعات</button></div>
        </form>
        @foreach($offlinePaymentMethods->whereIn('payment_channel', ['wallet','instapay']) as $method)
            <details class="mt-3"><summary>شراء إضافات بالتحويل عبر {{ $method->method_name }}</summary>
                <form class="mt-3" method="post" enctype="multipart/form-data" data-extra-ad-purchase data-unit-price="{{ $extraSettings['unit_price'] }}" action="{{ route('vendor.packages.extra-ads.store') }}">
                    @csrf
                    <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="subscription_id" value="{{ $summary['active_subscription']->id }}">
                    <input type="hidden" name="expected_unit_price" value="{{ $extraSettings['unit_price'] }}">
                    <input type="hidden" name="payment_method" value="offline_payment">
                    <input type="hidden" name="method_id" value="{{ $method->id }}">
                    <label class="d-block mb-3">عدد المساحات<input class="form-control" type="number" name="quantity" value="1" min="1" max="{{ $extraSettings['max_slots'] }}" required></label>
                    @foreach(($method->method_fields ?? []) as $field)<p>{{ $field['input_name'] ?? '' }}: <strong>{{ $field['input_data'] ?? '' }}</strong></p>@endforeach
                    @foreach(($method->method_informations ?? []) as $field)
                        @if(!in_array(($field['customer_input'] ?? ''),['payment_screenshot','transfer_reference','transaction_reference','transaction_id','reference_number','reference'],true))<label class="d-block mb-3">{{ $field['customer_placeholder'] ?? $field['customer_input'] ?? '' }}<input class="form-control" name="method_information[{{ $field['customer_input'] ?? '' }}]" maxlength="1000" @required($field['is_required'] ?? false)></label>@endif
                    @endforeach
                    <label class="d-block mb-3">رقم عملية التحويل<input name="transfer_reference" class="form-control" maxlength="191" required></label><label class="d-block mb-3">إثبات التحويل<input type="file" class="form-control" name="payment_proof" accept="image/jpeg,image/png,image/webp" required></label>
                    <label class="d-block mb-3">ملاحظة<input class="form-control" name="payment_note" maxlength="1000"></label>
                    <p>الإجمالي = العدد × {{ webCurrencyConverter(amount: $extraSettings['unit_price']) }}. يبدأ الاستخدام بعد اعتماد التحويل وينتهي مع الاشتراك. إذا انتهى الاشتراك قبل الاعتماد يبقى التحويل معلقًا لتسوية يدوية.</p>
                    <p>الإجمالي النهائي: <output data-extra-total></output> EGP</p><button class="btn btn-outline-primary">إرسال التحويل للمراجعة</button>
                </form>
            </details>
        @endforeach
    @else
        <p class="text-muted">{{ (!$extraSettings['enabled'] || (float) ($extraSettings['unit_price'] ?? 0) <= 0) ? 'شراء الإضافات ينتظر تفعيل الإدارة وتحديد السعر.' : 'يلزم اشتراك مدفوع وسارٍ لشراء الإضافات.' }}</p>
    @endif
    <h3 class="mt-4">مشترياتي الإضافية</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>المرجع</th><th>العدد</th><th>السعر النهائي</th><th>الحالة</th><th>النهاية</th></tr></thead><tbody>
    @forelse($extraPurchases as $purchase)
        <tr><td>{{ $purchase->admin_reference }}</td><td>{{ $purchase->quantity }}</td><td>{{ webCurrencyConverter(amount: $purchase->total_price) }}</td><td>{{ ['paid' => $purchase->expires_at->isPast() ? 'منتهية' : 'مدفوعة', 'pending_review' => 'بانتظار تأكيد التحويل', 'manual_settlement' => 'معلقة لتسوية يدوية', 'payment_rejected' => 'التحويل غير مؤكد'][$purchase->status] ?? $purchase->status }}@if($purchase->review_note)<div>{{ $purchase->review_note }}</div>@endif</td><td>{{ $purchase->expires_at->format('Y-m-d H:i') }}</td></tr>
    @empty<tr><td colspan="5">لا توجد مشتريات إضافية.</td></tr>@endforelse
    </tbody></table></div>
    {{ $extraPurchases->links() }}
</section>
