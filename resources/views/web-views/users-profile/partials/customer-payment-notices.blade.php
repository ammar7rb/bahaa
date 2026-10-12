@if(auth('customer')->check() && \Illuminate\Support\Facades\Schema::hasColumn('notifications','customer_event_key'))
@foreach(\App\Models\Notification::active()->where('customer_id',auth('customer')->id())->latest('id')->limit(2)->get() as $notice)
<div class="container py-2" role="status" dir="rtl"><span>{{ $notice->description }}</span> <a href="{{ route('customer.subscription.index') }}">الباقات والحد الائتماني</a> · <a href="{{ route('account-tickets') }}">التواصل مع الدعم</a></div>
@endforeach
@endif
