@if(config('vendor_product_workspace.enabled'))
    @php($vendorWorkspace = app(\App\Services\VendorProductWorkspaceService::class)->state($product))
    @if($vendorWorkspace)
        <div class="alert badge-soft-info mb-3" role="status">
            <strong dir="ltr">AD-{{ $vendorWorkspace->private_reference }}</strong>
            <p class="mb-0 mt-1">هذه بيانات إعلانك المحفوظة كما أرسلتها.</p>
            <span class="badge badge-soft-success mt-2">{{ app(\App\Services\VendorProductWorkspaceService::class)->vendorActive($vendorWorkspace) ? 'نشط' : 'غير نشط' }}</span>
            @if($vendorWorkspace->legacy_exempt)
                <p class="mb-0 mt-2">{{ translate('legacy_vendor_product_permanent_exemption') }}</p>
            @endif
        </div>
        @if(!app(\App\Services\VendorProductWorkspaceService::class)->vendorActive($vendorWorkspace))
            @include('vendor-views.product.partials.inactive-advertisement')
        @endif
    @endif
@endif
