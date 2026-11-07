@foreach([['customer.notifications.index','bell','الإشعارات'],['customer.messages.index','message','الرسائل']] as [$linkRoute,$iconName,$linkLabel])
@if($defaultSidebar ?? false)
<div class="widget-title"><a href="{{ route($linkRoute) }}" class="{{ request()->routeIs($linkRoute) ? 'active-menu' : '' }}">@include('customer-subscription.icon',['name'=>$iconName])<span class="aside-link">{{ $linkLabel }}</span></a></div>
@else
<li class="{{ request()->routeIs($linkRoute) ? 'active' : '' }}"><a href="{{ route($linkRoute) }}" @if(request()->routeIs($linkRoute)) aria-current="page" @endif>@include('customer-subscription.icon',['name'=>$iconName])<span>{{ $linkLabel }}</span></a></li>
@endif
@endforeach
