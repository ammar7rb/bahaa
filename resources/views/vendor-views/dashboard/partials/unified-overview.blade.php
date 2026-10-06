@php
    $money = static fn ($value) => webCurrencyConverter(amount: (float) $value);
@endphp

<div class="seller-overview mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-1">{{ translate('seller_dashboard_overview') }}</h3>
            <p class="text-muted mb-0">{{ translate('seller_dashboard_overview_hint') }}</p>
        </div>
        <a class="vendor-text-link" href="#seller-financial-due-dates">{{ translate('review_orders_and_due_dates') }}</a>
    </div>

    <div class="row g-2 mb-2">
        <div class="col-md-4">
            <div class="card h-100 remove-card-shadow vendor-summary-primary">
                <div class="card-body">
                    <div class="text-muted mb-2">{{ translate('sales_summary') }}</div>
                    <h2 class="mb-2">{{ $money($sellerOverview['sales']['total_amount']) }}</h2>
                    <div class="d-flex justify-content-between text-muted">
                        <span>{{ translate('orders') }}: <strong class="text-dark">{{ $sellerOverview['sales']['orders_count'] }}</strong></span>
                        <span>{{ translate('sold_units') }}: <strong class="text-dark">{{ $sellerOverview['sales']['sold_units'] }}</strong></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 remove-card-shadow vendor-summary-products">
                <div class="card-body">
                    <div class="text-muted mb-2">{{ translate('products_summary') }}</div>
                    <h2 class="mb-2">{{ $sellerOverview['products']['total'] }}</h2>
                    <div class="d-flex flex-wrap gap-2 fs-12">
                        <span class="badge badge-soft-success">{{ translate('active') }}: {{ $sellerOverview['products']['active'] }}</span>
                        <span class="badge badge-soft-danger">{{ translate('suspended') }}: {{ $sellerOverview['products']['suspended'] }}</span>
                        <span class="badge badge-soft-danger">{{ translate('rejected') }}: {{ $sellerOverview['products']['rejected'] }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 remove-card-shadow vendor-summary-advertisements">
                <div class="card-body">
                    <div class="text-muted mb-2">{{ translate('advertisements_summary') }}</div>
                    <h2 class="mb-2">{{ $sellerOverview['advertisements']['active'] }}</h2>
                    <div class="d-flex flex-wrap gap-2 fs-12">
                        <span class="badge badge-soft-success">{{ translate('active') }}: {{ $sellerOverview['advertisements']['active'] }}</span>
                        <span class="badge badge-soft-warning">{{ translate('pending') }}: {{ $sellerOverview['advertisements']['pending'] }}</span>
                        <span class="badge badge-soft-secondary">{{ translate('stopped') }}: {{ $sellerOverview['advertisements']['stopped'] }}</span>
                        <span class="badge badge-soft-danger">{{ translate('violating') }}: {{ $sellerOverview['advertisements']['violating'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-2">
        @foreach([
            ['label' => 'available_balance', 'value' => $sellerOverview['balances']['available'], 'class' => 'success'],
            ['label' => 'pending_from_platform', 'value' => $sellerOverview['balances']['pending_from_platform'], 'class' => 'warning'],
            ['label' => 'purchase_and_operating_wallet_balance', 'value' => $sellerOverview['balances']['operating'], 'class' => 'primary'],
            ['label' => 'order_insurance_credit', 'value' => $sellerOverview['balances']['order_insurance_credit'], 'class' => 'info'],
            ['label' => 'shipping_due_total', 'value' => $sellerOverview['balances']['shipping_due_total'], 'class' => 'warning'],
        ] as $balance)
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 remove-card-shadow"><div class="card-body">
                    <small class="text-muted">{{ translate($balance['label']) }}</small>
                    <h4 class="text-{{ $balance['class'] }} mb-0 mt-2">{{ $money($balance['value']) }}</h4>
                </div></div>
            </div>
        @endforeach
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 remove-card-shadow"><div class="card-body">
                <small class="text-muted">{{ translate('approved_orders_count') }}</small>
                <h4 class="text-primary mb-0 mt-2">{{ $sellerOverview['sales']['received_orders_count'] }}</h4>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 remove-card-shadow"><div class="card-body">
                <small class="text-muted">{{ translate('open_product_and_stock_issues') }}</small>
                <h4 class="text-danger mb-0 mt-2">{{ $sellerOverview['issues']['open_product_issues'] + $sellerOverview['issues']['stock_out_products'] }}</h4>
            </div></div>
        </div>
    </div>

    <div class="card remove-card-shadow" id="seller-financial-due-dates">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">{{ translate('nearest_financial_due_dates') }}</h4>
                <small class="text-muted">{{ translate('nearest_financial_due_dates_hint') }}</small>
            </div>
            @if($sellerOverview['next_due_at'])
                <span class="badge badge-soft-warning">{{ translate('next_due_date') }}: {{ \Carbon\Carbon::parse($sellerOverview['next_due_at'])->translatedFormat('d M Y') }}</span>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>{{ translate('order') }}</th>
                    <th>{{ translate('sales_amount') }}</th>
                    <th>{{ translate('shipping_entitlement') }}</th>
                    <th>{{ translate('settlement_status') }}</th>
                    <th>{{ translate('sales_due_at') }}</th>
                    <th>{{ translate('shipping_due_at') }}</th>
                    <th>{{ translate('insurance_reuse_due_at') }}</th>
                </tr></thead>
                <tbody>
                @forelse($sellerOverview['due_rows'] as $row)
                    <tr>
                        <td>{{ $row['order_reference'] }}</td>
                        <td>{{ $money($row['sales_amount']) }}</td>
                        <td>
                            @if($row['shipping_responsible'])
                                <span class="d-block">{{ $money($row['shipping_amount']) }}</span>
                                <small class="text-muted">{{ translate('seller_shipping') }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td><span class="badge badge-soft-info">{{ translate($row['settlement_status']) }}</span></td>
                        <td>{{ $row['sales_due_at'] ? \Carbon\Carbon::parse($row['sales_due_at'])->translatedFormat('d M Y') : '-' }}</td>
                        <td>{{ $row['shipping_due_at'] ? \Carbon\Carbon::parse($row['shipping_due_at'])->translatedFormat('d M Y') : '-' }}</td>
                        <td>
                            @if($row['insurance_amount'] > 0)
                                <span class="d-block">{{ $money($row['insurance_amount']) }}</span>
                                <small class="text-muted">{{ translate($row['insurance_status'] ?: 'pending') }}</small>
                                @if($row['insurance_paid_at'])
                                    <small class="d-block text-muted">{{ translate('paid_at') }}: {{ \Carbon\Carbon::parse($row['insurance_paid_at'])->translatedFormat('d M Y') }}</small>
                                @endif
                                @if($row['insurance_reuse_due_at'])
                                    <small class="d-block text-muted">{{ \Carbon\Carbon::parse($row['insurance_reuse_due_at'])->translatedFormat('d M Y') }}</small>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('no_financial_due_dates_found') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
