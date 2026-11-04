<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>مستند توريد #{{ $row['id'] }}</title></head><body>
<h2>مستند توريد التاجر — طلب #{{ $row['id'] }}</h2>
<p>الحالة: {{ translate($row['order_status']) }}</p>
<table width="100%" border="1" cellpadding="8"><thead><tr><th>منتج التاجر</th><th>الكمية</th><th>سعر التوريد للوحدة</th></tr></thead><tbody>
@foreach($row['order_details'] as $line)<tr><td>{{ $line['product_details']['name'] }}</td><td>{{ $line['qty'] }}</td><td>{{ $line['price_available'] ? number_format($line['price'], 2) : 'غير محفوظ' }}</td></tr>@endforeach
</tbody></table>
<p>التوريد المعتمد: {{ $row['procurement_approved'] ? number_format($row['vendor_procurement_amount'], 2) : 'غير متاح' }}</p>
<p>استحقاق الشحن: {{ number_format($row['shipping_seller_entitlement'], 2) }}</p>
</body></html>
