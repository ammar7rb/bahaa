@if($requiresSubscription ?? $sellerOverview['subscription']['publishing_requires_subscription'] ?? false)
<div class="vendor-subscription-banner" role="status">
    <strong>{{ translate('vendor_subscription_required_to_publish') }}</strong>
    <a href="{{ route('vendor.packages.index') }}">{{ translate('vendor_subscribe_now') }}</a>
</div>
@endif
