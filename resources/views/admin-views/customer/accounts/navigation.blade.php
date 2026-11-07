<nav class="ca-navigation" aria-label="إدارة العملاء">
@foreach(['list'=>'قائمة العملاء','payments'=>'مدفوعات','debts'=>'الديون','subscriptions'=>'الاشتراكات'] as $destination=>$label)
<a href="{{ route('admin.customer.accounts.index',array_filter(['page'=>$destination,'customer_id'=>$customer?->id])) }}" @if($page===$destination)aria-current="page"@endif>{{ $label }}</a>
@endforeach
</nav>
