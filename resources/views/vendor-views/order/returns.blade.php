@extends('layouts.vendor.app')
@section('title','متابعة المرتجعات')
@section('content')
<div class="content container-fluid"><h2>متابعة المرتجعات</h2>
<p>قرارات المرتجعات وتعديل المستحقات تُدار بواسطة الإدارة. يمكنك متابعة حالة التنفيذ من تفاصيل الطلب.</p>
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>طلب الإرجاع</th><th>الطلب</th><th>منتجك</th><th>الحالة</th></tr></thead><tbody>
@forelse($returns as $row)<tr><td>{{ $row['id'] }}</td><td><a href="{{ route('vendor.orders.details',$row['order_id']) }}">#{{ $row['order_id'] }}</a></td><td>{{ $row['product']['name'] }}</td><td>{{ translate($row['status']) }}</td></tr>
@empty<tr><td colspan="4">لا توجد طلبات إرجاع.</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
