@extends('layouts.admin.app')

@section('title', translate('stock_alerts_and_violations'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-0">{{ translate('stock_alerts_and_manual_violations') }}</h2>
            <p class="text-muted mb-0">{{ translate('stock_alerts_do_not_create_fines_automatically') }}</p>
        </div>

        <div class="card mb-4">
            <div class="card-header border-0"><h5 class="mb-0">{{ translate('products_needing_stock_attention') }}</h5></div>
            <div class="card-body pt-0">
                <form class="mb-3" method="get">
                    <div class="input-group" style="max-width: 440px">
                        <input class="form-control" name="q" value="{{ request('q') }}" placeholder="{{ translate('search_by_product_name_or_code') }}">
                        <button class="btn btn--primary" type="submit">{{ translate('search') }}</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>{{ translate('product') }}</th><th>{{ translate('seller') }}</th><th>{{ translate('stock') }}</th><th>{{ translate('alert') }}</th><th>{{ translate('manual_decision') }}</th></tr></thead>
                        <tbody>
                        @forelse($alerts as $product)
                            @php($alertType = app(\App\Services\SellerStockMonitoringService::class)->alertTypeFor($product, $product->seller))
                            <tr>
                                <td><div class="fw-semibold">{{ $product->name }}</div><small class="text-muted">#{{ $product->id }} · {{ $product->code }}</small></td>
                                <td>{{ $product->seller?->f_name }} {{ $product->seller?->l_name }}<br><small class="text-muted">#{{ $product->user_id }}</small></td>
                                <td>{{ $product->current_stock }}</td>
                                <td><span class="badge {{ $alertType === 'out_of_stock' ? 'bg-danger' : 'bg-warning text-dark' }}">{{ translate($alertType) }}</span></td>
                                <td>
                                    <form method="post" action="{{ route('admin.vendors.stock-violations.store') }}" class="d-grid gap-2">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        <input type="hidden" name="seller_id" value="{{ $product->user_id }}">
                                        <select class="form-select form-select-sm" name="decision" required>
                                            <option value="warning">{{ translate('warning') }}</option>
                                            <option value="penalty">{{ translate('penalty') }}</option>
                                        </select>
                                        <input class="form-control form-control-sm" name="policy_reference" maxlength="120" placeholder="{{ translate('policy_reference_optional') }}">
                                        <input class="form-control form-control-sm" type="number" min="0.01" step="0.01" name="penalty_amount" placeholder="{{ translate('penalty_amount_only_for_penalty') }}">
                                        <textarea class="form-control form-control-sm" name="reason" required maxlength="2000" placeholder="{{ translate('reason_shown_to_the_seller') }}"></textarea>
                                        <button class="btn btn-sm btn--primary" type="submit">{{ translate('save_decision') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('no_products_currently_need_stock_attention') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3">{{ $alerts->links() }}</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0"><h5 class="mb-0">{{ translate('documented_decisions') }}</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ translate('seller_product') }}</th><th>{{ translate('decision') }}</th><th>{{ translate('reason_and_policy') }}</th><th>{{ translate('amount') }}</th><th>{{ translate('admin_date') }}</th></tr></thead>
                    <tbody>
                    @forelse($violations as $violation)
                        <tr>
                            <td>{{ $violation->seller?->f_name }} {{ $violation->seller?->l_name }}<br><small class="text-muted">{{ $violation->product?->name ?? translate('deleted_product') }}</small></td>
                            <td><span class="badge {{ $violation->decision === 'penalty' ? 'bg-danger' : 'bg-warning text-dark' }}">{{ translate($violation->decision) }}</span><br><small>{{ translate($violation->alert_type) }}</small></td>
                            <td>{{ $violation->reason }}<br><small class="text-muted">{{ $violation->policy_reference }}</small></td>
                            <td>{{ $violation->penalty_amount ?? '—' }}</td>
                            <td>{{ $violation->admin?->name ?? '—' }}<br><small class="text-muted">{{ $violation->decided_at?->format('Y-m-d H:i') }}</small></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('no_manual_decisions_have_been_recorded') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body d-flex justify-content-end">{{ $violations->links() }}</div>
        </div>
    </div>
@endsection
