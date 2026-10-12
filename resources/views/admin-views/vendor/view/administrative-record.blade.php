@php
    $overviewMoney = static fn ($value) => webCurrencyConverter(amount: (float) $value);
    $insuranceWallet = $sellerOverview['insurance_wallet'] ?? [];
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
    .seller-admin-profile-card .seller-admin-wallet.insurance { border-color: rgba(13, 202, 240, .35); }
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
                    <small class="text-muted">{{ translate('activation_case_private_admin_workspace') }}</small>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                @if($activationCase)
                    <a class="btn btn-primary btn-sm seller-admin-action" href="{{ route('admin.activation-center.show', $activationCase) }}"><i class="fi fi-rr-comment-alt"></i> {{ translate('activation_conversation') }}</a>
                @endif
                <a class="btn btn-outline-secondary btn-sm seller-admin-action" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'order']) }}"><i class="fi fi-rr-shopping-cart"></i> {{ translate('order') }}</a>
                <a class="btn btn-outline-secondary btn-sm seller-admin-action" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'product']) }}"><i class="fi fi-rr-box"></i> {{ translate('product') }}</a>
                <a class="btn btn-outline-secondary btn-sm seller-admin-action" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'transaction']) }}"><i class="fi fi-rr-receipt"></i> {{ translate('transaction') }}</a>
                <button type="button" class="btn btn-outline-primary btn-sm seller-admin-action"
                        data-bs-toggle="modal" data-bs-target="#resetVendorPasswordModal">
                    <i class="fi fi-rr-key"></i> {{ translate('reset_password') }}
                </button>
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
        @if($activationCase)
            <div class="row g-2 mb-4">
                <div class="col-sm-6 col-lg-3"><div class="seller-admin-kpi p-3 h-100"><small class="text-muted d-block">{{ translate('account_activation') }}</small><span class="badge badge-soft-{{ $activationCase->status === 'approved' ? 'success' : ($activationCase->status === 'rejected' ? 'danger' : 'warning') }} mt-2">{{ translate($activationCase->status) }}</span></div></div>
                <div class="col-sm-6 col-lg-3"><div class="seller-admin-kpi p-3 h-100"><small class="text-muted d-block">{{ translate('activation_case') }}</small><strong class="d-block mt-2">#{{ $activationCase->id }}</strong></div></div>
                <div class="col-sm-6 col-lg-3"><div class="seller-admin-kpi p-3 h-100"><small class="text-muted d-block">{{ translate('joined_at') }}</small><strong class="d-block mt-2">{{ $seller?->created_at?->format('d M Y, h:i A') ?? '-' }}</strong></div></div>
                <div class="col-sm-6 col-lg-3"><div class="seller-admin-kpi p-3 h-100"><small class="text-muted d-block">{{ translate('assigned_employee') }}</small><strong class="d-block mt-2">{{ $activationCase->assignedAdmin?->name ?? translate('unassigned') }}</strong></div></div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-lg-7"><h5 class="mb-2">{{ translate('verified_profile_information') }}</h5><div class="table-responsive border rounded"><table class="table table-sm align-middle mb-0"><thead><tr><th>{{ translate('field') }}</th><th>{{ translate('value') }}</th><th>{{ translate('verification') }}</th></tr></thead><tbody>@forelse($activationCase->fields as $field)<tr><td>{{ $field->label }}</td><td class="text-break">{{ $field->value ?: '-' }}</td><td><span class="badge badge-soft-{{ $field->is_verified ? 'success' : 'warning' }}">{{ translate($field->is_verified ? 'verified' : 'pending') }}</span></td></tr>@empty<tr><td colspan="3" class="text-center text-muted">{{ translate('no_profile_information_added') }}</td></tr>@endforelse</tbody></table></div></div>
                <div class="col-lg-5"><h5 class="mb-2">{{ translate('private_verification_documents') }}</h5><div class="border rounded p-3 h-100"><div class="d-flex justify-content-between"><span>{{ translate('documents') }}</span><strong>{{ $activationCase->documents->count() }}</strong></div><div class="d-flex justify-content-between"><span>{{ translate('verified') }}</span><strong>{{ $activationCase->documents->where('verification_status', 'verified')->count() }}</strong></div><div class="d-flex justify-content-between"><span>{{ translate('pending') }}</span><strong>{{ $activationCase->documents->where('verification_status', 'pending')->count() }}</strong></div><a class="btn btn-outline-secondary btn-sm mt-3" href="{{ route('admin.activation-center.show', $activationCase) }}">{{ translate('private_verification_documents') }}</a></div></div>
            </div>
        @endif

        @if(isset($sellerOverview))
            <h5 class="mb-2 seller-admin-section-title">{{ translate('seller_dashboard_overview') }}</h5>
            <div class="row g-2 mb-4">
                <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted">{{ translate('sales_summary') }}</small><h4 class="mb-0 mt-2">{{ $overviewMoney($sellerOverview['sales']['total_amount']) }}</h4><small>{{ $sellerOverview['sales']['orders_count'] }} {{ translate('orders') }}</small></div></div>
                <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted">{{ translate('products_summary') }}</small><h4 class="mb-0 mt-2">{{ $sellerOverview['products']['total'] }}</h4><small>{{ translate('active') }}: {{ $sellerOverview['products']['active'] }}</small></div></div>
                <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted">{{ translate('advertisements_summary') }}</small><h4 class="mb-0 mt-2">{{ $sellerOverview['advertisements']['active'] }}</h4><small>{{ translate('violating') }}: {{ $sellerOverview['advertisements']['violating'] }}</small></div></div>
                <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted">{{ translate('order_insurance_credit') }}</small><h4 class="mb-0 mt-2 text-info">{{ $overviewMoney($sellerOverview['balances']['order_insurance_credit']) }}</h4><small>{{ translate('insurance_wallet_can_only_pay_order_insurance') }}</small></div></div>
            </div>

            <div class="seller-admin-wallet p-3 mb-4" id="seller-vendor-wallet">
                <div class="d-flex align-items-center gap-2 mb-3"><img width="24" src="{{ dynamicAsset(path: 'public/assets/back-end/img/admin-wallet.png') }}" alt=""><div><h5 class="mb-0">{{ translate('vendor_Wallet') }}</h5><small class="text-muted">{{ translate('seller_dashboard_overview_hint') }}</small></div></div>
                <div class="row g-2">@foreach([['label'=>'available_balance','value'=>$sellerOverview['balances']['available']],['label'=>'pending_from_platform','value'=>$sellerOverview['balances']['pending_from_platform']],['label'=>'operating_balance','value'=>$sellerOverview['balances']['operating']],['label'=>'pending_withdrawal','value'=>$sellerOverview['balances']['pending_withdrawal']]] as $balance)<div class="col-sm-6 col-lg-3"><div class="seller-admin-wallet-card p-3 h-100"><small class="text-muted d-block">{{ translate($balance['label']) }}</small><h5 class="mb-0 mt-2">{{ $overviewMoney($balance['value']) }}</h5></div></div>@endforeach</div>
            </div>

            <div class="seller-admin-wallet insurance p-3 mb-4" id="seller-insurance-wallet">
                <div class="d-flex align-items-center gap-2 mb-3"><span class="rounded-circle bg-info-subtle text-info d-inline-flex align-items-center justify-content-center" style="width:28px;height:28px"><i class="fi fi-rr-shield-check"></i></span><div><h5 class="mb-0">{{ translate('order_insurance_credit') }}</h5><small class="text-muted">{{ translate('insurance_wallet_can_only_pay_order_insurance') }}</small></div></div>
                <div class="row g-2">
                    @foreach([['label'=>'available_credit','value'=>$insuranceWallet['available_credit'] ?? 0],['label'=>'pending_payment','value'=>$insuranceWallet['pending_amount'] ?? 0,'count'=>$insuranceWallet['pending_count'] ?? 0],['label'=>'under_review','value'=>$insuranceWallet['under_review_amount'] ?? 0,'count'=>$insuranceWallet['under_review_count'] ?? 0],['label'=>'locked_balance','value'=>$insuranceWallet['locked_amount'] ?? 0,'count'=>$insuranceWallet['locked_count'] ?? 0],['label'=>'confiscated_amount','value'=>$insuranceWallet['confiscated_amount'] ?? 0]] as $balance)
                        <div class="col-sm-6 col-lg-3"><div class="seller-admin-wallet-card p-3 h-100"><small class="text-muted d-block">{{ translate($balance['label']) }}</small><h5 class="mb-0 mt-2 text-info">{{ $overviewMoney($balance['value']) }}</h5>@if(array_key_exists('count', $balance))<small class="text-muted">{{ $balance['count'] }} {{ translate('orders') }}</small>@endif</div></div>
                    @endforeach
                    <div class="col-sm-6 col-lg-3"><div class="seller-admin-wallet-card p-3 h-100"><small class="text-muted d-block">{{ translate('next_reusable_at') }}</small><h6 class="mb-0 mt-2">{{ !empty($insuranceWallet['next_reusable_at']) ? \Carbon\Carbon::parse($insuranceWallet['next_reusable_at'])->translatedFormat('d M Y, h:i A') : '-' }}</h6></div></div>
                </div>
            </div>

            <div class="table-responsive border rounded"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>{{ translate('order') }}</th><th>{{ translate('sales_amount') }}</th><th>{{ translate('shipping_entitlement') }}</th><th>{{ translate('insurance') }}</th><th>{{ translate('sales_due_at') }}</th><th>{{ translate('shipping_due_at') }}</th><th>{{ translate('insurance_reuse_due_at') }}</th></tr></thead><tbody>@forelse($sellerOverview['due_rows'] as $row)<tr><td><a href="{{ route('admin.vendors.order-details', ['order_id' => $row['order_id'], 'vendor_id' => $seller->id]) }}">{{ $row['order_reference'] }}</a></td><td>{{ $overviewMoney($row['sales_amount']) }}</td><td>{{ $row['shipping_responsible'] ? $overviewMoney($row['shipping_amount']) : '-' }}</td><td>{{ $row['insurance_amount'] > 0 ? $overviewMoney($row['insurance_amount']) : '-' }}<small class="d-block text-muted">{{ $row['insurance_status'] ? translate($row['insurance_status']) : '' }}</small></td><td>{{ $row['sales_due_at'] ? \Carbon\Carbon::parse($row['sales_due_at'])->translatedFormat('d M Y') : '-' }}</td><td>{{ $row['shipping_due_at'] ? \Carbon\Carbon::parse($row['shipping_due_at'])->translatedFormat('d M Y') : '-' }}</td><td>{{ $row['insurance_reuse_due_at'] ? \Carbon\Carbon::parse($row['insurance_reuse_due_at'])->translatedFormat('d M Y') : '-' }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-3">{{ translate('no_financial_due_dates_found') }}</td></tr>@endforelse</tbody></table></div>
        @endif
    </div>
</div>

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
