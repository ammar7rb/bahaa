@extends('layouts.vendor.app')

@section('title', translate('stock_violation_history'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h2 class="h1 mb-1 text-capitalize">{{ translate('stock_violation_history') }}</h2>
                <p class="mb-0 text-muted">{{ translate('stock_violation_history_note') }}</p>
            </div>
            <a href="{{ route('vendor.products.stock-limit-list') }}" class="btn btn--primary">
                <i class="tio-warning mr-1"></i>{{ translate('limited_Stocked_Products_List') }}
            </a>
        </div>

        <div class="alert alert-info d-flex gap-2 align-items-start" role="alert">
            <i class="tio-info-outined mt-1"></i>
            <span>{{ translate('stock_alerts_do_not_create_fines_automatically') }}</span>
        </div>

        <div class="card">
            <div class="card-header border-0 py-3">
                <form method="get" action="{{ route('vendor.products.stock-violations') }}">
                    <div class="input-group input-group-custom input-group-merge max-w-500">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="tio-search"></i></span></div>
                        <input type="search" name="searchValue" class="form-control" value="{{ $searchValue }}" placeholder="{{ translate('search_by_product_name_or_code') }}">
                        <button type="submit" class="btn btn--primary">{{ translate('search') }}</button>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table mb-0">
                    <thead class="thead-light text-capitalize"><tr>
                        <th>{{ translate('SL') }}</th><th>{{ translate('product_Name') }}</th><th>{{ translate('alert') }}</th><th>{{ translate('decision') }}</th><th>{{ translate('reason_and_policy') }}</th><th>{{ translate('amount') }}</th><th>{{ translate('date') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($violations as $key => $violation)
                        <tr>
                            <td>{{ $violations->firstItem() + $key }}</td>
                            <td>@if($violation->product)
                                <a class="title-color hover-c1" href="{{ route('vendor.products.view', $violation->product_id) }}">{{ Str::limit($violation->product->name, 35) }}</a>
                                <div class="text-muted fz-12">{{ $violation->product->code }}</div>
                            @else <span class="text-muted">{{ translate('product_was_deleted') }}</span> @endif</td>
                            <td><span class="badge {{ $violation->alert_type === 'out_of_stock' ? 'badge-soft-danger' : 'badge-soft-warning' }}">{{ translate($violation->alert_type) }}</span></td>
                            <td><span class="badge {{ $violation->decision === 'penalty' ? 'badge-soft-danger' : 'badge-soft-warning' }}">{{ translate($violation->decision) }}</span></td>
                            <td class="text-wrap" style="min-width:220px"><div>{{ $violation->reason }}</div>@if($violation->policy_reference)<small class="text-muted">{{ $violation->policy_reference }}</small>@endif</td>
                            <td>{{ $violation->penalty_amount ? setCurrencySymbol(amount: usdToDefaultCurrency(amount: $violation->penalty_amount), currencyCode: getCurrencyCode()) : '—' }}</td>
                            <td>{{ $violation->decided_at?->format('d M Y, h:i A') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="py-5 text-center text-muted">{{ translate('no_stock_violations_found') }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($violations->hasPages())<div class="px-4 py-3 d-flex justify-content-end">{{ $violations->links() }}</div>@endif
        </div>
    </div>
@endsection
