@if(isset($customer) && $customer)
<nav class="ca-profile-navigation" aria-label="أقسام ملف العميل">
@foreach(['profile'=>'إدارة الحساب','payments'=>'المحفظة والشحن','debts'=>'الديون','subscriptions'=>'الاشتراكات'] as $destination=>$label)
@if($destination==='profile' || \App\Services\CustomerAccountAccess::allows('financial_view'))
<a class="btn btn-outline-primary" @if(($page??'profile')===$destination)aria-current="page"@endif href="{{ $destination==='profile'?route('admin.customer.view',$customer->id):route('admin.customer.accounts.index',['page'=>$destination,'customer_id'=>$customer->id]) }}">{{ $label }}</a>
@endif
@endforeach
</nav>
@endif
