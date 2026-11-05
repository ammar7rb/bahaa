@if(!request()->is('vendor', 'vendor/*', 'seller', 'seller/*') && !request()->routeIs('vendor.*', 'seller.*'))
@if(auth('customer')->check() && \Illuminate\Support\Facades\Schema::hasColumn('notifications','customer_event_key'))
@foreach(\App\Models\Notification::active()->where('customer_id',auth('customer')->id())->where(function ($query) { $query->whereNull('customer_event_key')->orWhere('customer_event_key', 'not like', 'package-wallet-%'); })->latest('id')->limit(2)->get() as $notice)
<div class="container py-2" role="status" dir="rtl"><span>{{ $notice->description }}</span> <a href="{{ route('customer.notifications.index') }}">عرض الإشعار</a></div>
@endforeach
@endif

@endif
