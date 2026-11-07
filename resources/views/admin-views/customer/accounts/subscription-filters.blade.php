@php
    $operationOptions=$tab==='actions'
        ? ['subscription_activation'=>'اشتراك','subscription_renew_package'=>'تجديد باقة','subscription_upgrade'=>'ترقية','subscription_downgrade'=>'تخفيض','subscription_renew_limit'=>'تجديد الحد الشرائي','subscription_auto_on'=>'تفعيل التجديد التلقائي','subscription_auto_off'=>'تعطيل التجديد التلقائي','subscription_cancel'=>'إلغاء عند نهاية المدة']
        : ['activation'=>'اشتراك','renew_package'=>'تجديد باقة','upgrade'=>'ترقية','downgrade'=>'تخفيض','renew_limit'=>'تجديد الحد الشرائي','increase'=>'زيادة الحد'];
@endphp
<form class="ca-search ca-history-filters" method="get">
    <input type="hidden" name="tab" value="{{ $tab }}">@if($customer)<input type="hidden" name="customer_id" value="{{ $customer->id }}">@endif
    <input type="hidden" name="q" value="{{ request('q') }}">
    <div><label for="ca-filter-operation">نوع العملية</label><select id="ca-filter-operation" class="form-control" name="operation"><option value="">كل العمليات</option>@foreach($operationOptions as $value=>$label)<option value="{{ $value }}" @selected(request('operation')===$value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ca-from">من تاريخ العملية</label><input id="ca-from" type="date" name="from" class="form-control" value="{{ request('from') }}"></div><div><label for="ca-to">إلى تاريخ العملية</label><input id="ca-to" type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
    <button class="btn btn-outline-primary"><i class="fi fi-rr-filter" aria-hidden="true"></i>تصفية السجل</button>
</form>
