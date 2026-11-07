@extends('layouts.vendor.app')
@section('title',isset($products) ? 'تقرير منتجاتك ومخزونك' : 'تقرير التوريد ومستحقات الشحن')
@section('content')
<div class="content container-fluid">
@if(isset($products))
<h2>تقرير منتجاتك ومخزونك</h2><p>البيانات والأسعار والمخزون الذي سجلته لمنتجاتك.</p>
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>مرجع الإعلان</th><th>منتجك</th><th>سعر التاجر</th><th>المخزون المسجل</th><th>حالة الإعلان</th></tr></thead><tbody>
@forelse($products as $product)<tr><td>{{ $product['reference'] }}</td><td>{{ $product['name'] }}</td><td>{{ $product['unit_price'] === null ? 'غير مسجل' : webCurrencyConverter($product['unit_price']) }}</td><td>{{ $product['stock'] ?? 'غير مسجل' }}</td><td>{{ $product['active'] ? 'نشط' : 'غير نشط' }}</td></tr>
@empty<tr><td colspan="5">لا توجد منتجات مسجلة.</td></tr>@endforelse
</tbody></table></div></div>{{ $pagination->links() }}
@else
<h2>تقرير التوريد ومستحقات الشحن</h2>
<p>القيم المعتمدة تخص توريد بضائعك والشحن المخصص لك. حالة الطلب لا تعني إتاحة المستحق للسحب؛ راجع موعد التسوية في الماليات.</p>
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>الطلب</th><th>الحالة</th><th>التوريد المعتمد</th><th>استحقاق الشحن</th><th>موعد استحقاق البضائع</th></tr></thead><tbody>
@forelse($orders as $row)<tr><td><a href="{{ route('vendor.orders.details',$row['id']) }}">#{{ $row['id'] }}</a></td><td>{{ ($row['fulfillment_eligibility']['pending_on_vendor_side'] ?? false) ? 'طلبات معلقة من جهة التاجر' : translate($row['order_status']) }}</td><td>{{ ($row['procurement_approved'] ?? false) ? webCurrencyConverter($row['vendor_procurement_amount']) : 'تفاصيل مقيدة' }}@if(isset($row['restricted_amount']))<br>الجزء المقيد: {{ webCurrencyConverter($row['restricted_amount']) }}@endif</td><td>{{ isset($row['shipping_seller_entitlement']) ? webCurrencyConverter($row['shipping_seller_entitlement']) : 'مقيد' }}</td><td>{{ $row['shipping_assignment']['sales_due_at'] ?? 'لم يحدد بعد' }}</td></tr>
@empty<tr><td colspan="5">لا توجد طلبات محالة.</td></tr>@endforelse
</tbody></table></div></div>{{ $pagination->links() }}
@endif
</div>
@endsection
