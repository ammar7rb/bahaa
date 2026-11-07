@if($order->seller_is === 'seller' && app(\App\Services\VendorFulfillmentEligibilityService::class)->available())
@php($fulfillmentRequests = app(\App\Services\VendorFulfillmentEligibilityService::class)->requests($order))
<section class="card mb-3" dir="rtl"><div class="card-header"><h3>أهلية طلب تنفيذ التاجر</h3></div><div class="card-body">
<p>التاجر المصدر: #{{ $order->seller_id }} — {{ $order->seller?->f_name }} {{ $order->seller?->l_name }}</p>
<div class="table-responsive"><table class="table"><thead><tr><th>المنتج</th><th>موضعه الحالي</th><th>سعر التاجر الأصلي</th><th>سعر البيع للعميل</th></tr></thead><tbody>
@foreach($order->details as $eligibilityLine)<tr><td>#{{ $eligibilityLine->product_id }}</td><td>{{ app(\App\Services\VendorFulfillmentEligibilityService::class)->slotFor((int)$order->seller_id, (int)$eligibilityLine->product_id) ? 'داخل مساحة إعلانية' : 'خارج المساحات الإعلانية' }}</td><td>{{ $eligibilityLine->getRawOriginal('vendor_unit_price_snapshot') ?? 'غير محفوظ' }}</td><td>{{ $eligibilityLine->getRawOriginal('price') }}</td></tr>@endforeach
</tbody></table></div>
@forelse($fulfillmentRequests as $fulfillmentRequest)<div class="border rounded p-3 mb-2"><strong>طلب تنفيذ #{{ $fulfillmentRequest->id }} — {{ $fulfillmentRequest->portion === 'advertising' ? 'جزء داخل المساحات وقت التحويل' : 'جزء خارج المساحات وقت التحويل' }}</strong><br>الحالة: {{ ['locked'=>'مقفل','pending_vendor'=>'طلبات معلقة من جهة التاجر','available'=>'متاح','unlocked'=>'تم فك القفل'][$fulfillmentRequest->status] ?? $fulfillmentRequest->status }}<br>وقت التحويل: {{ $fulfillmentRequest->forwarded_at }}<br>موعد تصنيف التعليق: {{ $fulfillmentRequest->pending_due_at ?? 'لا ينطبق' }}<br>وقت التصنيف: {{ $fulfillmentRequest->pending_at ?? '—' }}<br>مستحق المنتجات: {{ $fulfillmentRequest->vendor_amount }} — استحقاق الشحن: {{ $fulfillmentRequest->shipping_amount }}</div>
@empty<p class="text-muted">لم يُحوّل طلب تنفيذ وفق الأهلية الجديدة بعد. يبدأ عدّ 24 ساعة عند تحويل الإدارة فقط.</p>@endforelse
@include('admin-views.order.partials._vendor-fulfillment-history')
</div></section>
@endif
