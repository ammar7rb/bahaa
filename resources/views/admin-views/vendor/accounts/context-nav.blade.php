@if(!empty($seller))
@php($financeView=(int)auth('admin')->user()->admin_role_id===1 || \App\Utils\Helpers::module_permission_check('vendor_financial_view') || \App\Utils\Helpers::module_permission_check('vendor_financial_manage'))
<nav class="va-context" aria-label="أقسام التاجر المحدد">
@foreach(['list'=>'قائمة التجار','payments'=>'مدفوعات','debts'=>'الديون','subscriptions'=>'الاشتراكات'] as $key=>$title)
@if($key==='list' || $financeView)
<a @class(['va-context-link','is-active'=>($page??'')===$key]) href="{{ route('admin.vendors.accounts.index',['page'=>$key,'seller_id'=>$seller->id]) }}">{{ $title }}</a>
@endif
@endforeach
</nav>
@endif
