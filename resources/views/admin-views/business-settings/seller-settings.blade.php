@php use App\Utils\Convert; @endphp
@extends('layouts.admin.app')

@section('title', translate('vendors'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                {{ translate('business_Setup') }}
            </h2>
        </div>
        @include('admin-views.business-settings.business-setup-inline-menu')

        <form action="{{ route('admin.business-settings.vendor-settings.update-vendor-settings') }}" method="post" id="update-vendor-settings-form">
            @csrf

            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3 mb-sm-20">
                        <h3 class="mb-1">{{ translate('General_Setup') }}</h3>
                        <p class="mb-0 fs-12">
                            {{ translate('complete_the_basic_settings_for_vendors') }}
                        </p>
                    </div>
                    <div class="p-12 p-sm-20 bg-section rounded">
                        <div class="row g-4">
                            <div class="col-xl-6 col-md-6">
                                <div
                                    class="d-flex justify-content-between align-items-center gap-3 border rounded p-12 p-sm-3 user-select-none h-100 bg-white">
                                    <span class="d-flex flex-column">
                                        <span class="fw-medium text-dark fs-14 mb-1 text-capitalize">
                                            {{ translate('active_Pos_For_Vendor') }}
                                        </span>
                                        <span class="mb-0 fs-12">
                                            {{ translate('if_enabled_pos_will_be_available_on_the_vendor_panel') }}
                                        </span>
                                    </span>
                                    @php($sellerPos = getWebConfig('seller_pos'))
                                    <label class="switcher" for="vendor-pos">
                                        <input
                                            class="switcher_input "
                                            type="checkbox" value="1" name="seller_pos"
                                            id="vendor-pos"
                                            {{ $sellerPos == 1 ? 'checked' : '' }}
                                            data-modal-type="input-change"
                                            data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/pos-seller-on.png') }}"
                                            data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/pos-seller-off.png') }}"
                                            data-on-title="{{ translate('want_to_Turn_ON_POS_for_Vendor') }}"
                                            data-off-title="{{ translate('want_to_Turn_OFF_POS_for_Vendor') }}"
                                            data-on-message="<p>{{ translate('if_enabled_POS_option_will_be_available_on_the_vendor_panel_and_vendor_app') }}</p>"
                                            data-off-message="<p>{{ translate('if_disabled_POS_option_will_be_hidden_from_the_Vendor_Panel_and_vendor_app') }}</p>">

                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-xl-6 col-md-6">
                                <div
                                    class="d-flex justify-content-between align-items-center gap-3 border rounded p-12 p-sm-3 user-select-none h-100 bg-white">
                                    <span class="d-flex flex-column">
                                        <span class="fw-medium text-dark fs-14 mb-1 text-capitalize">
                                            {{ translate('enable_vendor_registration') }}
                                        </span>
                                        <span class="mb-0 fs-12">
                                            {{ translate('enabling_this_option_allows_users_to_send_requests_to_become_registered') }}
                                        </span>
                                    </span>
                                    @php($vendorRegistration = getWebConfig('seller_registration'))

                                    <label class="switcher" for="vendor-registration">
                                        <input
                                            class="switcher_input "
                                            type="checkbox" value="1" name="seller_registration"
                                            id="vendor-registration"
                                            {{ $vendorRegistration == 1 ? 'checked' : '' }}
                                            data-modal-type="input-change"
                                            data-modal-form="#update-vendor-settings-form"
                                            data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/self-registrations-on.png') }}"
                                            data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/self-registrations-off.png') }}"
                                            data-on-title = "{{ translate('want_to_Turn_ON_Self_Registration') . '?' }}"
                                            data-off-title = "{{ translate('want_to_Turn_OFF_Self_Registration') . '?' }}"
                                            data-on-message = "<p>{{ translate('Enabling_this_option_allows_vendors_to_send_requests_to_become_registered') }}</p>"
                                            data-off-message = "<p>{{ translate('Disabling_this_option_will_not_allow_vendors_to_send_requests_to_become_registered.') }}</p>"
                                            data-on-button-text="{{ translate('turn_on') }}"
                                            data-off-button-text="{{ translate('turn_off') }}">
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-xl-6 col-md-6">
                                <label
                                    class="d-flex justify-content-between align-items-center gap-3 border rounded p-12 p-sm-3 user-select-none h-100 bg-white">
                                    <span class="d-flex flex-column">
                                        <span class="fw-medium text-dark fs-14 mb-1 text-capitalize">
                                            {{ translate('minimum_order_amount') }}
                                        </span>
                                        <span class="mb-0 fs-12">
                                            {{ translate('if_enabled_vendors_can_set_minimum_order_amount_for_their_orders') }}
                                        </span>
                                    </span>

                                    @php($minimumOrderAmountBySeller = getWebConfig('minimum_order_amount_by_seller'))
                                    <label class="switcher" for="minimum-order-amount-by-vendor">
                                        <input
                                            class="switcher_input "
                                            type="checkbox" value="1" name="minimum_order_amount_by_seller"
                                            id="minimum-order-amount-by-vendor"
                                            {{ $minimumOrderAmountBySeller == 1 ? 'checked' : '' }}
                                            data-modal-type="input-change"
                                            data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/minimum-order-amount-feature-on.png') }}"
                                            data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/minimum-order-amount-feature-off.png') }}"
                                            data-on-title="{{ translate('want_to_Turn_ON_the_Set_Minimum_Order_Amount_option') . '?' }}"
                                            data-off-title="{{ translate('want_to_Turn_OFF_the_Set_Minimum_Order_Amount_option') . '?' }}"
                                            data-on-message="<p>{{ translate('if_enabled_Vendors_can_set_minimum_order_amount_for_their_stores_by_themselves') . '.' }}</p>"
                                            data-off-message="<p>{{ translate('if_disabled_Vendors_cannot_set_the_minimum_order_amount_for_their_store_and_the_admin_will_set_it') . '.' }}</p>"
                                            data-on-button-text="{{ translate('turn_on') }}"
                                            data-off-button-text="{{ translate('turn_off') }}">
                                        <span class="switcher_control"></span>
                                    </label>
                                </label>
                            </div>
                            <div class="col-xl-6 col-md-6">
                                <label
                                    class="d-flex justify-content-between align-items-center gap-3 border rounded p-12 p-sm-3 user-select-none h-100 bg-white">
                                    <span class="d-flex flex-column">
                                        <span class="fw-medium text-dark fs-14 mb-1 text-capitalize">
                                            {{ translate('vendor_can_reply_on_review') }}
                                        </span>
                                        <span class="mb-0 fs-12">
                                            {{ translate('enable_this_option_to_allow_vendors_to_reply_to_customer_reviews') }}
                                        </span>
                                    </span>

                                    @php($vendorReviewReplyStatus = getWebConfig('vendor_review_reply_status') ?? 0)
                                    <label class="switcher" for="vendor-review-reply-status">
                                        <input
                                            class="switcher_input "
                                            type="checkbox" value="1" name="vendor_review_reply_status"
                                            id="vendor-review-reply-status"
                                            {{ $vendorReviewReplyStatus == 1 ? 'checked' : '' }}
                                            data-modal-type="input-change"
                                            data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/vendor-review-reply-on.png') }}"
                                            data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/vendor-review-reply-off.png') }}"
                                            data-on-title="{{ translate('By_Turning_On_Vendor_Review_Reply_Option') . '?' }}"
                                            data-off-title="{{ translate('By_Turning_Off_Vendor_Review_Reply_Option') . '?' }}"
                                            data-on-message="<p>{{ translate('If_you_turn_on_this_seller_will_be_able_to_reply_of_review') . '.' }}</p>"
                                            data-off-message="<p>{{ translate('If_you_turn_off_this_seller_will_not_be_able_to_reply_of_review') . '.' }}</p>"
                                            data-on-button-text="{{ translate('turn_on') }}"
                                            data-off-button-text="{{ translate('turn_off') }}">
                                        <span class="switcher_control"></span>
                                    </label>
                                </label>
                            </div>
                            <div class="col-xl-6 col-md-6">
                                <label
                                    class="d-flex justify-content-between align-items-center gap-3 border rounded p-12 p-sm-3 user-select-none h-100 bg-white">
                                    <span class="d-flex flex-column">
                                        <span class="fw-medium text-dark fs-14 mb-1 text-capitalize">
                                            {{ translate('Vendor_can_edit_order') }}
                                        </span>
                                        <span class="mb-0 fs-12">
                                            {{ translate('enable_this_option_to_allow_vendors_can_be_edit_order') }}
                                        </span>
                                    </span>

                                    @php($vendorCanEditOrderStatus = getWebConfig('vendor_can_edit_order') ?? 0)
                                    <label class="switcher" for="vendor-can-edit-order">
                                        <input
                                            class="switcher_input "
                                            type="checkbox" value="1" name="vendor_can_edit_order"
                                            id="vendor-can-edit-order"
                                            {{ $vendorCanEditOrderStatus == 1 ? 'checked' : '' }}
                                            data-modal-type="input-change"
                                            data-on-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/vendor-review-reply-on.png') }}"
                                            data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/vendor-review-reply-off.png') }}"
                                            data-on-title="{{ translate('By_Turning_On_Vendor_Edit_Order_Option') . '?' }}"
                                            data-off-title="{{ translate('By_Turning_Off_Vendor_Edit_Order_Option') . '?' }}"
                                            data-on-message="<p>{{ translate('If_you_turn_on_this_vendor_will_be_able_to_edit_his_orders') . '.' }}</p>"
                                            data-off-message="<p>{{ translate('If_you_turn_off_this_vendor_will_not_be_able_to_edit_his_orders') . '.' }}</p>"
                                            data-on-button-text="{{ translate('turn_on') }}"
                                            data-off-button-text="{{ translate('turn_off') }}">
                                        <span class="switcher_control"></span>
                                    </label>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" id="seller-order-insurance-settings">
                <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                    @php($sellerOrderInsuranceStatus = getWebConfig(name: 'seller_order_insurance_status') ?? 0)
                    @php($sellerOrderInsurancePercentage = getWebConfig(name: 'seller_order_insurance_percentage') ?? 0)
                    @php($sellerOrderInsuranceCalculationType = getWebConfig(name: 'seller_order_insurance_calculation_type') ?? 'percentage')
                    @php($sellerOrderInsuranceCalculationValue = getWebConfig(name: 'seller_order_insurance_calculation_value') ?? $sellerOrderInsurancePercentage)
                    @php($sellerOrderInsurancePendingDays = getWebConfig(name: 'seller_order_insurance_pending_days') ?? 3)
                    @php($sellerOrderInsuranceReuseAfterDays = getWebConfig(name: 'seller_order_insurance_reuse_after_days') ?? 90)
                    @php($sellerOrderInsuranceExpiryAction = getWebConfig(name: 'seller_order_insurance_expiry_action') ?? 'admin_review')
                    <div class="p-12 p-sm-20 bg-section rounded">
                        <div class="d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <h3 class="mb-1 text-capitalize">{{ translate('Seller_Order_Insurance') }}</h3>
                                <p class="mb-0 fs-12">{{ translate('seller_order_insurance_configuration_note') }}</p>
                            </div>
                            <label class="switcher" for="seller-order-insurance-status">
                                <input class="switcher_input" type="checkbox" value="1" name="seller_order_insurance_status" id="seller-order-insurance-status" {{ $sellerOrderInsuranceStatus == 1 ? 'checked' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                    </div>
                    <div class="p-12 p-sm-20 bg-section rounded">
                        <div class="row g-4">
                            <div class="col-xl-3 col-md-6">
                                <label class="form-label" for="seller-order-insurance-calculation-type">{{ translate('Insurance_Calculation_Type') }}</label>
                                <select class="form-select" name="seller_order_insurance_calculation_type" id="seller-order-insurance-calculation-type">
                                    <option value="percentage" @selected($sellerOrderInsuranceCalculationType === 'percentage')>{{ translate('percentage') }}</option>
                                    <option value="fixed" @selected($sellerOrderInsuranceCalculationType === 'fixed')>{{ translate('fixed_amount') }}</option>
                                </select>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <label class="form-label" for="seller-order-insurance-calculation-value">{{ translate('Insurance_Calculation_Value') }}</label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="seller_order_insurance_calculation_value" id="seller-order-insurance-calculation-value" value="{{ $sellerOrderInsuranceCalculationValue }}">
                            </div>
                            <div class="col-xl-4 col-md-6">
                                <label class="form-label" for="seller-order-insurance-pending-days">{{ translate('Seller_Order_Insurance_Pending_Days') }}</label>
                                <input type="number" min="1" max="90" class="form-control" name="seller_order_insurance_pending_days" id="seller-order-insurance-pending-days" value="{{ $sellerOrderInsurancePendingDays }}">
                            </div>
                            <div class="col-xl-4 col-md-6">
                                <label class="form-label" for="seller-order-insurance-reuse-after-days">{{ translate('Seller_Order_Insurance_Reuse_After_Days') }}</label>
                                <input type="number" min="1" max="365" class="form-control" name="seller_order_insurance_reuse_after_days" id="seller-order-insurance-reuse-after-days" value="{{ $sellerOrderInsuranceReuseAfterDays }}">
                                <small class="text-muted">{{ translate('Seller_Order_Insurance_Reuse_Credit_Only_Note') }}</small>
                            </div>
                            <div class="col-xl-4 col-md-6">
                                <label class="form-label" for="seller-order-insurance-expiry-action">{{ translate('Seller_Order_Insurance_Expiry_Action') }}</label>
                                <select class="form-select" name="seller_order_insurance_expiry_action" id="seller-order-insurance-expiry-action">
                                    <option value="admin_review" @selected($sellerOrderInsuranceExpiryAction === 'admin_review')>{{ translate('Admin_Manual_Review') }}</option>
                                    <option value="suspend" @selected($sellerOrderInsuranceExpiryAction === 'suspend')>{{ translate('Move_To_Suspended_Orders') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
                            <p class="text-muted fs-12 mb-0">{{ translate('seller_order_insurance_details_hidden_notice') }}</p>
                            @if(Route::has('admin.insurance-center.index'))
                                <a href="{{ route('admin.insurance-center.index', ['tab' => 'sellers', 'section' => 'rules']) }}" class="btn btn-outline-primary btn-sm">{{ translate('Advanced_Insurance_Rules') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" id="seller-settlement-settings">
                <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                    @php($settlementHoldDays = getWebConfig(name: 'seller_settlement_hold_days') ?? 45)
                    @php($settlementWorkingDays = getWebConfig(name: 'seller_settlement_working_days'))
                    @php($settlementWorkingDays = is_string($settlementWorkingDays) ? json_decode($settlementWorkingDays, true) : $settlementWorkingDays)
                    @php($settlementWorkingDays = is_array($settlementWorkingDays) ? $settlementWorkingDays : [0, 1, 2, 3, 4])
                    @php($settlementHolidays = getWebConfig(name: 'seller_settlement_holidays'))
                    @php($settlementHolidays = is_string($settlementHolidays) ? json_decode($settlementHolidays, true) : $settlementHolidays)
                    <div>
                        <h3 class="mb-1 text-capitalize">{{ translate('Seller_Settlement_Schedule') }}</h3>
                        <p class="mb-0 fs-12">{{ translate('seller_settlement_settings_snapshot_note') }}</p>
                    </div>
                    <div class="p-12 p-sm-20 bg-section rounded">
                        <div class="row g-4">
                            <div class="col-xl-4 col-md-6">
                                <label class="form-label" for="seller-settlement-hold-days">{{ translate('Settlement_Hold_Working_Days') }}</label>
                                <input type="number" min="0" max="45" class="form-control" name="seller_settlement_hold_days" id="seller-settlement-hold-days" value="{{ old('seller_settlement_hold_days', $settlementHoldDays) }}" required>
                                <small class="text-muted">{{ translate('maximum_45_working_days') }}</small>
                            </div>
                            <div class="col-xl-8 col-md-6">
                                <label class="form-label d-block">{{ translate('Settlement_Working_Days') }}</label>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach([0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'] as $dayValue => $dayLabel)
                                        <label class="form-check d-flex align-items-center gap-2 mb-0">
                                            <input class="form-check-input" type="checkbox" name="seller_settlement_working_days[]" value="{{ $dayValue }}" @checked(in_array($dayValue, old('seller_settlement_working_days', $settlementWorkingDays), true))>
                                            <span>{{ translate($dayLabel) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="seller-settlement-holidays">{{ translate('Settlement_Holidays') }}</label>
                                <textarea class="form-control" rows="3" name="seller_settlement_holidays" id="seller-settlement-holidays" placeholder="2026-01-07, 2026-04-13">{{ old('seller_settlement_holidays', is_array($settlementHolidays) ? implode(', ', $settlementHolidays) : '') }}</textarea>
                                <small class="text-muted">{{ translate('settlement_holidays_yyyy_mm_dd_hint') }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="seller-settlement-pre-due-alert-days">{{ translate('settlement_pre_due_alert_days') }}</label>
                                <input type="number" min="1" max="30" class="form-control" name="seller_settlement_pre_due_alert_days" id="seller-settlement-pre-due-alert-days" value="{{ old('seller_settlement_pre_due_alert_days', getWebConfig(name: 'seller_settlement_pre_due_alert_days') ?? 5) }}" required>
                                <small class="text-muted">{{ translate('settlement_pre_due_alert_days_hint') }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="seller-settlement-overdue-escalation-days">{{ translate('settlement_overdue_escalation_days') }}</label>
                                <input type="number" min="1" max="30" class="form-control" name="seller_settlement_overdue_escalation_days" id="seller-settlement-overdue-escalation-days" value="{{ old('seller_settlement_overdue_escalation_days', getWebConfig(name: 'seller_settlement_overdue_escalation_days') ?? 2) }}" required>
                                <small class="text-muted">{{ translate('settlement_timezone_snapshot_hint') }}: {{ getWebConfig(name: 'timezone') ?: config('app.timezone') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" id="vendor-insurance-settings">
                <div class="card-body d-flex flex-column gap-3 gap-sm-20">
                    @php($sellerInsuranceStatus = getWebConfig(name: 'seller_insurance_status') ?? 0)
                    <div class="p-12 p-sm-20 bg-section rounded">
                        <div class="d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <h3 class="mb-1 text-capitalize">{{ translate('Vendor_One_Time_Insurance') }}</h3>
                                <p class="mb-0 fs-12">
                                    {{ translate('require_each_vendor_to_pay_insurance_once_before_being_allowed_to_publish_products') }}.
                                </p>
                            </div>
                            <label class="switcher" for="seller-insurance-status">
                                <input class="switcher_input" type="checkbox" value="1"
                                       name="seller_insurance_status" id="seller-insurance-status"
                                       {{ $sellerInsuranceStatus == 1 ? 'checked' : '' }}
                                       data-modal-type="input-change"
                                       data-modal-form="#update-vendor-settings-form"
                                       data-on-title="{{ translate('want_to_turn_on_vendor_insurance') }}?"
                                       data-off-title="{{ translate('want_to_turn_off_vendor_insurance') }}?"
                                       data-on-message="<p>{{ translate('vendors_will_need_an_approved_insurance_payment_before_publishing_products') }}.</p>"
                                       data-off-message="<p>{{ translate('vendor_insurance_will_not_be_required_for_product_publishing') }}.</p>">
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                    </div>

                    <div class="p-12 p-sm-20 bg-section rounded">
                        <div class="row g-4">
                            @php($sellerInsuranceAmount = getWebConfig(name: 'seller_insurance_amount') ?? 0)
                            <div class="col-xl-6 col-md-6">
                                <div class="form-group mb-0">
                                    <label class="form-label" for="seller-insurance-amount">
                                        {{ translate('Insurance_Amount') }} ({{ getCurrencySymbol(type: 'default') }})
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" step="0.01" min="0.01" class="form-control"
                                           name="seller_insurance_amount" id="seller-insurance-amount"
                                           value="{{ old('seller_insurance_amount', Convert::default($sellerInsuranceAmount) ?? 0) }}">
                                    <div class="text-muted fs-12 mt-2">
                                        {{ translate('this_amount_is_charged_once_and_is_separate_from_vendor_packages_and_sales_commission') }}.
                                    </div>
                                </div>
                            </div>

                            @php($repaymentAfterForfeiture = getWebConfig(name: 'seller_insurance_repayment_after_forfeiture') ?? 1)
                            <div class="col-xl-6 col-md-6">
                                <div class="d-flex justify-content-between align-items-center gap-3 border rounded p-12 p-sm-3 user-select-none h-100 bg-white">
                                    <span class="d-flex flex-column">
                                        <span class="fw-medium text-dark fs-14 mb-1 text-capitalize">
                                            {{ translate('Require_New_Insurance_After_Forfeiture') }}
                                        </span>
                                        <span class="mb-0 fs-12">
                                            {{ translate('when_an_admin_forfeits_the_current_insurance_the_vendor_must_pay_a_new_one_before_publishing_again') }}.
                                        </span>
                                    </span>
                                    <label class="switcher" for="seller-insurance-repayment-after-forfeiture">
                                        <input class="switcher_input" type="checkbox" value="1"
                                               name="seller_insurance_repayment_after_forfeiture"
                                               id="seller-insurance-repayment-after-forfeiture"
                                               {{ $repaymentAfterForfeiture == 1 ? 'checked' : '' }}>
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mb-0 d-flex gap-2 align-items-start" role="alert">
                        <i class="fi fi-sr-info mt-1"></i>
                        <span class="fs-12">
                            {{ translate('online_payments_activate_after_verified_gateway_callback_while_every_offline_payment_requires_admin_review') }}.
                            <a href="{{ route('admin.payment-reviews.index', ['audience' => 'vendor', 'kind' => 'insurance']) }}" class="text-decoration-underline fw-semibold">
                                {{ translate('Open_Offline_Reviews') }}
                            </a>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="mb-3 mb-sm-20">
                        <h3 class="mb-1 text-capitalize">{{ translate('forget_password_setup') }}</h3>
                        <p class="mb-0 fs-12">
                            {{ translate('setup_how_vendors_can_recover_their_forgotten_passwords') }}.
                        </p>
                    </div>
                    <div class="p-12 p-sm-20 bg-section rounded">
                        <div class="form-group mb-20px">
                            <label class="form-label mb-3 text-capitalize" for="">
                                {{ translate('select_verification_option') }}
                            </label>
                            <div class="bg-white p-3 rounded">
                                <div class="row g-4">
                                    <div class="col-xl-6 col-md-6">
                                        <div class="form-check d-flex gap-3">
                                            <input class="form-check-input radio--input radio--input_lg" type="radio"
                                                name="vendor_forgot_password_method" id="verification_by_email"
                                                value="email"
                                                {{ getWebconfig(name: 'vendor_forgot_password_method') == 'email' ? 'checked' : '' }}>
                                            <div class="flex-grow-1">
                                                <label for="verification_by_email"
                                                    class="form-label text-dark fw-semibold mb-1">
                                                    {{ translate('Email') }}
                                                </label>
                                                <p class="fs-12 mb-2">
                                                    {{ translate('vendor_will_recover_his_password_though_email') }}
                                                </p>
                                                <div
                                                    class="bg-warning bg-opacity-10 fs-12 px-12 py-10 text-dark rounded d-flex gap-2 align-items-center">
                                                    <i class="fi fi-sr-bulb text-warning fs-16"></i>
                                                    <span>
                                                        {{ translate('to_configure_email_for_this_system_visit') }}
                                                        <a href="{{ route('admin.third-party.mail.index') }}" target="_blank"
                                                            class="text-decoration-underline fw-semibold">{{ translate('Email_Configuration') }}</a>
                                                        {{ translate('page') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-md-6">
                                        <div class="form-check d-flex gap-3">
                                            <input class="form-check-input radio--input radio--input_lg" type="radio"
                                                value="phone" name="vendor_forgot_password_method"
                                                id="verification_by_phone"
                                                {{ getWebconfig(name: 'vendor_forgot_password_method') == 'phone' ? 'checked' : '' }}>
                                            <div class="flex-grow-1">
                                                <label for="verification_by_phone"
                                                    class="form-label text-dark fw-semibold mb-1">
                                                    {{ translate('Phone') }} ({{ translate('OTP') }})
                                                </label>
                                                <p class="fs-12 mb-2">
                                                    {{ translate('vendor_will_recover_his_password_though_otp') }}
                                                </p>
                                                <div
                                                    class="bg-warning bg-opacity-10 fs-12 px-12 py-10 text-dark rounded d-flex gap-2 align-items-center">
                                                    <i class="fi fi-sr-bulb text-warning fs-16"></i>
                                                    <span>
                                                        {{ translate('to_configure_phone_for_this_system_visit') }}
                                                        <a href="{{ route('admin.third-party.sms-module') }}" target="_blank"
                                                            class="text-decoration-underline fw-semibold">
                                                            {{ translate('OTP_Configuration') }}
                                                        </a>
                                                        {{ translate('page') }}
                                                    </span>
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
                <div class="d-flex justify-content-sm-end justify-content-center gap-3 flex-grow-1 flex-grow-sm-0 bg-white action-btn-wrapper trans3">
                    <button type="reset" class="btn btn-secondary px-3 px-sm-4 w-120">{{ translate('reset') }}</button>
                    <button type="submit" class="btn btn-primary px-3 px-sm-4">
                        <i class="fi fi-sr-disk"></i>
                        {{ translate('save_information') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
    @include("layouts.admin.partials.offcanvas._seller-settings")
@endsection
