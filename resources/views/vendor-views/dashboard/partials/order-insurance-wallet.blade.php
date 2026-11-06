@php
    $wallet = $sellerOverview['insurance_wallet'];
    $money = static fn ($value) => webCurrencyConverter(amount: (float) $value);
@endphp

<div class="card mb-3 remove-card-shadow border-info" id="seller-order-insurance-wallet">
    <div class="card-body">
        <div class="row justify-content-between align-items-center g-2 mb-3">
            <div class="col-sm-7">
                <h4 class="d-flex align-items-center text-capitalize gap-10 mb-1">
                    <i class="tio-shield-outlined text-info fs-20"></i>
                    {{ translate('order_insurance_wallet') }}
                </h4>
                <p class="mb-0 text-muted fs-12">
                    {{ translate('seller_insurance_balance_is_separate_and_operating_balance_cannot_pay_insurance') }}
                </p>
            </div>
            <div class="col-sm-5 d-flex flex-wrap justify-content-sm-end gap-2">
                <a class="btn btn-outline-info" href="{{ route('vendor.orders.insurance-pending') }}">
                    <i class="tio-receipt-outlined mr-1"></i>{{ translate('pending_insurance_orders') }}
                </a>
                <a class="btn btn--primary" href="#seller-financial-due-dates">
                    <i class="tio-list mr-1"></i>{{ translate('order_insurance_details') }}
                </a>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-lg-4">
                <div class="card h-100 border-info-subtle bg-soft-info">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center gap-2">
                        <i class="tio-shield-check fs-36 text-info"></i>
                        <h3 class="mb-0 text-info">{{ $money($wallet['available_credit']) }}</h3>
                        <div class="font-weight-bold">{{ translate('reusable_order_insurance_credit') }}</div>
                        <small class="text-muted">{{ translate('insurance_wallet_can_only_pay_order_insurance') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="row g-2 h-100">
                    <div class="col-md-6">
                        <div class="card card-body h-100 justify-content-center">
                            <small class="text-muted">{{ translate('pending_payment') }}</small>
                            <h4 class="text-warning mb-1 mt-2">{{ $money($wallet['pending_amount']) }}</h4>
                            <small>{{ $wallet['pending_count'] }} {{ translate('orders') }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-body h-100 justify-content-center">
                            <small class="text-muted">{{ translate('pending_review') }}</small>
                            <h4 class="text-warning mb-1 mt-2">{{ $money($wallet['under_review_amount']) }}</h4>
                            <small>{{ $wallet['under_review_count'] }} {{ translate('orders') }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-body h-100 justify-content-center">
                            <small class="text-muted">{{ translate('paid_insurance_pending_reuse') }}</small>
                            <h4 class="text-info mb-1 mt-2">{{ $money($wallet['locked_amount']) }}</h4>
                            <small>{{ $wallet['locked_count'] }} {{ translate('orders') }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-body h-100 justify-content-center">
                            <small class="text-muted">{{ translate('next_reuse_date') }}</small>
                            <h5 class="mb-1 mt-2">{{ $wallet['next_reusable_at'] ? \Carbon\Carbon::parse($wallet['next_reusable_at'])->translatedFormat('d M Y') : '-' }}</h5>
                            <small class="text-muted">{{ translate('confiscated_amount') }}: {{ $money($wallet['confiscated_amount']) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
