@if(!request()->is('vendor', 'vendor/*', 'seller', 'seller/*') && !request()->routeIs('vendor.*', 'seller.*'))
@if(auth('customer')->check() && \Illuminate\Support\Facades\Schema::hasColumn('notifications','customer_event_key'))
@foreach(\App\Models\Notification::active()->where('customer_id',auth('customer')->id())->latest('id')->limit(2)->get() as $notice)
<div class="container py-2" role="status" dir="rtl"><span>{{ $notice->description }}</span> <a href="{{ route('customer.notifications.index') }}">عرض الإشعار</a></div>
@endforeach
@endif

@endif
