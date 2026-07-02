@php
    $nextStepRoutes = [
        'activate_insurance' => route('vendor.insurance.index'),
        'insurance_pending_review' => route('vendor.insurance.index'),
        'choose_package' => route('vendor.packages.index'),
        'package_pending_review' => route('vendor.packages.index'),
        'add_first_product' => route('vendor.products.add'),
        'product_limit_reached' => route('vendor.packages.index'),
        'manage_products' => route('vendor.products.list', ['type' => 'all']),
    ];
    $nextStepLabels = [
        'activate_insurance' => 'Activate_Vendor_Insurance',
        'insurance_pending_review' => 'Insurance_Payment_Under_Review',
        'choose_package' => 'Choose_Vendor_Package',
        'package_pending_review' => 'Package_Payment_Under_Review',
        'add_first_product' => 'Add_First_Product',
        'product_limit_reached' => 'Upgrade_Or_Renew_Package',
        'manage_products' => 'Manage_Products',
        'verify_phone' => 'Verify_Phone_Number',
        'await_admin_approval' => 'Account_Under_Admin_Review',
    ];
@endphp

<div class="card mb-3 remove-card-shadow">
    <div class="card-header border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-capitalize">{{ translate('Vendor_Setup_And_Package_Usage') }}</h4>
            <p class="mb-0 text-muted fs-12">{{ translate('Track_your_account_readiness_and_available_package_benefits') }}.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <strong>{{ $setup['completed_steps'] }}/{{ count($setup['steps']) }}</strong>
            <span class="text-muted fs-12">{{ translate('Steps_Completed') }}</span>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="progress mb-3" style="height: 6px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $setup['completion_percentage'] }}%"
                 aria-valuenow="{{ $setup['completion_percentage'] }}" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <div class="row g-2 mb-3">
            @foreach($setup['steps'] as $step)
                @php
                    $stepLabels = [
                        'account' => 'Account_Verification',
                        'insurance' => 'One_Time_Insurance',
                        'package' => 'Vendor_Package',
                        'products' => 'Published_Products',
                    ];
                @endphp
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded p-3 h-100 d-flex align-items-center gap-2">
                        <span class="text-{{ $step['completed'] ? 'success' : ($step['state'] === 'pending_review' ? 'warning' : 'muted') }} fs-18">
                            <i class="{{ $step['completed'] ? 'tio-checkmark-circle' : ($step['state'] === 'pending_review' ? 'tio-time' : 'tio-circle-outlined') }}"></i>
                        </span>
                        <div>
                            <strong class="d-block">{{ translate($stepLabels[$step['key']]) }}</strong>
                            <span class="fs-12 text-muted">{{ translate($step['state']) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-2 align-items-stretch">
            <div class="col-md-6 col-xl-3">
                <div class="bg-light rounded p-3 h-100">
                    <div class="fs-12 text-muted mb-1">{{ translate('Active_Package') }}</div>
                    <strong>{{ $setup['package']['active_subscription']?->package_name ?? translate('Not_Available') }}</strong>
                    @if($setup['package']['active_subscription']?->expires_at)
                        <div class="fs-12 text-muted mt-1">{{ translate('Expires_At') }}: {{ $setup['package']['active_subscription']->expires_at }}</div>
                    @endif
                    <div class="fs-12 text-muted mt-1">{{ translate('Sales_Commission') }}: {{ number_format($setup['commission']['effective_rate'], 2) }}%</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="bg-light rounded p-3 h-100">
                    <div class="fs-12 text-muted mb-1">{{ translate('Product_Listings_Remaining') }}</div>
                    <strong class="fs-18 text-primary">{{ $setup['products']['remaining_product_limit'] }}</strong>
                    <span class="fs-12 text-muted">/ {{ $setup['products']['product_limit'] }}</span>
                    <div class="fs-12 text-muted mt-1">{{ $setup['published_products_count'] }} {{ translate('Currently_Published') }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="bg-light rounded p-3 h-100">
                    <div class="fs-12 text-muted mb-1">{{ translate('Featured_Search_Remaining') }}</div>
                    <strong class="fs-18 text-primary">{{ $setup['search_promotions']['remaining_search_promotion_limit'] }}</strong>
                    <span class="fs-12 text-muted">/ {{ $setup['search_promotions']['search_promotion_limit'] }}</span>
                    <a class="d-block fs-12 mt-1" href="{{ route('vendor.promotions.search.index') }}">{{ translate('Manage_Featured_Search') }}</a>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="bg-light rounded p-3 h-100">
                    <div class="fs-12 text-muted mb-1">{{ translate('Homepage_Featured_Remaining') }}</div>
                    <strong class="fs-18 text-primary">{{ $setup['homepage_promotions']['remaining_homepage_promotion_limit'] }}</strong>
                    <span class="fs-12 text-muted">/ {{ $setup['homepage_promotions']['homepage_promotion_limit'] }}</span>
                    <a class="d-block fs-12 mt-1" href="{{ route('vendor.promotions.homepage.index') }}">{{ translate('Manage_Homepage_Featuring') }}</a>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top">
            <div>
                <span class="text-muted fs-12">{{ translate('Next_Step') }}:</span>
                <strong class="ml-1">{{ translate($nextStepLabels[$setup['next_step']] ?? $setup['next_step']) }}</strong>
            </div>
            @if(isset($nextStepRoutes[$setup['next_step']]))
                <a class="btn btn--primary" href="{{ $nextStepRoutes[$setup['next_step']] }}">
                    {{ translate($nextStepLabels[$setup['next_step']] ?? $setup['next_step']) }}
                    <i class="tio-arrow-forward ml-1"></i>
                </a>
            @endif
        </div>
    </div>
</div>
