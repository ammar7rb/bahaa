@extends('theme-views.layouts.app')

@section('title', translate('customer_purchase_packages').' | '.$web_config['company_name'].' '.translate('ecommerce'))

@section('content')
    <main class="main-content d-flex flex-column gap-3 py-3 mb-4">
        <div class="container">
            <div class="row g-3">
                @include('theme-views.partials._profile-aside')

                <div class="col-lg-9">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3 text-capitalize">{{ translate('customer_purchase_packages') }}</h5>

                            @if(request('flag') === 'success')
                                <div class="alert alert-success">{{ translate('customer_purchase_package_payment_success') }}</div>
                            @elseif(request('flag') === 'fail')
                                <div class="alert alert-danger">{{ translate('customer_purchase_package_payment_failed') }}</div>
                            @endif

                            <div class="row g-3 mb-4">
                                <div class="col-sm-6 col-xl-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted mb-1">{{ translate('active_package') }}</div>
                                        <h6 class="mb-0">{{ $limitSummary['subscription']?->package_name ?? translate('No_active_package') }}</h6>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-xl-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted mb-1">{{ translate('total_limit') }}</div>
                                        <h6 class="mb-0">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $limitSummary['total_limit'])) }}</h6>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-xl-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted mb-1">{{ translate('used_limit') }}</div>
                                        <h6 class="mb-0">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $limitSummary['used_limit'])) }}</h6>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-xl-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted mb-1">{{ translate('available_limit') }}</div>
                                        <h6 class="mb-0">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $limitSummary['available_limit'])) }}</h6>
                                    </div>
                                </div>
                            </div>

                            @if(!$paymentAvailable)
                                <div class="alert alert-warning">{{ translate('payment_methods_are_not_available_at_this_time.') }}</div>
                            @endif

                            @if(isset($pendingActivationInvoice) && $pendingActivationInvoice)
                                <div class="border rounded p-3 mb-4">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                        <div>
                                            <h5 class="mb-1">{{ translate('activation_invoice') }} #{{ $pendingActivationInvoice->invoice_no }}</h5>
                                            <p class="text-muted mb-0">{{ translate('pay_this_invoice_to_activate_your_package_and_release_your_paid_order') }}</p>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-muted fs-12">{{ translate('total') }}</div>
                                            <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $pendingActivationInvoice->total_amount)) }}</strong>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <div class="bg-light rounded p-3 h-100">
                                                <div class="text-muted fs-12">{{ translate('package') }}</div>
                                                <strong>{{ $pendingActivationInvoice->package_name ?? translate('not_available') }}</strong>
                                                <div class="small text-muted mt-1">
                                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $pendingActivationInvoice->package_price)) }}
                                                    /
                                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $pendingActivationInvoice->package_purchase_limit)) }}
                                                    {{ translate('purchase_limit') }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="bg-light rounded p-3 h-100">
                                                <div class="text-muted fs-12">{{ translate('monthly_insurance') }}</div>
                                                <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $pendingActivationInvoice->insurance_amount)) }}</strong>
                                                @if($pendingActivationInvoice->insurance_discount_amount > 0)
                                                    <div class="small text-success mt-1">
                                                        {{ translate('discount') }}:
                                                        {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $pendingActivationInvoice->insurance_discount_amount)) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="bg-light rounded p-3 h-100">
                                                <div class="text-muted fs-12">{{ translate('order_group') }}</div>
                                                <strong>{{ $pendingActivationInvoice->order_group_id }}</strong>
                                                <div class="small text-muted mt-1">{{ translate($pendingActivationInvoice->status) }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    @if($pendingActivationInvoice->status === 'pending_package_assignment')
                                        <div class="alert alert-warning mb-0">{{ translate('no_active_package_can_cover_this_order_total_please_ask_admin_to_create_a_custom_package') }}</div>
                                    @else
                                        @if($digitalPaymentAvailable || !$offlinePaymentAvailable)
                                        <form action="{{ route('customer.purchase-package.activation-invoice.pay', ['id' => $pendingActivationInvoice->id]) }}" method="post">
                                            @csrf
                                            <input type="hidden" value="web" name="payment_platform">
                                            <input type="hidden" value="{{ request()->url() }}" name="external_redirect_link">
                                            <input type="hidden" value="{{ session('currency_code') ?: \App\Models\Currency::find(getWebConfig(name: 'system_default_currency'))?->code }}" name="current_currency_code">
                                            <div class="row g-3">
                                                <div class="col-md-8">
                                                    <label class="mb-2">{{ translate('payment_methods') }}</label>
                                                    @if($digitalPaymentAvailable)
                                                        @foreach($paymentGatewayList as $gateway)
                                                            @php($gatewayData = !empty($gateway->additional_data) ? json_decode($gateway->additional_data) : null)
                                                            @php($gatewayTitle = $gatewayData?->gateway_title ?? ucwords(str_replace('_', ' ', $gateway->key_name)))
                                                            <label class="form-check form--check rounded mb-2">
                                                                <input type="radio" class="form-check-input" name="payment_method" value="{{ $gateway->key_name }}" required>
                                                                <span class="form-check-label">{{ $gatewayTitle }}</span>
                                                            </label>
                                                        @endforeach
                                                    @elseif(!$offlinePaymentAvailable)
                                                        <div class="text-muted">{{ translate('payment_unavailable') }}</div>
                                                    @endif
                                                </div>
                                                <div class="col-md-4 d-flex align-items-end">
                                                    @if($digitalPaymentAvailable)
                                                        <button type="submit" class="btn btn-primary w-100">{{ translate('pay_activation_invoice') }}</button>
                                                    @elseif(!$offlinePaymentAvailable)
                                                        <button type="button" class="btn btn-secondary w-100" disabled>{{ translate('payment_unavailable') }}</button>
                                                    @endif
                                                </div>
                                            </div>
                                        </form>
                                        @endif
                                        @include('system-partials._purchase-package-offline-payment-modals', [
                                            'formAction' => route('customer.purchase-package.activation-invoice.offline-payment', ['id' => $pendingActivationInvoice->id]),
                                            'modalIdPrefix' => 'aster-activation-invoice-' . $pendingActivationInvoice->id,
                                            'amount' => $pendingActivationInvoice->total_amount,
                                            'buttonClass' => 'btn-outline-primary',
                                            'submitClass' => 'btn-primary',
                                        ])
                                    @endif
                                </div>
                            @endif

                            @if($limitSummary['has_active_package'] && ($extraCreditSettings['enabled'] ?? false))
                                <div class="border rounded p-3 mb-4">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                        <div>
                                            <h5 class="mb-1">{{ translate('buy_extra_credit') }}</h5>
                                            <p class="text-muted mb-0">
                                                {{ translate('extra_credit_rate') }}:
                                                <strong>{{ $extraCreditSettings['rate'] ?? 0 }}%</strong>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-muted fs-12">{{ translate('minimum_amount') }}</div>
                                            <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $extraCreditSettings['minimum_amount'] ?? 0)) }}</strong>
                                        </div>
                                    </div>

                                    <form action="{{ route('customer.purchase-package.extra-credit.purchase') }}" method="post">
                                        @csrf
                                        <input type="hidden" value="web" name="payment_platform">
                                        <input type="hidden" value="{{ request()->url() }}" name="external_redirect_link">
                                        <input type="hidden" value="{{ session('currency_code') ?: \App\Models\Currency::find(getWebConfig(name: 'system_default_currency'))?->code }}" name="current_currency_code">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="mb-2">{{ translate('credit_amount') }}</label>
                                                <input type="number"
                                                       name="credit_amount"
                                                       class="form-control"
                                                       min="{{ usdToDefaultCurrency(amount: $extraCreditSettings['minimum_amount'] ?? 0.01) }}"
                                                       @if(($extraCreditSettings['maximum_amount'] ?? 0) > 0) max="{{ usdToDefaultCurrency(amount: $extraCreditSettings['maximum_amount']) }}" @endif
                                                       step="0.01"
                                                       value="{{ usdToDefaultCurrency(amount: $extraCreditSettings['step_amount'] ?? $extraCreditSettings['minimum_amount'] ?? 50) }}"
                                                       required>
                                                <small class="text-muted">
                                                    {{ translate('step_amount') }}:
                                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $extraCreditSettings['step_amount'] ?? 0)) }}
                                                </small>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="mb-2">{{ translate('payment_methods') }}</label>
                                                @if($digitalPaymentAvailable)
                                                    @foreach($paymentGatewayList as $gateway)
                                                        @php($gatewayData = !empty($gateway->additional_data) ? json_decode($gateway->additional_data) : null)
                                                        @php($gatewayTitle = $gatewayData?->gateway_title ?? ucwords(str_replace('_', ' ', $gateway->key_name)))
                                                        <label class="form-check form--check rounded mb-2">
                                                            <input type="radio" class="form-check-input" name="payment_method" value="{{ $gateway->key_name }}" required>
                                                            <span class="form-check-label">{{ $gatewayTitle }}</span>
                                                        </label>
                                                    @endforeach
                                                @else
                                                    <div class="text-muted">{{ translate('payment_unavailable') }}</div>
                                                @endif
                                            </div>
                                            <div class="col-md-3 d-flex align-items-end">
                                                @if($digitalPaymentAvailable)
                                                    <button type="submit" class="btn btn-primary w-100">{{ translate('buy_extra_credit') }}</button>
                                                @else
                                                    <button type="button" class="btn btn-secondary w-100" disabled>{{ translate('payment_unavailable') }}</button>
                                                @endif
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            @endif

                            <div class="row g-3">
                                @forelse($packages as $package)
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100 d-flex flex-column">
                                            <div class="d-flex justify-content-between gap-2 mb-2">
                                                <div>
                                                    <h5 class="mb-1">{{ $package->name }}</h5>
                                                    @if($package->is_custom)
                                                        <span class="badge bg-info">{{ translate('custom_package') }}</span>
                                                    @endif
                                                </div>
                                                <div class="text-end">
                                                    <div class="text-muted fs-12">{{ translate('package_price') }}</div>
                                                    <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $package->package_price)) }}</strong>
                                                </div>
                                            </div>
                                            @if($package->description)
                                                <p class="text-muted mb-3">{{ $package->description }}</p>
                                            @endif
                                            <div class="mb-3">
                                                <span class="text-muted">{{ translate('purchase_limit') }}:</span>
                                                <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $package->purchase_limit)) }}</strong>
                                            </div>

                                            <form action="{{ route('customer.purchase-package.purchase', ['id' => $package->id]) }}" method="post" class="mt-auto">
                                                @csrf
                                                <input type="hidden" value="web" name="payment_platform">
                                                <input type="hidden" value="{{ request()->url() }}" name="external_redirect_link">
                                                @if($digitalPaymentAvailable)
                                                    <div class="mb-3">
                                                        <label class="mb-2">{{ translate('payment_methods') }}</label>
                                                        @foreach($paymentGatewayList as $gateway)
                                                            @php($gatewayData = !empty($gateway->additional_data) ? json_decode($gateway->additional_data) : null)
                                                            @php($gatewayTitle = $gatewayData?->gateway_title ?? ucwords(str_replace('_', ' ', $gateway->key_name)))
                                                            <label class="form-check form--check rounded mb-2">
                                                                <input type="radio" class="form-check-input" name="payment_method" value="{{ $gateway->key_name }}" required>
                                                                <span class="form-check-label">{{ $gatewayTitle }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    <button type="submit" class="btn btn-primary w-100">{{ translate('buy_package') }}</button>
                                                @elseif(!$offlinePaymentAvailable)
                                                    <button type="button" class="btn btn-secondary w-100" disabled>{{ translate('payment_unavailable') }}</button>
                                                @endif
                                            </form>
                                            @include('system-partials._purchase-package-offline-payment-modals', [
                                                'formAction' => route('customer.purchase-package.purchase.offline-payment', ['id' => $package->id]),
                                                'modalIdPrefix' => 'aster-package-' . $package->id,
                                                'amount' => $package->package_price,
                                                'buttonClass' => 'btn-outline-primary',
                                                'submitClass' => 'btn-primary',
                                            ])
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12">
                                        <div class="alert alert-info mb-0">{{ translate('no_customer_purchase_packages_available') }}</div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
