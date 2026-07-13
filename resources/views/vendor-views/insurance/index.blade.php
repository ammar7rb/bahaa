@extends('layouts.vendor.app')

@section('title', translate('Vendor_Insurance'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="mb-1 text-capitalize">{{ translate('Vendor_Insurance') }}</h1>
                <p class="mb-0 text-muted fs-12">
                    {{ translate('the_insurance_is_paid_once_before_product_publishing_and_is_separate_from_packages_and_commission') }}.
                </p>
            </div>
            <div class="text-end">
                <div class="text-muted fs-12">{{ translate('Required_Amount') }}</div>
                <strong class="text-primary fs-20">
                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $summary['settings']['amount'])) }}
                </strong>
            </div>
        </div>

        @if(!$summary['settings']['enabled'])
            <div class="alert alert-soft-info d-flex gap-2 align-items-start" role="alert">
                <i class="fi fi-sr-info mt-1"></i>
                <span>{{ translate('vendor_insurance_is_currently_disabled_and_no_payment_is_required') }}.</span>
            </div>
        @elseif($summary['active_insurance'])
            <div class="alert alert-soft-success d-flex gap-2 align-items-start" role="alert">
                <i class="fi fi-sr-shield-check mt-1"></i>
                <div>
                    <strong>{{ translate('Your_Insurance_Is_Active') }}</strong>
                    <div class="fs-12 mt-1">
                        {{ translate('Transaction') }}: {{ $summary['active_insurance']->transaction_id }}
                        @if($summary['active_insurance']->paid_at)
                            · {{ translate('Paid_At') }}: {{ $summary['active_insurance']->paid_at }}
                        @endif
                    </div>
                </div>
            </div>
        @elseif($summary['pending_review'])
            <div class="alert alert-soft-warning d-flex gap-2 align-items-start" role="alert">
                <i class="fi fi-sr-hourglass-end mt-1"></i>
                <div>
                    <strong>{{ translate('Payment_Is_Under_Admin_Review') }}</strong>
                    <div class="fs-12 mt-1">
                        {{ translate('your_transfer_information_was_received_and_insurance_will_only_activate_after_admin_approval') }}.
                    </div>
                </div>
            </div>
        @elseif(!$summary['required'])
            <div class="alert alert-soft-success d-flex gap-2 align-items-start" role="alert">
                <i class="fi fi-sr-shield-check mt-1"></i>
                <span>{{ translate('no_new_insurance_payment_is_required_for_your_account') }}.</span>
            </div>
        @endif

        @if($summary['can_pay'])
            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="mb-3">
                                <h3 class="mb-1">{{ translate('Online_Payment') }}</h3>
                                <p class="mb-0 fs-12 text-muted">
                                    {{ translate('insurance_is_activated_automatically_after_a_verified_gateway_callback') }}.
                                </p>
                            </div>

                            @if($digitalPaymentAvailable)
                                <form action="{{ route('vendor.insurance.pay') }}" method="post">
                                    @csrf
                                    <input type="hidden" name="current_currency_code"
                                           value="{{ session('currency_code') ?: \App\Models\Currency::find(getWebConfig(name: 'system_default_currency'))?->code }}">
                                    <div class="d-flex flex-column gap-2 mb-3">
                                        @foreach($paymentGatewayList as $gateway)
                                            @php($gatewayData = !empty($gateway->additional_data) ? json_decode($gateway->additional_data) : null)
                                            @php($gatewayTitle = $gatewayData?->gateway_title ?? ucwords(str_replace('_', ' ', $gateway->key_name)))
                                            <label class="border rounded p-3 d-flex align-items-center gap-2 mb-0">
                                                <input type="radio" name="payment_method" value="{{ $gateway->key_name }}" required>
                                                <span class="fw-medium">{{ $gatewayTitle }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <button type="submit" class="btn btn--primary w-100">
                                        <i class="fi fi-sr-credit-card"></i>
                                        {{ translate('Pay_Insurance') }}
                                    </button>
                                </form>
                            @else
                                <div class="alert alert-soft-secondary mb-0">
                                    {{ translate('online_payment_is_not_available_right_now') }}.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="mb-3">
                                <h3 class="mb-1">{{ translate('Offline_Transfer') }}</h3>
                                <p class="mb-0 fs-12 text-muted">
                                    {{ translate('every_offline_transfer_requires_manual_admin_review_before_insurance_activation') }}.
                                </p>
                            </div>

                            @if($offlinePaymentAvailable && $offlinePaymentMethods->isNotEmpty())
                                <div class="accordion" id="seller-insurance-offline-methods">
                                    @foreach($offlinePaymentMethods as $method)
                                        <div class="border rounded mb-2">
                                            <button class="btn btn-light w-100 text-left d-flex justify-content-between align-items-center"
                                                    type="button" data-toggle="collapse"
                                                    data-target="#offline-method-{{ $method->id }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                                                <span>{{ translatePaymentText($method->method_name) }}</span>
                                                <i class="fi fi-sr-angle-small-down"></i>
                                            </button>
                                            <div id="offline-method-{{ $method->id }}" class="collapse {{ $loop->first ? 'show' : '' }}"
                                                 data-parent="#seller-insurance-offline-methods">
                                                <form action="{{ route('vendor.insurance.offline-payment') }}" method="post"
                                                      enctype="multipart/form-data" class="p-3 border-top">
                                                    @csrf
                                                    <input type="hidden" name="method_id" value="{{ $method->id }}">

                                                    @if(!empty($method->method_fields))
                                                        <div class="bg-light rounded p-3 mb-3">
                                                            @foreach($method->method_fields as $field)
                                                                <div class="fs-12 mb-1">
                                                                    <strong>{{ translatePaymentText($field['input_name'] ?? '') }}:</strong>
                                                                    {{ translatePaymentText($field['input_data'] ?? '') }}
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    @foreach(($method->method_informations ?? []) as $field)
                                                        @php($inputName = $field['customer_input'] ?? null)
                                                        @continue(!$inputName || $inputName === 'payment_screenshot')
                                                        <div class="form-group mb-3">
                                                            <label class="form-label" for="offline-{{ $method->id }}-{{ $inputName }}">
                                                                {{ translatePaymentText($field['customer_placeholder'] ?? $inputName) }}
                                                                @if($field['is_required'] ?? 0)<span class="text-danger">*</span>@endif
                                                            </label>
                                                            <input type="text" class="form-control"
                                                                   id="offline-{{ $method->id }}-{{ $inputName }}"
                                                                   name="method_information[{{ $inputName }}]"
                                                                   {{ ($field['is_required'] ?? 0) ? 'required' : '' }}>
                                                        </div>
                                                    @endforeach

                                                    <div class="form-group mb-3">
                                                        <label class="form-label" for="payment-proof-{{ $method->id }}">
                                                            {{ translate('Payment_Proof_Image') }} <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="file" class="form-control" id="payment-proof-{{ $method->id }}"
                                                               name="payment_proof" accept="image/jpeg,image/png,image/webp" required>
                                                        <small class="text-muted">JPG, PNG, WEBP · {{ translate('Max') }} 5 MB</small>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label class="form-label" for="payment-note-{{ $method->id }}">{{ translate('Note') }}</label>
                                                        <textarea class="form-control" id="payment-note-{{ $method->id }}"
                                                                  name="payment_note" rows="2"></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn--primary w-100">
                                                        <i class="fi fi-sr-paper-plane"></i>
                                                        {{ translate('Submit_For_Review') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="alert alert-soft-secondary mb-0">
                                    {{ translate('offline_payment_is_not_available_right_now') }}.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">{{ translate('Insurance_History') }}</h3>
                    <span class="badge badge-soft-dark">{{ $insurances->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Transaction') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Payment_Method') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Date') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($insurances as $insurance)
                            <tr>
                                <td class="text-break">{{ $insurance->transaction_id }}</td>
                                <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $insurance->amount)) }}</td>
                                <td>{{ $insurance->payment_method ? translate($insurance->payment_method) : translate('Not_Selected') }}</td>
                                <td><span class="badge badge-soft-{{ $insurance->status === 'paid' ? 'success' : ($insurance->status === 'pending_review' ? 'warning' : ($insurance->status === 'rejected' ? 'danger' : 'secondary')) }}">{{ translate($insurance->status) }}</span></td>
                                <td>{{ $insurance->created_at }}</td>
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
