@extends('layouts.admin.app')

@section('title', translate('Activation_Offline_Payment_Reviews'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/back-end/img/customer.png') }}" alt="">
                {{ translate('Activation_Offline_Payment_Reviews') }}
                <span class="badge badge-soft-dark radius-50">{{ $invoices->total() }}</span>
            </h2>
            <a href="{{ route('admin.customer.purchase-package.index') }}" class="btn btn-secondary">
                {{ translate('Customer_Purchase_Packages') }}
            </a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    {{ translate('Any_offline_payment_must_be_reviewed_by_admin_before_package_or_insurance_activation') }}.
                    {{ translate('The_customer_will_only_see_that_the_payment_is_pending_review_until_you_approve_it') }}.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                    <h3 class="mb-0">
                        {{ translate('Review_List') }}
                        <span class="badge badge-info text-bg-info">{{ $invoices->total() }}</span>
                    </h3>
                    <form action="{{ url()->current() }}" method="GET" class="min-w-100-mobile">
                        <div class="d-flex flex-wrap gap-2">
                            <div class="select-wrapper">
                                <select name="status" class="form-select">
                                    <option value="">{{ translate('Pending_and_Approved') }}</option>
                                    <option value="pending_offline_review" {{ request('status') === 'pending_offline_review' ? 'selected' : '' }}>
                                        {{ translate('Pending_Review') }}
                                    </option>
                                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>
                                        {{ translate('Approved') }}
                                    </option>
                                </select>
                            </div>
                            <div class="input-group max-w-280">
                                <input type="search" name="searchValue" class="form-control"
                                       placeholder="{{ translate('search_by_invoice_customer_or_order_group') }}"
                                       value="{{ request('searchValue') }}">
                                <div class="input-group-append search-submit">
                                    <button type="submit">
                                        <i class="fi fi-rr-search"></i>
                                    </button>
                                </div>
                            </div>
                            <a href="{{ route('admin.customer.purchase-package.offline-payment-reviews') }}" class="btn btn-secondary">
                                {{ translate('reset') }}
                            </a>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light thead-50 text-capitalize">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Invoice') }}</th>
                            <th>{{ translate('Customer') }}</th>
                            <th>{{ translate('Package') }}</th>
                            <th class="text-center">{{ translate('Insurance') }}</th>
                            <th class="text-center">{{ translate('Total') }}</th>
                            <th>{{ translate('Offline_Payment_Info') }}</th>
                            <th class="text-center">{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($invoices as $key => $invoice)
                            @php($offlinePayment = $invoice->metadata['offline_payment'] ?? null)
                            <tr>
                                <td>{{ $invoices->firstItem() + $key }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $invoice->invoice_no }}</div>
                                    <div class="fs-12 text-muted">{{ translate('Order_Group') }}: {{ $invoice->order_group_id }}</div>
                                    <div class="fs-12 text-muted">{{ $invoice->created_at }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $invoice->customer ? trim($invoice->customer->f_name . ' ' . $invoice->customer->l_name) : translate('customer_not_found') }}
                                    </div>
                                    <div class="fs-12 text-muted">{{ $invoice->customer?->email }}</div>
                                    <div class="fs-12 text-muted">{{ $invoice->customer?->phone }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $invoice->package_name ?: $invoice->package?->name }}</div>
                                    <div class="fs-12 text-muted">
                                        {{ translate('Price') }}:
                                        {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->package_price)) }}
                                    </div>
                                    <div class="fs-12 text-muted">
                                        {{ translate('Limit') }}:
                                        {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->package_purchase_limit)) }}
                                    </div>
                                </td>
                                <td class="text-center">
                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->insurance_amount)) }}
                                </td>
                                <td class="text-center">
                                    <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->total_amount)) }}</strong>
                                </td>
                                <td>
                                    @if($offlinePayment)
                                        <div class="fs-12">
                                            <strong>{{ translate('Method') }}:</strong>
                                            {{ translatePaymentText($offlinePayment['method_name'] ?? translate('N/A')) }}
                                        </div>
                                        @foreach($offlinePayment as $paymentInfoKey => $paymentInfoValue)
                                            @if(!in_array($paymentInfoKey, ['method_id', 'method_name']))
                                                <div class="fs-12 text-break {{ $paymentInfoKey === 'payment_note' ? 'text-muted' : '' }}">
                                                    <strong>{{ translatePaymentText($paymentInfoKey) }}:</strong>
                                                    @php($proofUrl = getOfflinePaymentProofUrl($paymentInfoValue, ['offline-payment/activation-invoice-proof', 'offline-payment/customer-package-proof']))
                                                    @if($proofUrl)
                                                        <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-2">
                                                            <img src="{{ $proofUrl }}" width="52" height="52" class="rounded border object-cover" alt="{{ translatePaymentText($paymentInfoKey) }}">
                                                            <span>{{ translate('View') }}</span>
                                                        </a>
                                                    @else
                                                        {{ formatOrderPaymentInfoValue($paymentInfoValue) }}
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                    @else
                                        <span class="text-muted">{{ translate('No_data_found') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($invoice->status === 'pending_offline_review')
                                        <span class="badge badge-soft-warning">{{ translate('Pending_Review') }}</span>
                                    @elseif($invoice->status === 'paid')
                                        <span class="badge badge-soft-success">{{ translate('Approved') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate($invoice->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($invoice->status === 'pending_offline_review')
                                        <div class="d-flex justify-content-center gap-2">
                                            <form action="{{ route('admin.customer.purchase-package.offline-payment-reviews.approve', ['id' => $invoice->id]) }}"
                                                  method="post">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success btn-sm">
                                                    {{ translate('Approve') }}
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.customer.purchase-package.offline-payment-reviews.reject', ['id' => $invoice->id]) }}"
                                                  method="post">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    {{ translate('Reject') }}
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-muted">{{ translate('No_action') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end p-4">
                    {!! $invoices->links() !!}
                </div>
                @if(count($invoices) == 0)
                    @include('layouts.admin.partials._empty-state', ['text' => 'no_offline_payment_review_found'], ['image' => 'default'])
                @endif
            </div>
        </div>
    </div>
@endsection
