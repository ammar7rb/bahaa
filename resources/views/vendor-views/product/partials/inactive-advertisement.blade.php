<div class="vendor-ad-inactive-notice" role="status">
    <span>{{ translate(app(\App\Services\VendorAdvertisingAccessService::class)->requiresSubscription((int) $product->user_id) ? 'phase5_inactive_ad' : 'vendor_advertisement_requires_active_package_slot') }}</span>
    <a href="{{ route('vendor.packages.index') }}">{{ translate('phase5_renew_package') }}</a>
    <a href="{{ route('vendor.products.list', ['type' => 'all', 'section' => 'slots']) }}">{{ translate('phase5_manage_spaces') }}</a>
</div>
