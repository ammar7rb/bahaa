<section class="care-services"><div class="container">
    <div>@include('theme-views.partials._care-icon',['icon'=>'free_delivery_icon.svg'])<span>{{ translate('fast_shipping') }}</span></div>
    <div>@include('theme-views.partials._care-icon',['icon'=>'order_placed_icon_timeline.svg'])<span>{{ translate('track_order') }}</span></div>
    <div>@include('theme-views.partials._care-icon',['icon'=>'lock.svg'])<span>{{ translate('secure_payment') }}</span></div>
    <a href="{{ auth('customer')->check() ? route('account-tickets') : route('contacts') }}">@include('theme-views.partials._care-icon',['icon'=>'support_icon.svg'])<span>{{ translate('support_center') }}</span></a>
</div></section>
