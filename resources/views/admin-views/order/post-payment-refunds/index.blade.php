@extends('layouts.admin.app')

@section('title', translate('post_payment_refunds'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="page-header-title mb-1">{{ translate('post_payment_refunds') }}</h1>
                <p class="text-muted mb-0">{{ translate('post_payment_refunds_description') }}</p>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach([
                [translate('refund_operations'), $summary['count']],
                [translate('purchase_balance'), setCurrencySymbol(amount: $summary['purchase_amount'], currencyCode: getCurrencyCode())],
                [translate('insurance_balance'), setCurrencySymbol(amount: $summary['insurance_amount'], currencyCode: getCurrencyCode())],
                [translate('held_until_due_date'), $summary['held_count']],
            ] as [$label, $value])
                <div class="col-sm-6 col-xl-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted mb-1">{{ $label }}</div>
                        <div class="fs-2 fw-bold">{{ $value }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form method="get" class="row g-2 w-100">
                    <div class="col-md-6">
                        <input class="form-control" name="search" value="{{ request('search') }}"
                               placeholder="{{ translate('search_by_order_customer_email_or_phone') }}">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">{{ translate('all_status') }}</option>
                            <option value="held" @selected(request('status') === 'held')>{{ translate('held') }}</option>
                            <option value="available" @selected(request('status') === 'available')>{{ translate('available') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit">{{ translate('filter') }}</button>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.orders.post-payment-refunds') }}">{{ translate('reset') }}</a>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr>
                        <th>#</th><th>{{ translate('order') }}</th><th>{{ translate('customer') }}</th>
                        <th>{{ translate('purchase_balance') }}</th><th>{{ translate('insurance_balance') }}</th>
                        <th>{{ translate('available_at') }}</th><th>{{ translate('status') }}</th>
                        <th>{{ translate('processed_by') }}</th><th>{{ translate('note') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($refunds as $refund)
                        <tr>
                            <td>{{ $refund->id }}</td>
                            <td><a href="{{ route('admin.orders.details', $refund->order_id) }}">#{{ $refund->order_id }}</a></td>
                            <td>
                                <div class="fw-semibold">{{ trim(($refund->customer?->f_name ?? '').' '.($refund->customer?->l_name ?? '')) ?: 'C'.$refund->customer_id }}</div>
                                <small class="text-muted">{{ $refund->customer?->phone ?: $refund->customer?->email }}</small>
                            </td>
                            <td>{{ setCurrencySymbol(amount: $refund->purchase_amount, currencyCode: getCurrencyCode()) }}</td>
                            <td>{{ setCurrencySymbol(amount: $refund->insurance_amount, currencyCode: getCurrencyCode()) }}</td>
                            <td>
                                <div>{{ translate('purchases') }}: {{ $refund->purchase_available_at?->format('Y-m-d H:i') ?: '-' }}</div>
                                <div>{{ translate('insurance') }}: {{ $refund->insurance_available_at?->format('Y-m-d H:i') ?: '-' }}</div>
                            </td>
                            <td><span class="badge {{ $refund->status === 'available' ? 'bg-success' : 'bg-warning text-dark' }}">{{ translate($refund->status) }}</span></td>
                            <td>{{ $refund->admin?->name ?: '-' }}</td>
                            <td class="text-wrap" style="min-width:220px">{{ $refund->admin_note ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-5">{{ translate('no_data_found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($refunds->hasPages())
                <div class="card-footer">{{ $refunds->links() }}</div>
            @endif
        </div>
    </div>
@endsection
