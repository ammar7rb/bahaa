@extends('layouts.admin.app')

@section('title', translate('Vendor_Insurance_Offline_Reviews'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                {{ translate('Vendor_Insurance_Offline_Reviews') }}
                <span class="badge badge-soft-dark radius-50">{{ $insurances->total() }}</span>
            </h2>
            <a href="{{ route('admin.business-settings.vendor-settings.index') }}#vendor-insurance-settings" class="btn btn-secondary">
                <i class="fi fi-sr-settings"></i>
                {{ translate('Insurance_Settings') }}
            </a>
        </div>

        <div class="alert alert-info d-flex gap-2 align-items-start mb-3" role="alert">
            <i class="fi fi-sr-info mt-1"></i>
            <span>
                {{ translate('offline_vendor_insurance_never_activates_automatically_review_the_transfer_data_and_proof_before_approval') }}.
            </span>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                    <h3 class="mb-0">{{ translate('Review_List') }}</h3>
                    <form action="{{ url()->current() }}" method="get">
                        <div class="d-flex flex-wrap gap-2">
                            <div class="select-wrapper">
                                <select name="status" class="form-select">
                                    <option value="">{{ translate('Pending_Approved_And_Rejected') }}</option>
                                    <option value="pending_review" {{ request('status') === 'pending_review' ? 'selected' : '' }}>{{ translate('Pending_Review') }}</option>
                                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ translate('Rejected') }}</option>
                                </select>
                            </div>
                            <div class="input-group max-w-280">
                                <input type="search" name="searchValue" class="form-control"
                                       placeholder="{{ translate('search_vendor_or_transaction') }}" value="{{ request('searchValue') }}">
                                <div class="input-group-append search-submit">
                                    <button type="submit"><i class="fi fi-rr-search"></i></button>
                                </div>
                            </div>
                            <a href="{{ route('admin.vendors.insurance-payments.index') }}" class="btn btn-secondary">{{ translate('reset') }}</a>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light thead-50 text-capitalize">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Vendor') }}</th>
                            <th>{{ translate('Transaction') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Transfer_Details') }}</th>
                            <th>{{ translate('Payment_Proof') }}</th>
                            <th class="text-center">{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($insurances as $key => $insurance)
                            @php($offlinePayment = $insurance->metadata['offline_payment'] ?? [])
                            @php($proof = $offlinePayment['payment_proof'] ?? null)
                            @php($proofStorage = $proof ? storageLink('seller-insurance/payment-proof', $proof['image_name'] ?? '', $proof['storage'] ?? 'public') : null)
                            @php($proofUrl = is_array($proofStorage) ? ($proofStorage['path'] ?? null) : $proofStorage)
                            <tr>
                                <td>{{ $insurances->firstItem() + $key }}</td>
                                <td>
                                    <div class="fw-semibold">{{ trim(($insurance->seller?->f_name ?? '') . ' ' . ($insurance->seller?->l_name ?? '')) ?: translate('vendor_not_found') }}</div>
                                    <div class="fs-12 text-muted">{{ $insurance->seller?->email }}</div>
                                    <div class="fs-12 text-muted">{{ $insurance->seller?->phone }}</div>
                                </td>
                                <td>
                                    <div class="text-break max-w-220">{{ $insurance->transaction_id }}</div>
                                    <div class="fs-12 text-muted">{{ $insurance->created_at }}</div>
                                </td>
                                <td><strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $insurance->amount)) }}</strong></td>
                                <td>
                                    <div class="fs-12"><strong>{{ translate('Method') }}:</strong> {{ $offlinePayment['method_name'] ?? translate('N/A') }}</div>
                                    @foreach(($offlinePayment['method_information'] ?? []) as $field => $value)
                                        <div class="fs-12 text-break"><strong>{{ ucwords(str_replace('_', ' ', $field)) }}:</strong> {{ $value }}</div>
                                    @endforeach
                                    @if(!empty($offlinePayment['payment_note']))
                                        <div class="fs-12 text-muted text-break"><strong>{{ translate('Note') }}:</strong> {{ $offlinePayment['payment_note'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($proofUrl)
                                        <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="d-inline-block">
                                            <img src="{{ $proofUrl }}" alt="{{ translate('Payment_Proof') }}"
                                                 width="72" height="72" class="rounded border object-cover">
                                        </a>
                                    @else
                                        <span class="text-muted">{{ translate('No_proof') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-soft-{{ $insurance->status === 'paid' ? 'success' : ($insurance->status === 'rejected' ? 'danger' : 'warning') }}">
                                        {{ translate($insurance->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($insurance->status === 'pending_review')
                                        <div class="d-flex justify-content-center gap-2">
                                            <form action="{{ route('admin.vendors.insurance-payments.approve', ['id' => $insurance->id]) }}" method="post">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success btn-sm">{{ translate('Approve') }}</button>
                                            </form>
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-toggle="modal"
                                                    data-target="#reject-insurance-{{ $insurance->id }}">
                                                {{ translate('Reject') }}
                                            </button>
                                        </div>

                                        <div class="modal fade" id="reject-insurance-{{ $insurance->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <form action="{{ route('admin.vendors.insurance-payments.reject', ['id' => $insurance->id]) }}" method="post" class="modal-content">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">{{ translate('Reject_Offline_Payment') }}</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <label class="form-label" for="review-note-{{ $insurance->id }}">{{ translate('Rejection_Reason') }}</label>
                                                        <textarea class="form-control" id="review-note-{{ $insurance->id }}" name="review_note" rows="3" required></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('cancel') }}</button>
                                                        <button type="submit" class="btn btn-danger">{{ translate('Confirm_Reject') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @else
                                        <div class="fs-12 text-muted text-center">
                                            {{ $insurance->reviewedByAdmin?->name }}<br>
                                            {{ $insurance->reviewed_at }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">{{ translate('No_data_found') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end p-4">{!! $insurances->links() !!}</div>
            </div>
        </div>
    </div>
@endsection
