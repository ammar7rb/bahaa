@php
    $overviewMoney = static fn ($value) => webCurrencyConverter(amount: (float) $value);
    $sellerShop = $seller?->shop;
    $sellerStatus = (string) ($seller?->status ?? '');
@endphp

<style>
    .seller-admin-profile-card .seller-admin-kpi { background: var(--bs-light, #f8f9fa); border: 1px solid rgba(13, 110, 253, .10); border-radius: .65rem; min-height: 78px; }
    .seller-admin-profile-card .seller-admin-action { min-width: 108px; }
    .seller-admin-profile-card .seller-admin-section-title { letter-spacing: .01em; }
    .seller-admin-profile-card .seller-admin-identity { border: 1px solid rgba(13, 110, 253, .12); border-radius: .8rem; background: linear-gradient(135deg, rgba(13,110,253,.04), rgba(255,255,255,.96)); }
    .seller-admin-profile-card .seller-admin-avatar { width: 76px; height: 76px; object-fit: cover; border: 3px solid rgba(13,110,253,.12); }
    .seller-admin-profile-card .seller-admin-data { border-inline-start: 1px solid rgba(108,117,125,.18); }
    .seller-admin-profile-card .seller-admin-wallet { border: 1px solid rgba(108,117,125,.18); border-radius: .8rem; background: #fff; }
    .seller-admin-profile-card .seller-admin-wallet-card { min-height: 94px; border: 1px solid rgba(108,117,125,.14); border-radius: .65rem; background: var(--bs-light, #f8f9fa); }
</style>

<div class="card mb-4 border-primary-subtle seller-admin-profile-card">
    <div class="card-header bg-light">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="d-flex align-items-center gap-3">
                <img class="rounded-circle seller-admin-avatar" alt="{{ translate('store') }}"
                     src="{{ getStorageImages(path: $sellerShop?->image_full_url, type: 'shop') }}">
                <div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h3 class="mb-0">{{ $sellerShop?->name ?? $seller?->f_name . ' ' . $seller?->l_name }}</h3>
                        <span class="badge badge-soft-{{ $sellerStatus === 'approved' ? 'success' : ($sellerStatus === 'suspended' || $sellerStatus === 'rejected' ? 'danger' : 'warning') }}">{{ translate($sellerStatus ?: 'pending') }}</span>
                    </div>
                    <div class="text-muted mt-1">{{ translate('seller_administrative_profile') }} · M{{ $seller?->id }}</div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a class="btn btn-outline-secondary btn-sm seller-admin-action" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'order']) }}"><i class="fi fi-rr-shopping-cart"></i> {{ translate('order') }}</a>
                <a class="btn btn-outline-secondary btn-sm seller-admin-action" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'product']) }}"><i class="fi fi-rr-box"></i> {{ translate('product') }}</a>
                <a class="btn btn-outline-secondary btn-sm seller-admin-action" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'transaction']) }}"><i class="fi fi-rr-receipt"></i> {{ translate('transaction') }}</a>
                @if(\App\Utils\Helpers::module_permission_check('vendor_password_reset'))<button type="button" class="btn btn-outline-primary btn-sm seller-admin-action"
                        data-bs-toggle="modal" data-bs-target="#resetVendorPasswordModal">
                    <i class="fi fi-rr-key"></i> {{ translate('reset_password') }}
                </button>@endif
            </div>
        </div>
        <div class="seller-admin-identity p-3 mt-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-3"><small class="text-muted d-block">{{ translate('owner_name') }}</small><strong>{{ trim(($seller?->f_name ?? '') . ' ' . ($seller?->l_name ?? '')) ?: '-' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">{{ translate('email') }}</small><strong class="text-break">{{ $seller?->email ?: '-' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">{{ translate('phone') }}</small><strong>{{ $seller?->phone ?: '-' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">{{ translate('joined_at') }}</small><strong>{{ $seller?->created_at?->format('d M Y, h:i A') ?? '-' }}</strong></div>
                <div class="col-12"><small class="text-muted d-block">{{ translate('address') }}</small><span class="text-break">{{ $sellerShop?->address ?: ($seller?->shop_address ?: '-') }}</span></div>
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
            @if ($sellerStatus === 'pending')
                <form action="{{ route('admin.vendors.updateStatus') }}" method="POST" class="d-inline-block" id="administrative-reject-form">@csrf<input type="hidden" name="id" value="{{ $seller->id }}"><input type="hidden" name="status" value="rejected"><button type="button" class="btn btn-danger btn-sm form-alert" data-message="{{ translate('want_to_reject_this_vendor') }}?" data-id="administrative-reject-form">{{ translate('reject') }}</button></form>
                <form action="{{ route('admin.vendors.updateStatus') }}" method="POST" class="d-inline-block" id="administrative-approve-form">@csrf<input type="hidden" name="id" value="{{ $seller->id }}"><input type="hidden" name="status" value="approved"><button type="button" class="btn btn-success btn-sm form-alert" data-message="{{ translate('want_to_approve_this_vendor') }}?" data-id="administrative-approve-form">{{ translate('approve') }}</button></form>
            @elseif ($sellerStatus === 'approved')
                <form action="{{ route('admin.vendors.updateStatus') }}" method="POST" class="d-inline-block" id="administrative-suspend-form">@csrf<input type="hidden" name="id" value="{{ $seller->id }}"><input type="hidden" name="status" value="suspended"><button type="button" class="btn btn-danger btn-sm form-alert" data-message="{{ translate('want_to_suspend_this_vendor') }}?" data-id="administrative-suspend-form">{{ translate('suspend_this_vendor') }}</button></form>
            @elseif (in_array($sellerStatus, ['suspended', 'rejected'], true))
                <form action="{{ route('admin.vendors.updateStatus') }}" method="POST" class="d-inline-block" id="administrative-active-form">@csrf<input type="hidden" name="id" value="{{ $seller->id }}"><input type="hidden" name="status" value="approved"><button type="button" class="btn btn-success btn-sm form-alert" data-message="{{ translate('want_to_active_this_vendor') }}?" data-id="administrative-active-form">{{ translate('active') }}</button></form>
            @endif
        </div>
    </div>

    <div class="card-body">

        @if(isset($sellerOverview))
            <h5 class="mb-2 seller-admin-section-title">{{ translate('seller_dashboard_overview') }}</h5>
            <div class="row g-2 mb-4">
                <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted">{{ translate('sales_summary') }}</small><h4 class="mb-0 mt-2">{{ $overviewMoney($sellerOverview['sales']['total_amount']) }}</h4><small>{{ $sellerOverview['sales']['orders_count'] }} {{ translate('orders') }}</small></div></div>
                <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted">{{ translate('products_summary') }}</small><h4 class="mb-0 mt-2">{{ $sellerOverview['products']['total'] }}</h4><small>{{ translate('active') }}: {{ $sellerOverview['products']['active'] }}</small></div></div>
                <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted">{{ translate('advertisements_summary') }}</small><h4 class="mb-0 mt-2">{{ $sellerOverview['advertisements']['active'] }}</h4><small>{{ translate('violating') }}: {{ $sellerOverview['advertisements']['violating'] }}</small></div></div>
                
            </div>

            <div class="seller-admin-wallet p-3 mb-4" id="seller-vendor-wallet">
                <div class="d-flex align-items-center gap-2 mb-3"><img width="24" src="{{ dynamicAsset(path: 'public/assets/back-end/img/admin-wallet.png') }}" alt=""><div><h5 class="mb-0">{{ translate('vendor_Wallet') }}</h5><small class="text-muted">{{ translate('seller_dashboard_overview_hint') }}</small></div></div>
                <div class="row g-2">@foreach([['label'=>'available_balance','value'=>$sellerOverview['balances']['available']],['label'=>'pending_from_platform','value'=>$sellerOverview['balances']['pending_from_platform']],['label'=>'operating_balance','value'=>$sellerOverview['balances']['operating']],['label'=>'pending_withdrawal','value'=>$sellerOverview['balances']['pending_withdrawal']]] as $balance)<div class="col-sm-6 col-lg-3"><div class="seller-admin-wallet-card p-3 h-100"><small class="text-muted d-block">{{ translate($balance['label']) }}</small><h5 class="mb-0 mt-2">{{ $overviewMoney($balance['value']) }}</h5></div></div>@endforeach</div>
            </div>

            

            <div class="table-responsive border rounded"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>{{ translate('order') }}</th><th>{{ translate('sales_amount') }}</th><th>{{ translate('shipping_entitlement') }}</th><th>{{ translate('sales_due_at') }}</th><th>{{ translate('shipping_due_at') }}</th></tr></thead><tbody>@forelse($sellerOverview['due_rows'] as $row)<tr><td><a href="{{ route('admin.vendors.order-details', ['order_id' => $row['order_id'], 'vendor_id' => $seller->id]) }}">{{ $row['order_reference'] }}</a></td><td>{{ $overviewMoney($row['sales_amount']) }}</td><td>{{ $row['shipping_responsible'] ? $overviewMoney($row['shipping_amount']) : '-' }}</td><td>{{ $row['sales_due_at'] ? \Carbon\Carbon::parse($row['sales_due_at'])->translatedFormat('d M Y') : '-' }}</td><td>{{ $row['shipping_due_at'] ? \Carbon\Carbon::parse($row['shipping_due_at'])->translatedFormat('d M Y') : '-' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">{{ translate('no_financial_due_dates_found') }}</td></tr>@endforelse</tbody></table></div>
        @endif
    </div>
</div>

@if(\App\Utils\Helpers::module_permission_check('vendor_password_reset'))
<div class="modal fade" id="resetVendorPasswordModal" tabindex="-1"
     aria-labelledby="resetVendorPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('admin.vendors.reset-password', $seller->id) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="resetVendorPasswordModalLabel">{{ translate('reset_password') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small">
                    {{ translate('admin_password_reset_notice') }}
                </div>
                <div class="mb-3">
                    <label class="form-label" for="vendor-reset-password">{{ translate('new_password') }}</label>
                    <input class="form-control" id="vendor-reset-password" name="password" type="password"
                           minlength="8" required autocomplete="new-password">
                </div>
                <div>
                    <label class="form-label" for="vendor-reset-password-confirmation">{{ translate('confirm_password') }}</label>
                    <input class="form-control" id="vendor-reset-password-confirmation" name="password_confirmation"
                           type="password" minlength="8" required autocomplete="new-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ translate('reset_password') }}</button>
            </div>
        </form>
    </div>
</div>
@endif
