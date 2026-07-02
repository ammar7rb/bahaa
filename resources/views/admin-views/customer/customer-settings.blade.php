@php use App\Utils\Convert; @endphp
@extends('layouts.admin.app')

@section('title', translate('customer_settings'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                {{ translate('business_Setup') }}
            </h2>
        </div>
        @include('admin-views.business-settings.business-setup-inline-menu')

        <form action="{{ route('admin.business-settings.customer-settings') }}" method="post" id="update-settings">
            @csrf

            <div class="d-flex flex-column gap-3">
                <div class="card">
                    <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                        <div class="p-12 p-sm-20 bg-section rounded">
                            @php($walletStatus = getWebConfig(name: 'wallet_status'))
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <h2>{{ translate('Customer_Wallet') }}</h2>
                                    <p class="mb-0">
                                        {{ translate('for_these_wallet_settings_customers_can_get_the_refund_to_the_wallet_and_also_can_use_their_wallet_money_to_pay_for_any_order') }}
                                    </p>
                                </div>
                                <div>
                                    <label class="switcher" for="customer-wallet-status">
                                        <input class="switcher_input custom-modal-plugin" type="checkbox" value="1"
                                            name="customer_wallet" id="customer-wallet-status"
                                            {{ $walletStatus == 1 ? 'checked' : '' }} data-modal-type="input-change"
                                            data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/customer-wallet-on.png') }}"
                                            data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/customer-wallet-off.png') }}"
                                            data-on-title="{{ translate('want_to_Turn_ON_Customer_Wallet') }}"
                                            data-off-title="{{ translate('want_to_Turn_OFF_Customer_Wallet') }}"
                                            data-on-message="<p>{{ translate('if_enabled_customers_can_have_the_wallet_option_on_their_account_and_use_it_while_placing_orders_and_getting_refunds') }}</p>"
                                            data-off-message="<p>{{ translate('if_disabled_customer_wallet_option_will_be_hidden_from_their_account') }}</p>">
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="row g-4">
                                @php($addFundsToWallet = getWebConfig(name: 'add_funds_to_wallet'))
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="">
                                            {{ translate('add_funds_to_wallet') }}
                                            <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="right"
                                                data-bs-title="{{ translate('enabling_the_option_customers_will_be_able_to_add_funds_to_the_wallet_through_the_available_payment_method') }}.">
                                                <i class="fi fi-sr-info"></i>
                                            </span>
                                        </label>
                                        <label
                                            class="d-flex justify-content-between align-items-center gap-3 border rounded px-3 py-10 bg-white user-select-none">
                                            <span class="fw-medium text-dark">{{ translate('status') }}</span>
                                            <label class="switcher" for="add-funds-to-wallet">
                                                <input class="switcher_input custom-modal-plugin" type="checkbox"
                                                    value="1" name="add_funds_to_wallet" id="add-funds-to-wallet"
                                                    {{ $walletStatus && $addFundsToWallet ? 'checked' : '' }}
                                                    {{ $walletStatus == 0 ? 'disabled' : '' }}
                                                    data-modal-type="input-change"
                                                    data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/wallet-on.png') }}"
                                                    data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/wallet-off.png') }}"
                                                    data-on-title="{{ translate('want_to_Turn_ON_Add_Fund_to_Wallet_option') }}"
                                                    data-off-title="{{ translate('want_to_Turn_OFF_Add_Fund_to_Wallet_option') }}"
                                                    data-on-message="<p>{{ translate('if_enabled_customers_can_add_money_to_their_wallet') }}</p>"
                                                    data-off-message="<p>{{ translate('if_disabled_customers_would_not_be_able_to_add_money_to_their_wallet') }}</p>">
                                                <span class="switcher_control"></span>
                                            </label>
                                        </label>
                                    </div>
                                </div>
                                @php($minimumAddFundAmount = getWebConfig(name: 'minimum_add_fund_amount'))
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="">
                                            {{ translate('minimum_Add_Amount') }}
                                            ({{ getCurrencySymbol(type: 'default') }})
                                        </label>
                                        <input type="text" class="form-control" name="minimum_add_fund_amount"
                                            id="minimum_add_fund_amount" placeholder="{{ translate('ex') . ': ' . '10' }}"
                                            value="{{ Convert::default($minimumAddFundAmount) ?? 0 }}"
                                            {{ $walletStatus == 0 ? 'disabled' : '' }}>
                                    </div>
                                </div>
                                @php($maximumAddFundAmount = getWebConfig(name: 'maximum_add_fund_amount'))
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="">
                                            {{ translate('maximum_Add_Amount') }}
                                            ({{ getCurrencySymbol(type: 'default') }})
                                        </label>
                                        <input type="text" class="form-control" name="maximum_add_fund_amount"
                                            id="maximum_add_fund_amount" placeholder="{{ translate('ex') . ': ' . '10' }}"
                                            value="{{ Convert::default($maximumAddFundAmount) ?? 0 }}"
                                            {{ $walletStatus == 0 ? 'disabled' : '' }}>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                        @php($customerExtraCreditStatus = getWebConfig(name: 'customer_extra_credit_status'))
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <h2 class="text-capitalize">
                                        {{ translate('Customer_Extra_Credit') }}
                                    </h2>
                                    <p class="mb-0">
                                        {{ translate('control_the_extra_purchase_credit_customers_can_buy_when_their_package_limit_is_not_enough_for_the_cart_product_total') }}
                                    </p>
                                </div>
                                <div>
                                    <label class="switcher" for="customer-extra-credit-status">
                                        <input class="switcher_input custom-modal-plugin" type="checkbox" value="1"
                                               name="customer_extra_credit_status" id="customer-extra-credit-status"
                                               {{ $customerExtraCreditStatus ? 'checked' : '' }} data-modal-type="input-change"
                                               data-on-title="{{ translate('want_to_Turn_ON_Customer_Extra_Credit') }}"
                                               data-off-title="{{ translate('want_to_Turn_OFF_Customer_Extra_Credit') }}"
                                               data-on-message="<p>{{ translate('if_enabled_customers_can_buy_extra_credit_when_their_available_purchase_limit_is_less_than_the_cart_product_total') }}</p>"
                                               data-off-message="<p>{{ translate('if_disabled_customers_must_buy_or_upgrade_a_package_when_their_purchase_limit_is_not_enough') }}</p>">
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="row g-4">
                                @php($extraCreditMinAmount = getWebConfig(name: 'customer_extra_credit_min_amount') ?? 50)
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="customer_extra_credit_min_amount">
                                            {{ translate('Minimum_Extra_Credit_Amount') }}
                                            ({{ getCurrencySymbol(type: 'default') }})
                                        </label>
                                        <input type="number" step="0.01" min="0.01" class="form-control"
                                               name="customer_extra_credit_min_amount" id="customer_extra_credit_min_amount"
                                               placeholder="{{ translate('ex') . ': ' . '50' }}"
                                               value="{{ Convert::default($extraCreditMinAmount) ?? 50 }}">
                                    </div>
                                </div>
                                @php($extraCreditStepAmount = getWebConfig(name: 'customer_extra_credit_step_amount') ?? 100)
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="customer_extra_credit_step_amount">
                                            {{ translate('Extra_Credit_Step_Amount') }}
                                            ({{ getCurrencySymbol(type: 'default') }})
                                        </label>
                                        <input type="number" step="0.01" min="0.01" class="form-control"
                                               name="customer_extra_credit_step_amount" id="customer_extra_credit_step_amount"
                                               placeholder="{{ translate('ex') . ': ' . '100' }}"
                                               value="{{ Convert::default($extraCreditStepAmount) ?? 100 }}">
                                    </div>
                                </div>
                                @php($extraCreditRate = getWebConfig(name: 'customer_extra_credit_rate') ?? 10)
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="customer_extra_credit_rate">
                                            {{ translate('Extra_Credit_Payment_Rate') }} (%)
                                        </label>
                                        <input type="number" step="0.01" min="0" max="100" class="form-control"
                                               name="customer_extra_credit_rate" id="customer_extra_credit_rate"
                                               placeholder="{{ translate('ex') . ': ' . '10' }}"
                                               value="{{ $extraCreditRate }}">
                                    </div>
                                </div>
                                @php($extraCreditMaxAmount = getWebConfig(name: 'customer_extra_credit_max_amount') ?? 0)
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="customer_extra_credit_max_amount">
                                            {{ translate('Maximum_Extra_Credit_Amount') }}
                                            ({{ getCurrencySymbol(type: 'default') }})
                                        </label>
                                        <input type="number" step="0.01" min="0" class="form-control"
                                               name="customer_extra_credit_max_amount" id="customer_extra_credit_max_amount"
                                               placeholder="{{ translate('0_means_unlimited') }}"
                                               value="{{ Convert::default($extraCreditMaxAmount) ?? 0 }}">
                                    </div>
                                </div>
                                @php($extraCreditRoundingRule = getWebConfig(name: 'customer_extra_credit_rounding_rule') ?? 'ceil_step')
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="customer_extra_credit_rounding_rule">
                                            {{ translate('Extra_Credit_Calculation_Rule') }}
                                        </label>
                                        <div class="select-wrapper">
                                            <select name="customer_extra_credit_rounding_rule" id="customer_extra_credit_rounding_rule" class="form-select">
                                                <option value="ceil_step" {{ $extraCreditRoundingRule == 'ceil_step' ? 'selected' : '' }}>
                                                    {{ translate('Round_shortage_up_to_step_amount') }}
                                                </option>
                                                <option value="exact_shortage" {{ $extraCreditRoundingRule == 'exact_shortage' ? 'selected' : '' }}>
                                                    {{ translate('Use_exact_shortage_amount') }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-md-6">
                                    <div class="p-3 rounded bg-white border h-100">
                                        <h4 class="mb-2">{{ translate('Example') }}</h4>
                                        <p class="mb-0 fs-12">
                                            {{ translate('if_shortage_is_80_and_step_amount_is_100_the_system_can_suggest_100_extra_credit_and_charge_the_customer_based_on_the_rate') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                        @php($monthlyInsuranceStatus = getWebConfig(name: 'customer_monthly_insurance_status'))
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <h2 class="text-capitalize">{{ translate('Customer_Monthly_Insurance') }}</h2>
                                    <p class="mb-0">
                                        {{ translate('control_the_monthly_insurance_amount_required_to_activate_customer_orders_after_product_payment') }}
                                    </p>
                                </div>
                                <label class="switcher" for="customer-monthly-insurance-status">
                                    <input class="switcher_input custom-modal-plugin" type="checkbox" value="1"
                                           name="customer_monthly_insurance_status" id="customer-monthly-insurance-status"
                                           {{ $monthlyInsuranceStatus ? 'checked' : '' }} data-modal-type="input-change"
                                           data-on-title="{{ translate('want_to_Turn_ON_Customer_Monthly_Insurance') }}"
                                           data-off-title="{{ translate('want_to_Turn_OFF_Customer_Monthly_Insurance') }}">
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                        </div>
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="row g-4">
                                @php($monthlyInsuranceAmount = getWebConfig(name: 'customer_monthly_insurance_amount') ?? 0)
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="customer_monthly_insurance_amount">
                                            {{ translate('Monthly_Insurance_Amount') }} ({{ getCurrencySymbol(type: 'default') }})
                                        </label>
                                        <input type="number" step="0.01" min="0" class="form-control"
                                               name="customer_monthly_insurance_amount" id="customer_monthly_insurance_amount"
                                               value="{{ Convert::default($monthlyInsuranceAmount) ?? 0 }}">
                                    </div>
                                </div>
                                @php($monthlyInsurancePeriodDays = getWebConfig(name: 'customer_monthly_insurance_period_days') ?? 30)
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="customer_monthly_insurance_period_days">
                                            {{ translate('Insurance_Period_Days') }}
                                        </label>
                                        <input type="number" min="1" class="form-control"
                                               name="customer_monthly_insurance_period_days" id="customer_monthly_insurance_period_days"
                                               value="{{ $monthlyInsurancePeriodDays }}">
                                    </div>
                                </div>
                                @php($monthlyInsuranceDiscountType = getWebConfig(name: 'customer_monthly_insurance_first_discount_type') ?? 'none')
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="customer_monthly_insurance_first_discount_type">
                                            {{ translate('First_Time_Discount_Type') }}
                                        </label>
                                        <div class="select-wrapper">
                                            <select name="customer_monthly_insurance_first_discount_type" id="customer_monthly_insurance_first_discount_type" class="form-select">
                                                <option value="none" {{ $monthlyInsuranceDiscountType == 'none' ? 'selected' : '' }}>{{ translate('none') }}</option>
                                                <option value="fixed" {{ $monthlyInsuranceDiscountType == 'fixed' ? 'selected' : '' }}>{{ translate('fixed_amount') }}</option>
                                                <option value="percentage" {{ $monthlyInsuranceDiscountType == 'percentage' ? 'selected' : '' }}>{{ translate('percentage') }}</option>
                                                <option value="free" {{ $monthlyInsuranceDiscountType == 'free' ? 'selected' : '' }}>{{ translate('free') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                @php($monthlyInsuranceDiscountValue = getWebConfig(name: 'customer_monthly_insurance_first_discount_value') ?? 0)
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="customer_monthly_insurance_first_discount_value">
                                            {{ translate('First_Time_Discount_Value') }}
                                        </label>
                                        <input type="number" step="0.01" min="0" class="form-control"
                                               name="customer_monthly_insurance_first_discount_value" id="customer_monthly_insurance_first_discount_value"
                                               value="{{ $monthlyInsuranceDiscountType == 'fixed' ? Convert::default($monthlyInsuranceDiscountValue) : $monthlyInsuranceDiscountValue }}">
                                    </div>
                                </div>
                                @php($activationHoldMessage = getWebConfig(name: 'customer_activation_hold_message'))
                                <div class="col-xl-8 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="customer_activation_hold_message">
                                            {{ translate('Activation_Hold_Message') }}
                                        </label>
                                        <textarea name="customer_activation_hold_message" id="customer_activation_hold_message" class="form-control" rows="3">{{ $activationHoldMessage }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                        @php($threeStepShippingStatus = getWebConfig(name: 'customer_three_step_shipping_status'))
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <h2 class="text-capitalize">{{ translate('Three_Step_Shipping_Options') }}</h2>
                                    <p class="mb-0">
                                        {{ translate('control_same_day_next_day_and_normal_delivery_costs_for_customer_checkout') }}
                                    </p>
                                </div>
                                <label class="switcher" for="customer-three-step-shipping-status">
                                    <input class="switcher_input custom-modal-plugin" type="checkbox" value="1"
                                           name="customer_three_step_shipping_status" id="customer-three-step-shipping-status"
                                           {{ $threeStepShippingStatus ? 'checked' : '' }} data-modal-type="input-change"
                                           data-on-title="{{ translate('want_to_Turn_ON_Three_Step_Shipping') }}"
                                           data-off-title="{{ translate('want_to_Turn_OFF_Three_Step_Shipping') }}">
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                        </div>
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="row g-4">
                                @foreach([
                                    'same_day' => ['label' => translate('Same_Day_Delivery'), 'default_title' => 'Same Day Delivery', 'default_duration' => 'Same day'],
                                    'next_day' => ['label' => translate('Next_Day_Delivery'), 'default_title' => 'Next Day Delivery', 'default_duration' => 'Next day'],
                                    'normal' => ['label' => translate('Normal_Delivery'), 'default_title' => 'Normal Delivery', 'default_duration' => '3 days'],
                                ] as $shippingKey => $shippingOption)
                                    @php($shippingStatus = getWebConfig(name: 'customer_shipping_'.$shippingKey.'_status'))
                                    @php($shippingTitle = getWebConfig(name: 'customer_shipping_'.$shippingKey.'_title') ?? $shippingOption['default_title'])
                                    @php($shippingDuration = getWebConfig(name: 'customer_shipping_'.$shippingKey.'_duration') ?? $shippingOption['default_duration'])
                                    @php($shippingCost = getWebConfig(name: 'customer_shipping_'.$shippingKey.'_cost') ?? 0)
                                    <div class="col-xl-4">
                                        <div class="bg-white border rounded p-3 h-100">
                                            <label class="d-flex justify-content-between align-items-center gap-3 mb-3">
                                                <span class="fw-semibold text-dark">{{ $shippingOption['label'] }}</span>
                                                <label class="switcher" for="customer-shipping-{{ $shippingKey }}-status">
                                                    <input class="switcher_input" type="checkbox" value="1"
                                                           name="customer_shipping_{{ $shippingKey }}_status"
                                                           id="customer-shipping-{{ $shippingKey }}-status"
                                                           {{ $shippingStatus ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </label>
                                            <div class="form-group">
                                                <label class="form-label">{{ translate('title') }}</label>
                                                <input type="text" class="form-control" name="customer_shipping_{{ $shippingKey }}_title" value="{{ $shippingTitle }}">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">{{ translate('duration') }}</label>
                                                <input type="text" class="form-control" name="customer_shipping_{{ $shippingKey }}_duration" value="{{ $shippingDuration }}">
                                            </div>
                                            <div class="form-group mb-0">
                                                <label class="form-label">{{ translate('cost') }} ({{ getCurrencySymbol(type: 'default') }})</label>
                                                <input type="number" step="0.01" min="0" class="form-control" name="customer_shipping_{{ $shippingKey }}_cost" value="{{ Convert::default($shippingCost) ?? 0 }}">
                                            </div>
                                            @if($shippingKey == 'same_day')
                                                @php($sameDayCutoff = getWebConfig(name: 'customer_shipping_same_day_cutoff') ?? '12:00')
                                                <div class="form-group mb-0 mt-3">
                                                    <label class="form-label">{{ translate('Cutoff_Time') }}</label>
                                                    <input type="time" class="form-control" name="customer_shipping_same_day_cutoff" value="{{ $sameDayCutoff }}">
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                <div>
                                    <h2 class="text-capitalize">{{ translate('Manual_Transfer_Payment_Form') }}</h2>
                                    <p class="mb-0">
                                        {{ translate('this_uses_the_existing_offline_payment_methods_and_can_be_reviewed_from_the_admin_dashboard') }}
                                    </p>
                                </div>
                                <a class="btn btn-outline-primary" href="{{ route('admin.third-party.offline-payment-method.index') }}" target="_blank">
                                    {{ translate('Manage_Offline_Payment_Methods') }}
                                </a>
                            </div>
                        </div>
                        <div class="p-12 p-sm-20 bg-section rounded">
                            @php($manualTransferMethodName = getWebConfig(name: 'customer_manual_transfer_method_name') ?? 'Manual Transfer / Auto Payment Form')
                            <div class="form-group mb-0">
                                <label class="form-label" for="customer_manual_transfer_method_name">
                                    {{ translate('Manual_Transfer_Method_Name') }}
                                </label>
                                <input type="text" name="customer_manual_transfer_method_name" id="customer_manual_transfer_method_name"
                                       class="form-control" value="{{ $manualTransferMethodName }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                        @php($loyaltyPointStatus = getWebConfig(name: 'loyalty_point_status'))
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <h2 class="text-capitalize">
                                        {{ translate('Customer_Loyalty_Point') }}
                                    </h2>
                                    <p class="mb-0">
                                        {{ translate('in_this_settings_admin_can_set_the_rules_for_the_customers_for_earning_and_use_the_loyalty_points') }}
                                    </p>
                                </div>
                                <div>
                                    <label class="switcher" for="customer-loyalty-point">
                                        <input class="switcher_input custom-modal-plugin" type="checkbox" value="1"
                                            name="customer_loyalty_point" id="customer-loyalty-point"
                                            {{ $loyaltyPointStatus ? 'checked' : '' }} data-modal-type="input-change"
                                            data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/loyalty-on.png') }}"
                                            data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/loyalty-off.png') }}"
                                            data-on-title="{{ translate('want_to_Turn_ON_Loyalty_Point') }}"
                                            data-off-title="{{ translate('want_to_Turn_OFF_Loyalty_Point') }}"
                                            data-on-message="<p>{{ translate('if_enabled_the_loyalty_point_option_will_be_available_to_the_customers_account') }}</p>"
                                            data-off-message="<p>{{ translate('if_disabled_loyalty_point_option_will_be_hidden_from_the_customers_account') }}</p>">
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="row g-4">
                                @php($loyaltyPointExchangeRate = getWebConfig(name: 'loyalty_point_exchange_rate'))
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="loyalty_point_exchange_rate">
                                            {{ translate('Equivalent_Points_Needed_to_Redeem') }}
                                            {{ setCurrencySymbol(amount: 1) }}
                                        </label>
                                        <input type="text" class="form-control" name="loyalty_point_exchange_rate"
                                            {{ $loyaltyPointStatus == 0 ? '' : 'required' }}
                                            id="loyalty_point_exchange_rate"
                                            placeholder="{{ translate('ex') . ': ' . '10' }}"
                                            value="{{ $loyaltyPointExchangeRate ?? 0 }}" required>
                                    </div>
                                </div>
                                @php($loyaltyPointMinimumPoint = getWebConfig(name: 'loyalty_point_minimum_point'))
                                <div class="col-xl-4 col-md-6">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="minimum_transfer_point">
                                            {{ translate('minimum_Point_Required_To_Convert') }}
                                            <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="right"
                                                data-bs-title="{{ translate('this_point_is_the_required_amount_which_is_needed_to_convert_the_point_to_the_wallet_balance') }}">
                                                <i class="fi fi-sr-info"></i>
                                            </span>
                                        </label>
                                        <input type="text" class="form-control" name="minimum_transfer_point"
                                            id="minimum_transfer_point" placeholder="{{ translate('ex') . ': ' . '2' }}"
                                            {{ $loyaltyPointStatus == 0 ? '' : 'required' }}
                                            value="{{ $loyaltyPointMinimumPoint ?? 0 }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-12 p-sm-20 bg-section rounded">
                            @php($loyaltyPointEachOrder = getWebConfig(name: 'loyalty_point_for_each_order'))
                            <div class="row g-4 align-items-center">
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <div>
                                        <h4 class="fw-medium text-capitalize">
                                            {{ translate('Earn_Loyalty_Point_on_Each_Order') }}
                                        </h4>
                                        <p class="mb-0">
                                            {{ translate('setup_loyalty_point_percentage_earned_by_customer_based_on_order_amount') }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="switcher" for="customer-loyalty-point-each-order">
                                            <input class="switcher_input custom-modal-plugin" type="checkbox" value="1"
                                                   name="loyalty_point_for_each_order" id="customer-loyalty-point-each-order"
                                                   {{ $loyaltyPointStatus && $loyaltyPointEachOrder ? 'checked' : '' }}
                                                   {{ $loyaltyPointStatus ? '' : 'disabled' }}
                                                   data-modal-type="input-change"
                                                   data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/loyalty-on.png') }}"
                                                   data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/loyalty-off.png') }}"
                                                   data-on-title="{{ translate('want_to_Turn_ON_Loyalty_Point_on_Each_Order') }}"
                                                   data-off-title="{{ translate('want_to_Turn_OFF_Loyalty_Point_on_Each_Order') }}"
                                                   data-on-message="<p>{{ translate('if_enabled_the_loyalty_point_option_will_be_available_to_the_customers_each_order_when_order_place') }}</p>"
                                                   data-off-message="<p>{{ translate('if_disabled_loyalty_point_option_will_be_hidden_from_the_customers_each_order_when_order_place') }}</p>">
                                            <span class="switcher_control"></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    @php($loyaltyPointItemPurchasePoint = getWebConfig(name: 'loyalty_point_item_purchase_point'))
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="">
                                            {{ translate('Earning_Percentage') }} (%)
                                        </label>
                                        <input type="number" class="form-control" name="item_purchase_point"
                                            id="" placeholder="{{ translate('ex') . ': ' . '2' }}"
                                            value="{{ $loyaltyPointItemPurchasePoint ?? 1 }}" min="0" step="any"
                                            {{ $loyaltyPointStatus == 1 ? 'required' : '' }}>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                        @php($refEarningStatus = getWebConfig(name: 'ref_earning_status'))
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <h2 class="text-capitalize">
                                        {{ translate('Customer_Referral_Earning_Settings') }}
                                    </h2>
                                    <p class="mb-0">
                                        {{ translate('allow_customers_to_refer_your_business_to_friends_and_family_using_a_referral_code_and_earn_rewards.') }}
                                    </p>
                                </div>
                                <div>
                                    <label class="switcher" for="ref-earning-status">
                                        <input class="switcher_input custom-modal-plugin" type="checkbox" value="1"
                                            name="ref_earning_status" id="ref-earning-status"
                                            {{ $refEarningStatus ? 'checked' : '' }} data-modal-type="input-change"
                                            data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/referral-earning-on.png') }}"
                                            data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/referral-earning-off.png') }}"
                                            data-on-title="{{ translate('want_to_Turn_ON_Referral_And_Earning_option') }}?"
                                            data-off-title="{{ translate('want_to_Turn_OFF_Referral_And_Earning_option') }}?"
                                            data-on-message="<p>{{ translate('if_enabled_Customers_will_earn_rewards_when_someone_registers_using_their_referral_code.') }}</p>"
                                            data-off-message="<p>{{ translate('if_disabled_Customers_will_no_longer_earn_rewards_for_successful_referrals.') }}</p>">
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="row g-4 align-items-center">
                                @php($refEarningExchangeRate = getWebConfig(name: 'ref_earning_exchange_rate'))

                                <div class="col-lg-4">
                                    <h2 class="text-capitalize">
                                        {{ translate('Who_share_the_Code') }}
                                    </h2>
                                    <p>
                                        {{ translate('set_the_reward_for_the_customer_who_is_sharing_the_code_with_friends_and_family_to_refer_the_app.') }}
                                    </p>
                                </div>
                                <div class="col-lg-8">
                                    <div class="form-group">
                                        <label class="form-label text-capitalize" for="ref_earning_exchange_rate">
                                            {{ translate('earnings_to_Each_Referral') }}
                                            ({{ getCurrencySymbol(type: 'default') }})
                                        </label>
                                        <input type="text" class="form-control" name="ref_earning_exchange_rate"
                                            id="ref_earning_exchange_rate"
                                            placeholder="{{ translate('ex') . ': ' . '10' }}"
                                            {{ $refEarningStatus == 0 ? '' : 'required' }}
                                            value="{{ Convert::default($refEarningExchangeRate) ?? 0 }}">
                                        <p class="text-danger mt-1 mb-0">
                                            {{ translate('must_turn_on_add_fund_to_wallet_option_otherwise_customer_can_not_receive_the_reward_amount') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @php($referralEarningDiscountData = getWebConfig('ref_earning_customer'))
                <div class="card">
                    <div class="card-body">
                        <div class="p-12 p-sm-20 bg-section rounded">
                            <div class="row g-4 align-items-center">
                                <div class="col-lg-4">
                                    <h2 class="text-capitalize">{{ translate('Who_use_the_code') }}</h2>
                                    <p class="mb-0">
                                        {{ translate('set_up_the_discount_that_the_customer_will_receive_when_using_the_refer_code_in_signup_and_taking_their_first_order.') }}
                                    </p>
                                </div>
                                <div class="col-lg-8">
                                    <div class="row g-4">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label class="form-label text-capitalize" for="">
                                                    {{ translate('customer_will_get_discount_on_first_order') }}
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <label
                                                    class="d-flex justify-content-between align-items-center gap-3 border rounded px-3 py-10 bg-white user-select-none">
                                                    <span class="fw-medium text-dark">{{ translate('status') }}</span>
                                                    <label class="switcher" for="ref_earning_discount_status">

                                                        <input class="switcher_input custom-modal-plugin" type="checkbox"
                                                            @if (isset($referralEarningDiscountData['ref_earning_discount_status']) && $referralEarningDiscountData['ref_earning_discount_status'] == 1) checked @endif value="1"
                                                            name="ref_earning_discount_status"
                                                            id="ref_earning_discount_status"
                                                            data-modal-type="input-change"
                                                           data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/referral-earning-on.png') }}"
                                                           data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/referral-earning-off.png') }}"
                                                            data-on-title="{{ translate('are_you_sure_to_turn_on_new_user_referral_reward') }}?"
                                                            data-off-title="{{ translate('are_you_sure_to_turn_off_new_user_referral_reward') }}?"
                                                            data-on-message="<p>{{ translate('customers_will_only_receive_referral_rewards_if_the_referral_and_referral_reward_options_are_enabled.') }}</p>"
                                                            data-off-message="<p>{{ translate('customers_will_only_receive_referral_rewards_if_the_referral_and_referral_reward_options_are_enabled.') }}</p>">
                                                        <span class="switcher_control"></span>
                                                    </label>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group">
                                                <label class="form-label text-capitalize" for="">
                                                    {{ translate('Discount_Amount') }}
                                                    <span class="tooltip-icon" data-bs-toggle="tooltip"
                                                        data-bs-placement="right" data-bs-title="{{ translate('the_value_of_the_discount_the_referred_customer_will_get_on_their_first_order_.') }} {{ translate('you_can_choose_a_fixed_amount_or_a_percentage_.') }}">
                                                        <i class="fi fi-sr-info"></i>
                                                    </span>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" name="discount_amount" id="discount_amount" min="0"
                                                        value="{{ $referralEarningDiscountData['discount_amount'] ?? '' }}"
                                                        class="form-control" placeholder="{{ translate('Ex: 10') }}">
                                                    <div class="input-group-append select-wrapper">
                                                        <select name="discount_type" class="form-select shadow-none">
                                                            <option value="percentage" {{ isset($referralEarningDiscountData['discount_type']) && $referralEarningDiscountData['discount_type'] == 'percentage' ? 'selected' : '' }}>%</option>
                                                            <option value="flat"{{ isset($referralEarningDiscountData['discount_type']) && $referralEarningDiscountData['discount_type'] == 'flat' ? 'selected' : '' }}>$</option>
                                                        </select>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group">
                                                <label class="form-label text-capitalize" for="">
                                                    {{ translate('Validity') }}
                                                    <span class="tooltip-icon" data-bs-toggle="tooltip"
                                                        data-bs-placement="right" data-bs-title="{{ translate('sets_how_long_the_referral_discount_will_remain_active_after_the_customer_signs_up_measured_in_days.') }}">
                                                        <i class="fi fi-sr-info"></i>
                                                    </span>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" name="validity" id="validity"
                                                        value="{{ $referralEarningDiscountData['validity'] ?? '' }}"
                                                        class="form-control" placeholder="{{ translate('Ex: 10') }}" min="1" >
                                                    <div class="input-group-append select-wrapper">
                                                        <?php
                                                            $validityTypes = ['day', 'week', 'month'];
                                                        ?>
                                                        <select class="form-select shadow-none" name="validity_type">
                                                            <option disabled value="">
                                                                {{ translate('select_Type') }}
                                                            </option>
                                                            @foreach ($validityTypes as $type)
                                                                <option value="{{ $type }}"
                                                                    {{ isset($referralEarningDiscountData['validity_type']) && $referralEarningDiscountData['validity_type'] == $type ? 'selected' : '' }}>
                                                                    {{ translate($type) }}
                                                                </option>
                                                            @endforeach
                                                        </select>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end trans3 mt-4">
                <div
                    class="d-flex justify-content-sm-end justify-content-center gap-3 flex-grow-1 flex-grow-sm-0 bg-white action-btn-wrapper trans3">
                    <button type="reset" class="btn btn-secondary px-3 px-sm-4 w-120">{{ translate('Reset') }}</button>
                    <button type="submit" class="btn btn-primary px-3 px-sm-4 text-capitalize">
                        <i class="fi fi-sr-disk"></i>
                        {{ translate('save_information') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
    @include('layouts.admin.partials.offcanvas._customer-settings')
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/new/back-end/js/business-setting.js') }}"></script>
@endpush
