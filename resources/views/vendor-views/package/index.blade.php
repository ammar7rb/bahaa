@extends('layouts.vendor.app')

@section('title', translate('Vendor_Packages'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="mb-1 text-capitalize">{{ translate('Vendor_Packages') }}</h1>
                <p class="mb-0 text-muted fs-12">{{ translate('choose_the_package_that_matches_your_listing_and_promotion_needs') }}.</p>
            </div>
            <a href="{{ route('vendor.insurance.index') }}" class="btn btn-outline-primary">
                <i class="fi fi-sr-shield-check"></i> {{ translate('Vendor_Insurance') }}
            </a>
        </div>

        @if(!$summary['insurance_satisfied'])
            <div class="alert alert-soft-warning d-flex justify-content-between align-items-center gap-3 mb-3">
                <span>{{ translate('you_must_activate_vendor_insurance_before_buying_a_package') }}.</span>
                <a href="{{ route('vendor.insurance.index') }}" class="btn btn-sm btn--primary">{{ translate('Activate_Insurance') }}</a>
            </div>
        @endif

        @if($summary['pending_review'])
            <div class="alert alert-soft-warning mb-3">
                <strong>{{ translate('Package_Payment_Is_Under_Admin_Review') }}</strong>
                <div class="fs-12 mt-1">
                    {{ $summary['pending_subscription']->package_name }} ·
                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $summary['pending_subscription']->paid_package_price)) }}.
                    {{ translate('the_package_will_activate_only_after_admin_approval') }}.
                </div>
            </div>
        @elseif($summary['pending_subscription'])
            <div class="alert alert-soft-info mb-3">
                {{ translate('a_payment_attempt_is_pending_for') }} <strong>{{ $summary['pending_subscription']->package_name }}</strong>.
                {{ translate('you_can_retry_the_same_package_payment_method') }}.
            </div>
        @endif

        @if($summary['active_subscription'])
            @php($active = $summary['active_subscription'])
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                        <div>
                            <div class="text-success fs-12 fw-semibold text-uppercase">{{ translate('Active_Package') }}</div>
                            <h3 class="mb-1">{{ $active->package_name }}</h3>
                            <div class="text-muted fs-12">
                                {{ translate('Activated') }}: {{ $active->activated_at }} ·
                                {{ $active->expires_at ? translate('Expires') . ': ' . $active->expires_at : translate('No_Expiry') }}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted fs-12">{{ translate('Paid') }}</div>
                            <strong class="text-primary">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $active->paid_package_price)) }}</strong>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="fs-12 text-muted">{{ translate('Product_Listings_Remaining') }}</div>
                                <strong>{{ max($active->product_limit + $active->product_adjustment_limit - $active->used_product_limit, 0) }}</strong>
                                <div class="fs-12 text-muted">{{ $active->product_duration_days }} {{ translate('days_each') }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="fs-12 text-muted">{{ translate('Featured_Search_Remaining') }}</div>
                                <strong>{{ max($active->search_promotion_limit + $active->search_promotion_adjustment_limit - $active->used_search_promotion_limit, 0) }}</strong>
                                <div class="fs-12 text-muted">{{ $active->search_promotion_duration_days }} {{ translate('days_each') }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="fs-12 text-muted">{{ translate('Homepage_Featured_Remaining') }}</div>
                                <strong>{{ max($active->homepage_promotion_limit + $active->homepage_promotion_adjustment_limit - $active->used_homepage_promotion_limit, 0) }}</strong>
                                <div class="fs-12 text-muted">{{ $active->homepage_promotion_duration_days }} {{ translate('days_each') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-3 mb-3">
            @forelse($packages as $package)
                @php($pending = $summary['pending_subscription'])
                @php($canPayPackage = $summary['insurance_satisfied'] && (!$pending || ($pending->status === 'pending' && (int) $pending->seller_package_id === (int) $package->id)))
                <div class="col-xl-4 col-md-6">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between gap-2 mb-3">
                                <div>
                                    <h3 class="mb-1">{{ $package->name }}</h3>
                                    <div class="text-muted fs-12">{{ $package->description }}</div>
                                </div>
                                <div class="text-end">
                                    <strong class="text-primary fs-18">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $package->package_price)) }}</strong>
                                    <div class="fs-12 text-muted">{{ $package->package_validity_days ? $package->package_validity_days . ' ' . translate('days') : translate('No_Expiry') }}</div>
                                </div>
                            </div>

                            <div class="border-top border-bottom py-3 mb-3 d-flex flex-column gap-2 fs-12">
                                <div class="d-flex justify-content-between"><span>{{ translate('Product_Listings') }}</span><strong>{{ $package->product_limit }} · {{ $package->product_duration_days }} {{ translate('days') }}</strong></div>
                                <div class="d-flex justify-content-between"><span>{{ translate('Featured_Search') }}</span><strong>{{ $package->search_promotion_limit }} @if($package->search_promotion_limit) · {{ $package->search_promotion_duration_days }} {{ translate('days') }} @endif</strong></div>
                                <div class="d-flex justify-content-between"><span>{{ translate('Homepage_Featured') }}</span><strong>{{ $package->homepage_promotion_limit }} @if($package->homepage_promotion_limit) · {{ $package->homepage_promotion_duration_days }} {{ translate('days') }} @endif</strong></div>
                            </div>

                            <div class="mt-auto">
                                @if($canPayPackage && $digitalPaymentAvailable)
                                    <form action="{{ route('vendor.packages.pay') }}" method="post" class="mb-2">
                                        @csrf
                                        <input type="hidden" name="package_id" value="{{ $package->id }}">
                                        <input type="hidden" name="current_currency_code" value="{{ session('currency_code') ?: \App\Models\Currency::find(getWebConfig(name: 'system_default_currency'))?->code }}">
                                        <div class="input-group">
                                            <select class="form-select" name="payment_method" required>
                                                <option value="">{{ translate('Online_Payment_Method') }}</option>
                                                @foreach($paymentGatewayList as $gateway)
                                                    @php($gatewayData = !empty($gateway->additional_data) ? json_decode($gateway->additional_data) : null)
                                                    <option value="{{ $gateway->key_name }}">{{ $gatewayData?->gateway_title ?? ucwords(str_replace('_', ' ', $gateway->key_name)) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn--primary"><i class="fi fi-sr-credit-card"></i></button>
                                        </div>
                                    </form>
                                @endif

                                @if($canPayPackage && $offlinePaymentAvailable && $offlinePaymentMethods->isNotEmpty())
                                    <button type="button" class="btn btn-outline-primary w-100" data-toggle="collapse" data-target="#package-offline-{{ $package->id }}">
                                        {{ translate('Pay_By_Offline_Transfer') }}
                                    </button>
                                    <div class="collapse mt-2" id="package-offline-{{ $package->id }}">
                                        @foreach($offlinePaymentMethods as $method)
                                            <form action="{{ route('vendor.packages.offline-payment') }}" method="post" enctype="multipart/form-data" class="border rounded p-3 mb-2">
                                                @csrf
                                                <input type="hidden" name="package_id" value="{{ $package->id }}">
                                                <input type="hidden" name="method_id" value="{{ $method->id }}">
                                                <div class="fw-semibold mb-2">{{ translatePaymentText($method->method_name) }}</div>
                                                @foreach(($method->method_fields ?? []) as $field)
                                                    <div class="fs-12 mb-1"><strong>{{ translatePaymentText($field['input_name'] ?? '') }}:</strong> {{ translatePaymentText($field['input_data'] ?? '') }}</div>
                                                @endforeach
                                                @foreach(($method->method_informations ?? []) as $field)
                                                    @php($inputName = $field['customer_input'] ?? null)
                                                    @continue(!$inputName || $inputName === 'payment_screenshot')
                                                    <input type="text" class="form-control mt-2" name="method_information[{{ $inputName }}]"
                                                           placeholder="{{ translatePaymentText($field['customer_placeholder'] ?? $inputName) }}"
                                                           {{ ($field['is_required'] ?? 0) ? 'required' : '' }}>
                                                @endforeach
                                                <input type="file" class="form-control mt-2" name="payment_proof" accept="image/jpeg,image/png,image/webp" required>
                                                <textarea class="form-control mt-2" name="payment_note" rows="2" placeholder="{{ translate('Note') }}"></textarea>
                                                <button type="submit" class="btn btn--primary w-100 mt-2">{{ translate('Submit_For_Admin_Review') }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                @elseif(!$canPayPackage)
                                    <button type="button" class="btn btn-secondary w-100" disabled>{{ translate('Payment_Not_Available') }}</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="alert alert-soft-info mb-0">{{ translate('No_active_vendor_packages_available') }}.</div></div>
            @endforelse
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="mb-3">{{ translate('Subscription_History') }}</h3>
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle">
                        <thead class="thead-light"><tr><th>{{ translate('Package') }}</th><th>{{ translate('Price') }}</th><th>{{ translate('Payment_Method') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Date') }}</th></tr></thead>
                        <tbody>
                        @forelse($subscriptions as $subscription)
                            <tr>
                                <td>{{ $subscription->package_name }}</td>
                                <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $subscription->paid_package_price)) }}</td>
                                <td>{{ $subscription->payment_method ? translate($subscription->payment_method) : translate('Not_Selected') }}</td>
                                <td><span class="badge badge-soft-{{ $subscription->status === 'active' ? 'success' : ($subscription->status === 'pending_review' ? 'warning' : ($subscription->status === 'rejected' ? 'danger' : 'secondary')) }}">{{ translate($subscription->status) }}</span></td>
                                <td>{{ $subscription->created_at }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No_data_found') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
