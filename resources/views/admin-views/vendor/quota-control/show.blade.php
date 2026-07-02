@extends('layouts.admin.app')

@section('title', translate('Vendor_Package_Details'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="mb-1">{{ trim($seller->f_name.' '.$seller->l_name) }}</h1>
                <p class="mb-0 text-muted fs-12">{{ $seller->shop?->name }} · {{ $seller->email }} · {{ $seller->phone }}</p>
            </div>
            <a href="{{ route('admin.vendors.quota-control.index') }}" class="btn btn-outline-secondary"><i class="tio-arrow-back"></i> {{ translate('Back') }}</a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="fs-12 text-muted">{{ translate('Insurance') }}</div>
                    <strong class="text-{{ $seller->activeInsurance ? 'success' : 'warning' }}">{{ $seller->activeInsurance ? translate('Active') : translate('Not_Active') }}</strong>
                    <div class="fs-12 text-muted mt-1">{{ $seller->activeInsurance?->paid_at ?? $seller->activeInsurance?->waived_at }}</div>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="fs-12 text-muted">{{ translate('Active_Package') }}</div>
                    <strong>{{ $subscription?->package_name ?? translate('No_Active_Package') }}</strong>
                    <div class="fs-12 text-muted mt-1">{{ $subscription?->expires_at ?? ($subscription ? translate('No_Expiry') : '-') }}</div>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="fs-12 text-muted">{{ translate('Account_Status') }}</div>
                    <strong>{{ translate($seller->status) }}</strong>
                    <div class="fs-12 text-muted mt-1">ID: {{ $seller->id }}</div>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="fs-12 text-muted">{{ translate('Sales_Commission') }}</div>
                    <strong class="{{ $commissionSummary['exempt'] ? 'text-success' : '' }}">{{ number_format($commissionSummary['effective_rate'], 2) }}%</strong>
                    <div class="fs-12 text-muted mt-1">{{ translate($commissionSummary['source']) }}</div>
                </div></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header border-0"><h3 class="mb-0">{{ translate('Vendor_Sales_Commission') }}</h3></div>
            <div class="card-body pt-0">
                <form action="{{ route('admin.vendors.update-setting', $seller->id) }}" method="post" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label d-block">{{ translate('Commission_Source') }}</label>
                        <label class="switcher d-inline-flex align-items-center gap-2 mb-0">
                            <input class="switcher_input" type="checkbox" value="1" name="commission_status" @checked($commissionSummary['custom_enabled'])>
                            <span class="switcher_control"></span>
                            <span>{{ translate('Use_Custom_Commission_For_This_Vendor') }}</span>
                        </label>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ translate('Commission') }} (%)</label>
                        <input type="number" name="commission" class="form-control" min="0" max="100" step="0.01"
                               value="{{ $commissionSummary['custom_enabled'] ? $commissionSummary['custom_rate'] : $commissionSummary['default_rate'] }}" required>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-muted">
                            {{ translate('Enable_custom_commission_and_enter_zero_to_exempt_this_vendor') }}.
                            {{ translate('Disable_it_to_use_the_system_default') }}: {{ number_format($commissionSummary['default_rate'], 2) }}%.
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <button class="btn btn--primary w-100" type="submit"><i class="tio-save"></i> {{ translate('Update') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @if($subscription)
            <div class="card mb-3">
                <div class="card-header border-0"><h3 class="mb-0">{{ translate('Current_Quota_Balances') }}</h3></div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        @foreach([
                            ['label' => 'Product_Listings', 'base' => $subscription->product_limit, 'adjustment' => $subscription->product_adjustment_limit, 'used' => $subscription->used_product_limit],
                            ['label' => 'Featured_Search', 'base' => $subscription->search_promotion_limit, 'adjustment' => $subscription->search_promotion_adjustment_limit, 'used' => $subscription->used_search_promotion_limit],
                            ['label' => 'Homepage_Featured', 'base' => $subscription->homepage_promotion_limit, 'adjustment' => $subscription->homepage_promotion_adjustment_limit, 'used' => $subscription->used_homepage_promotion_limit],
                        ] as $quota)
                            @php($total = max(0, $quota['base'] + $quota['adjustment']))
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <strong>{{ translate($quota['label']) }}</strong>
                                    <div class="d-flex justify-content-between fs-12 mt-2"><span>{{ translate('Base') }}</span><span>{{ $quota['base'] }}</span></div>
                                    <div class="d-flex justify-content-between fs-12"><span>{{ translate('Manual_Adjustment') }}</span><span>{{ $quota['adjustment'] }}</span></div>
                                    <div class="d-flex justify-content-between fs-12"><span>{{ translate('Used') }}</span><span>{{ $quota['used'] }}</span></div>
                                    <div class="d-flex justify-content-between mt-2 border-top pt-2"><strong>{{ translate('Remaining') }}</strong><strong class="text-primary">{{ max(0, $total - $quota['used']) }}</strong></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header border-0"><h3 class="mb-0">{{ translate('Manual_Quota_Adjustment') }}</h3></div>
                <div class="card-body pt-0">
                    <form action="{{ route('admin.vendors.quota-control.adjust', $seller->id) }}" method="post" class="row g-3">
                        @csrf
                        <input type="hidden" name="request_token" value="{{ $requestToken }}">
                        <div class="col-md-3"><label class="form-label">{{ translate('Quota_Type') }}</label><select name="quota_type" class="form-control" required><option value="product">{{ translate('Product_Listings') }}</option><option value="search_promotion">{{ translate('Featured_Search') }}</option><option value="homepage_promotion">{{ translate('Homepage_Featured') }}</option></select></div>
                        <div class="col-md-2"><label class="form-label">{{ translate('Operation') }}</label><select name="operation" class="form-control" required><option value="add">{{ translate('Add') }}</option><option value="deduct">{{ translate('Deduct') }}</option></select></div>
                        <div class="col-md-2"><label class="form-label">{{ translate('Amount') }}</label><input type="number" name="amount" min="1" max="1000000" class="form-control" required></div>
                        <div class="col-md-5"><label class="form-label">{{ translate('Reason') }}</label><input type="text" name="reason" maxlength="1000" class="form-control" required></div>
                        <div class="col-12 text-end"><button class="btn btn--primary" type="submit"><i class="tio-save"></i> {{ translate('Apply_Adjustment') }}</button></div>
                    </form>
                </div>
            </div>
        @else
            <div class="alert alert-soft-warning mb-3">{{ translate('seller_has_no_active_package_to_adjust') }}.</div>
        @endif

        <div class="card mb-3">
            <div class="card-header border-0"><h3 class="mb-0">{{ translate('Promotion_Control') }}</h3></div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle mb-0">
                    <thead class="thead-light"><tr><th>{{ translate('Product') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Expires_At') }}</th><th>{{ translate('Sort_Order') }}</th><th>{{ translate('Control') }}</th></tr></thead>
                    <tbody>
                    @forelse($promotions as $promotion)
                        <tr>
                            <td>{{ $promotion->product?->name ?? '#'.$promotion->product_id }}</td>
                            <td>{{ translate($promotion->promotion_type) }}</td>
                            <td><span class="badge badge-soft-{{ $promotion->status === 'active' ? 'success' : 'secondary' }}">{{ translate($promotion->status) }}</span></td>
                            <td>{{ $promotion->expires_at }}</td>
                            <td>{{ $promotion->sort_order }}</td>
                            <td>
                                @if($promotion->status === 'active' && (!$promotion->expires_at || $promotion->expires_at->isFuture()))
                                    <form action="{{ route('admin.vendors.quota-control.promotions.update', $promotion->id) }}" method="post" class="d-flex gap-2 align-items-center">
                                        @csrf
                                        <input type="number" name="sort_order" value="{{ $promotion->sort_order }}" class="form-control form-control-sm" style="width:90px" required>
                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ translate('Reason') }}" required>
                                        <button name="action" value="update_order" class="btn btn-sm btn-outline-primary" title="{{ translate('Update_Order') }}"><i class="tio-sort"></i></button>
                                        <button name="action" value="cancel" class="btn btn-sm btn-outline-danger" title="{{ translate('Cancel_Promotion') }}"><i class="tio-clear"></i></button>
                                    </form>
                                @else - @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">{{ translate('No_promotion_history') }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header border-0"><h3 class="mb-0">{{ translate('Product_Listing_History') }}</h3></div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle mb-0">
                    <thead class="thead-light"><tr><th>{{ translate('Product') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Duration') }}</th><th>{{ translate('Starts_At') }}</th><th>{{ translate('Expires_At') }}</th><th>{{ translate('Quota_Restored') }}</th></tr></thead>
                    <tbody>
                    @forelse($entitlements as $entitlement)
                        <tr>
                            <td>{{ $entitlement->product?->name ?? '#'.$entitlement->product_id }}</td>
                            <td><span class="badge badge-soft-{{ $entitlement->status === 'active' ? 'success' : 'secondary' }}">{{ translate($entitlement->status) }}</span></td>
                            <td>{{ $entitlement->duration_days }} {{ translate('days') }}</td>
                            <td>{{ $entitlement->starts_at ?? '-' }}</td>
                            <td>{{ $entitlement->expires_at ?? '-' }}</td>
                            <td>{{ $entitlement->quota_restored ? translate('Yes') : translate('No') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">{{ translate('No_product_listing_history') }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header border-0"><h3 class="mb-0">{{ translate('Quota_Transaction_History') }}</h3></div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle mb-0">
                    <thead class="thead-light"><tr><th>{{ translate('Date') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Quota') }}</th><th>{{ translate('Credit') }}</th><th>{{ translate('Debit') }}</th><th>{{ translate('Balance') }}</th><th>{{ translate('Admin') }}</th><th>{{ translate('Reason') }}</th></tr></thead>
                    <tbody>
                    @forelse($transactions as $transaction)
                        <tr><td>{{ $transaction->created_at }}</td><td>{{ translate($transaction->transaction_type) }}</td><td>{{ $transaction->quota_type ? translate($transaction->quota_type) : '-' }}</td><td>{{ $transaction->credit }}</td><td>{{ $transaction->debit }}</td><td>{{ $transaction->balance_after }}</td><td>{{ $transaction->createdByAdmin?->name ?? '-' }}</td><td class="text-wrap">{{ $transaction->note }}</td></tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-4 text-muted">{{ translate('No_transactions_found') }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6"><div class="card h-100"><div class="card-header border-0"><h3 class="mb-0">{{ translate('Package_History') }}</h3></div><div class="table-responsive"><table class="table table-borderless mb-0"><tbody>@forelse($subscriptions as $item)<tr><td>{{ $item->package_name }}</td><td>{{ translate($item->status) }}</td><td>{{ $item->activated_at ?? $item->created_at }}</td></tr>@empty<tr><td class="text-muted">{{ translate('No_package_history') }}</td></tr>@endforelse</tbody></table></div></div></div>
            <div class="col-lg-6"><div class="card h-100"><div class="card-header border-0"><h3 class="mb-0">{{ translate('Insurance_History') }}</h3></div><div class="table-responsive"><table class="table table-borderless mb-0"><tbody>@forelse($insuranceHistory as $item)<tr><td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $item->amount)) }}</td><td>{{ translate($item->status) }}</td><td>{{ $item->paid_at ?? $item->created_at }}</td></tr>@empty<tr><td class="text-muted">{{ translate('No_insurance_history') }}</td></tr>@endforelse</tbody></table></div></div></div>
        </div>
    </div>
@endsection
