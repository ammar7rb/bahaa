<section class="ca-panel"><h2>مرتجعات المشتريات المعتمدة</h2><p>قيمة الرد مأخوذة من المرتجع الأصلي. لا تُستوفى الديون من مبالغ المرتجعات.</p><div class="ca-table" role="region" tabindex="0" aria-label="مرتجعات المشتريات"><table><thead><tr><th>المرتجع والطلب</th><th>المبلغ</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>
@forelse($returns as $return)<tr><td>#{{ $return->id }} · الطلب #{{ $return->order_id }}</td><td>{{ $money($return->amount) }}</td><td>{{ ['approved'=>'معتمد للرد','refunded'=>'تم الرد','pending'=>'قيد المراجعة','rejected'=>'مرفوض'][$return->status]??$return->status }}</td><td>
@if(\App\Utils\Helpers::module_permission_check('orders'))<a href="{{ route('admin.refund-section.refund.details',$return->id) }}">المرتجع الأصلي</a>
@if($return->status==='approved' && $can('refunds_manage'))<button class="btn btn-sm btn-outline-primary" type="button" data-ca-open="ca-return-{{ $return->id }}">إضافة قيمة المرتجع</button>@endif
@endif</td></tr>
@empty<tr><td colspan="4" class="ca-empty">لا توجد مرتجعات مشتريات لهذا العميل.</td></tr>@endforelse
</tbody></table></div>{{ $returns->links() }}</section>
@foreach($returns as $return)
@if($return->status==='approved' && $can('refunds_manage') && \App\Utils\Helpers::module_permission_check('orders'))
<dialog id="ca-return-{{ $return->id }}" class="ca-dialog" aria-labelledby="ca-return-title-{{ $return->id }}"><form method="post" action="{{ route('admin.customer.accounts.return-credit',$return->id) }}" data-ca-financial>@csrf<h2 id="ca-return-title-{{ $return->id }}">إضافة قيمة مرتجع معتمد</h2><p>عميل <bdi>{{ $customer->administrative_reference }}</bdi> · الطلب #{{ $return->order_id }} · المرتجع #{{ $return->id }} · المبلغ {{ $money($return->amount) }}</p>@include('admin-views.customer.accounts.confirmation')<footer><button class="btn btn-secondary" type="button" data-ca-close>رجوع</button><button class="btn btn-primary">تأكيد الرد إلى المحفظة</button></footer></form></dialog>
@endif
@endforeach
