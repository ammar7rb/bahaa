@extends('layouts.front-end.app')
@section('title', translate('my_wallet'))
@push('css_or_js')
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/customer-wallet.css') }}?v=20260906.1">
@endpush
@section('content')
<div class="container py-4 text-align-direction customer-balances-page">
    <div class="row">
        @include('web-views.partials._profile-aside')
        <section class="col-lg-9">
            @if(session('deposit_message'))<div class="alert alert-success">{{ session('deposit_message') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="wallet-heading">
                    <span class="wallet-icon-tile">@include('web-views.users-profile.partials.wallet-icon', ['icon' => 'purchase'])</span>
                    <div><h1 class="h4 mb-1">{{ translate('my_wallet') }}</h1><p class="text-muted mb-0 small">{{ translate('wallet_overview_subtitle') }}</p></div>
                </div>
                <button type="button" class="profile-aside-btn btn btn--primary d-lg-none">{{ translate('profile_info') }}</button>
            </div>
            <div class="row">
                @foreach(['purchase', 'insurance'] as $walletType)
                    <div class="col-md-6 mb-3" id="{{ $walletType }}-balance">
                        <div class="card h-100 wallet-balance-card wallet-balance-{{ $walletType }}"><div class="card-body">
                            <div class="wallet-card-heading"><h2 class="h6 mb-0">{{ translate($walletType . '_balance_title') }}</h2><span class="wallet-icon-tile">@include('web-views.users-profile.partials.wallet-icon', ['icon' => $walletType])</span></div>
                            <p class="wallet-amount my-3"><bdi>{{ webCurrencyConverter($walletType === 'purchase' ? $purchaseBalance : $insuranceSummary['available_balance']) }}</bdi></p>
                            <p class="text-muted">{{ translate($walletType . '_balance_usage_note') }}</p>
                            @if($walletType === 'insurance')
                                <div class="wallet-held-info"><p class="mb-2">{{ translate('held_insurance_balance') }}: <strong>{{ webCurrencyConverter($insuranceSummary['held_balance']) }}</strong></p>
                                <p class="text-muted small mb-0">{{ translate('next_insurance_maturity') }}:
                                    <bdi>{{ $insuranceSummary['next_maturity_at'] ? \Carbon\Carbon::parse($insuranceSummary['next_maturity_at'])->format('Y-m-d H:i') : translate('no_held_insurance') }}</bdi>
                                </p></div>
                            @endif
                            <details class="wallet-deposit">
                                <summary>@include('web-views.users-profile.partials.wallet-icon', ['icon' => 'plus']) {{ translate('deposit_to_' . $walletType . '_balance') }}</summary>
                                <div class="wallet-deposit-content">
                            @if($canDeposit && ($walletType === 'insurance' || $purchaseWalletEnabled))
                                <details class="border rounded p-3">
                                    <summary class="text-primary cursor-pointer font-weight-bold">{{ translate('wallet_online_deposit') }}</summary>
                                    <form class="mt-3" method="post" action="{{ route('customer.add-fund-request') }}">
                                        @csrf
                                        <input type="hidden" name="wallet_type" value="{{ $walletType }}">
                                        <input type="hidden" name="payment_platform" value="web">
                                        <label for="{{ $walletType }}-amount">{{ translate('amount') }} ({{ session('currency_symbol') }})</label>
                                        <input id="{{ $walletType }}-amount" type="number" name="amount" min="0.01" step="0.01" required class="form-control mb-3">
                                        <label for="{{ $walletType }}-gateway">{{ translate('payment_method') }}</label>
                                        <select id="{{ $walletType }}-gateway" name="payment_method" required class="form-control mb-3">
                                            <option value="">{{ translate('balance_choose_payment_method') }}</option>
                                            @foreach($paymentGatewayList as $gateway)
                                                <option value="{{ $gateway->key_name }}">{{ data_get(json_decode($gateway->additional_data ?? '{}', true), 'gateway_title') ?: ucwords(str_replace('_', ' ', $gateway->key_name)) }}</option>
                                            @endforeach
                                        </select>
                                        <p class="small text-muted">{{ translate('balance_credited_after_payment_confirmation') }}</p>
                                        <button class="btn btn--primary" type="submit">{{ translate('continue_to_payment') }}</button>
                                    </form>
                                </details>
                            @else
                                @if(!$canDepositOffline || $offlinePaymentMethods->isEmpty() || ($walletType === 'purchase' && !$purchaseWalletEnabled))
                                    <p class="small text-muted">{{ translate('balance_deposits_unavailable') }}</p>
                                @endif
                            @endif
                            @if($canDepositOffline && ($walletType === 'insurance' || $purchaseWalletEnabled))
                                @include('web-views.users-profile.partials.balance-offline-deposit')
                            @endif
                                </div>
                            </details>
                        </div></div>
                    </div>
                @endforeach
            </div>
            <div class="wallet-notice">@include('web-views.users-profile.partials.wallet-icon', ['icon' => 'insurance'])<p class="mb-0">{{ translate('insurance_maturity_reuse_note') }}</p></div>
            @php($walletTab = in_array(request('wallet_tab'), ['deposits', 'orders', 'purchase', 'insurance'], true) ? request('wallet_tab') : 'deposits')
            <div class="wallet-history-heading">@include('web-views.users-profile.partials.wallet-icon', ['icon' => 'history'])<h2 class="h5 mb-0">{{ translate('wallet_activity') }}</h2></div>
            <nav class="wallet-history-nav" aria-label="{{ translate('wallet_activity') }}">
                @foreach(['deposits' => 'wallet_deposits_tab', 'orders' => 'wallet_orders_tab', 'purchase' => 'purchase_balance_history', 'insurance' => 'insurance_balance_history'] as $tab => $label)
                    <a href="{{ route('wallet', ['wallet_tab' => $tab]) }}" @if($walletTab === $tab) aria-current="page" @endif>{{ translate($label) }}</a>
                @endforeach
            </nav>
            <div class="card mb-3 wallet-history" @if($walletTab !== 'deposits') hidden @endif><div class="card-body">
                <h2 class="h5">{{ translate('wallet_deposits_tab') }}</h2>
                <div class="table-responsive"><table class="table"><thead><tr>
                    <th>{{ translate('date') }}</th><th>{{ translate('balance_destination') }}</th><th>{{ translate('amount') }}</th>
                    <th>{{ translate('payment_method') }}</th><th>{{ translate('status') }}</th><th>{{ translate('payment_proof') }}</th><th>{{ translate('deposit_review_note') }}</th>
                </tr></thead><tbody>
                @forelse($depositRequests as $deposit)
                    <tr><td><bdi>{{ $deposit->created_at?->format('Y-m-d H:i') }}</bdi></td>
                        <td>{{ translate($deposit->wallet_type . '_balance_title') }}</td><td>{{ webCurrencyConverter($deposit->amount) }}</td>
                        <td>{{ $deposit->method_name }}</td><td>{{ translate('deposit_status_' . $deposit->status) }}</td>
                        <td><a target="_blank" rel="noopener" href="{{ route('customer-balances.proof', $deposit->id) }}">{{ translate('view') }}</a></td>
                        <td>{{ $deposit->review_note ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">{{ translate('no_deposit_requests') }}</td></tr>
                @endforelse
                </tbody></table></div>{{ $depositRequests->links() }}
            </div></div>
            <div class="card mb-3 wallet-history" @if($walletTab !== 'orders') hidden @endif><div class="card-body">
                <h2 class="h5">{{ translate('orders_purchase_and_insurance_history') }}</h2>
                <div class="table-responsive"><table class="table">
                    <thead><tr>
                        <th>{{ translate('balance_order_number') }}</th><th>{{ translate('date') }}</th>
                        <th>{{ translate('purchase_amount') }}</th><th>{{ translate('payment_status') }}</th>
                        <th>{{ translate('order_insurance_cost') }}</th><th>{{ translate('insurance_payment_state') }}</th>
                        <th>{{ translate('insurance_maturity_date') }}</th><th>{{ translate('insurance_balance_state') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td><a href="{{ route('account-order-details', ['id' => $order->id]) }}">#{{ $order->id }}</a></td>
                            <td><bdi>{{ $order->created_at?->format('Y-m-d') ?? '—' }}</bdi></td>
                            <td>{{ webCurrencyConverter($order->order_amount) }}</td>
                            <td>{{ translate($order->payment_status) }}</td>
                            <td>{{ $order->insurance ? webCurrencyConverter($order->insurance->amount) : '—' }}</td>
                            <td>{{ $order->insurance ? translate($order->insurance->payment_status) : translate('no_order_insurance') }}</td>
                            <td><bdi>{{ $order->insurance?->matures_at?->format('Y-m-d H:i') ?? '—' }}</bdi></td>
                            <td>{{ $order->insurance ? translate('insurance_state_' . $order->insurance->status) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">{{ translate('no_orders_to_display') }}</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                {{ $orders->links() }}
            </div></div>
            @foreach(['purchase' => $purchaseEntries, 'insurance' => $insuranceEntries] as $entryWallet => $entries)
                <div class="card mb-3 wallet-history" @if($walletTab !== $entryWallet) hidden @endif><div class="card-body">
                    <h2 class="h5">{{ translate($entryWallet . '_balance_history') }}</h2>
                    <div class="table-responsive"><table class="table">
                        <thead><tr><th>{{ translate('date') }}</th><th>{{ translate('transaction_type') }}</th><th>{{ translate('balance_credit_amount') }}</th><th>{{ translate('balance_debit_amount') }}</th><th>{{ translate('balance_order_number') }}</th></tr></thead>
                        <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td><bdi>{{ $entry->created_at?->format('Y-m-d H:i') ?? '—' }}</bdi></td>
                                <td>{{ translate($entryWallet === 'insurance' ? 'insurance_entry_' . $entry->entry_type : $entry->transaction_type) }}</td>
                                <td>{{ webCurrencyConverter($entry->credit) }}</td><td>{{ webCurrencyConverter($entry->debit) }}</td>
                                <td>
                                    @if($entryWallet === 'insurance' && $entry->order_id)
                                        <a href="{{ route('account-order-details', ['id' => $entry->order_id]) }}">#{{ $entry->order_id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">{{ translate('no_balance_transactions') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                    {{ $entries->links() }}
                </div></div>
            @endforeach
        </section>
    </div>
</div>
@endsection
