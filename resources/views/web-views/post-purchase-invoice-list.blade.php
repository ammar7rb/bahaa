@extends('layouts.front-end.app')

@section('title', translate('suspended_orders'))

@section('content')
    <div class="container py-2 py-md-4 p-0 p-md-2 user-profile-container px-5px">
        <div class="row">
            @include('web-views.partials._profile-aside')

            <section class="col-lg-9 __customer-profile customer-profile-wishlist px-0">
                <div class="card __card d-flex web-direction h-100">
                    <div class="card-body p-3 p-md-4">
                        <div class="mb-4">
                            <h5 class="font-semi-bold mb-1">{{ translate('suspended_orders') }}</h5>
                            <p class="text-muted mb-0">{{ translate('complete_tax_and_insurance_payment_to_continue_your_order') }}</p>
                        </div>

                        @forelse($invoices as $invoice)
                            <a class="d-flex flex-wrap align-items-center justify-content-between gap-3 text-decoration-none border rounded p-3 mb-2"
                               href="{{ route('post-purchase-invoices.show', $invoice->id) }}">
                                <div>
                                    <strong>{{ translate('order') }} #{{ $invoice->order_id }}</strong>
                                    <div class="small text-muted mt-1">
                                        {{ translate('tax') }}: {{ webCurrencyConverter($invoice->tax_amount) }}
                                        &middot;
                                        {{ translate('order_insurance') }}: {{ webCurrencyConverter($invoice->insurance_amount) }}
                                    </div>
                                </div>
                                <div class="text-end ms-auto">
                                    <strong>{{ webCurrencyConverter(max(0, $invoice->total_amount - $invoice->paid_amount)) }}</strong>
                                    <div class="mt-1">
                                        <span class="badge bg-{{ $invoice->status === 'awaiting_review' ? 'warning' : ($invoice->status === 'expired' ? 'danger' : 'primary') }}">
                                            {{ translate($invoice->status) }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="text-center py-5 text-muted">{{ translate('no_suspended_orders') }}</div>
                        @endforelse

                        <div class="mt-3">{{ $invoices->links() }}</div>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
