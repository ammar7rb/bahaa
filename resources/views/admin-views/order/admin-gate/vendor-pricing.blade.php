<section class="card mb-3" aria-labelledby="vendor-pricing-title">
    <div class="card-header"><h3 id="vendor-pricing-title">سعر التوريد ومبلغ العميل المؤكد</h3></div>
    <div class="card-body">
        <p>اعتماد سعر التاجر يحدد التوريد فقط. مبلغ العميل وأسعار البيع المحفوظة لا تتغير. الشحن أدناه توزيع داخلي دون تحصيل إضافي.</p>
        <div class="table-responsive"><table class="table table-bordered">
            <thead><tr><th>المنتج</th><th>الكمية</th><th>سعر التاجر الأصلي للوحدة</th><th>سعر البيع للوحدة</th><th>خصم السطر</th><th>خصم الطلب المخصص</th><th>الضريبة</th><th>صافي البضائع دون ضريبة</th><th>التوريد</th></tr></thead>
            <tbody>@foreach($pricing['lines'] as $line)<tr>
                <td>{{ $line['name'] }}</td><td>{{ $line['qty'] }}</td>
                <td>{{ $line['vendor_unit_price'] === null ? 'غير محفوظ' : webCurrencyConverter($line['vendor_unit_price']) }}</td>
                <td>{{ webCurrencyConverter($line['selling_unit_price']) }}</td><td>{{ webCurrencyConverter($line['product_discount']) }}</td>
                <td>{{ webCurrencyConverter($line['allocated_order_discount']) }}</td><td>{{ webCurrencyConverter($line['tax']) }}</td>
                <td>{{ webCurrencyConverter($line['sale']) }}</td><td>{{ $line['procurement'] === null ? 'غير محفوظ' : webCurrencyConverter($line['procurement']) }}</td>
            </tr>@endforeach</tbody>
        </table></div>
        <p>مبلغ العميل: <strong>{{ webCurrencyConverter($pricing['customer_order_amount']) }}</strong> — شحن العميل عند الشراء: {{ webCurrencyConverter($pricing['customer_shipping']) }}</p>
        @if($pricing['complete'])
            <p>توريد البضائع: {{ webCurrencyConverter($pricing['procurement_amount']) }} — هامش البضائع الإجمالي: {{ webCurrencyConverter($pricing['margin']) }}</p>
        @else
            <p class="text-warning">هذا الطلب لا يحتوي سعر توريد أصلي محفوظًا لكل سطر. لا يمكن اعتماد سعر من الكتالوج الحالي أو حساب هامش موثوق.</p>
        @endif
        @if($approvedPricing)
            <p class="text-success">تم اعتماد سعر التاجر بواسطة المسؤول #{{ $approvedPricing->admin_id }} في {{ $approvedPricing->approved_at }}. القيمة المعتمدة: {{ webCurrencyConverter($approvedPricing->procurement_amount) }}. لا توجد موافقة سابقة؛ سعر البيع محفوظ دون تغيير.</p>
        @elseif(empty($unifiedReview))
            <form action="{{ route('admin.orders.admin-gate.vendor-price', $order->id) }}" method="post">@csrf
                <button class="btn btn-primary" @disabled(!$pricing['complete'] || $order->payment_status !== 'paid')>اعتماد سعر التاجر / Use Vendor Price</button>
            </form>
        @endif
    </div>
</section>

@if(\Illuminate\Support\Facades\Schema::hasTable('order_financial_events'))
@php($financialEvents = \Illuminate\Support\Facades\DB::table('order_financial_events')->where('order_id',$order->id)->orderBy('id')->get())
@if($financialEvents->isNotEmpty())
<section class="card mb-3" aria-labelledby="financial-events-title">
    <div class="card-header"><h3 id="financial-events-title">{{ __('company-financials.audit_title') }}</h3></div>
    <div class="card-body"><p>{{ __('company-financials.audit_note') }}</p><div class="table-responsive"><table class="table table-bordered">
        <thead><tr><th>{{ __('company-financials.columns.date') }}</th><th>{{ __('company-financials.audit_event') }}</th><th>{{ __('company-financials.audit_actor') }}</th><th>{{ __('company-financials.columns.reference') }}</th></tr></thead>
        <tbody>@foreach($financialEvents as $financialEvent)<tr><td><bdi>{{ $financialEvent->created_at }}</bdi></td><td>{{ __('company-financials.events.'.$financialEvent->event_type) }}</td><td>{{ $financialEvent->admin_id ? '#'.$financialEvent->admin_id : __('company-financials.audit_system') }}</td><td><bdi>{{ $financialEvent->source_type }} #{{ $financialEvent->source_id }}</bdi></td></tr>@endforeach</tbody>
    </table></div>@if(\App\Utils\Helpers::module_permission_check('reports'))<a href="{{ route('admin.report.financials.company',['tab'=>'sales','q'=>(string)$order->id]) }}">{{ __('financials.company') }}</a>@endif</div>
</section>
@endif
@endif
