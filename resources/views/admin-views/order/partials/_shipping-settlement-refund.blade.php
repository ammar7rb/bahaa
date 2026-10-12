@if(\Illuminate\Support\Facades\Schema::hasColumn('orders', 'shipping_promise_key'))
    @php($shippingProofs = \Illuminate\Support\Facades\Schema::hasTable('order_shipping_proofs') ? $order->shippingProofs()->latest()->get() : collect())
    @php($refundLedger = \Illuminate\Support\Facades\Schema::hasTable('order_refund_ledgers') ? $order->refundLedgers()->latest()->first() : null)
    @php($postPurchaseInvoice = \Illuminate\Support\Facades\Schema::hasTable('post_purchase_invoices') ? $order->postPurchaseInvoice : null)
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
                <div>
                    <h3 class="fs-16 mb-1">{{ translate('Shipping_promise,_settlement_and_proofs') }}</h3>
                    <p class="text-muted mb-0">{{ translate('The_customer_promise_is_a_snapshot._Changing_settings_will_not_change_this_order.') }}</p>
                </div>
                <span class="badge badge-soft-info">{{ strtoupper($order->shipping_promise_key ?: 'legacy') }}</span>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="bg-section rounded p-3 h-100"><small>{{ translate('customer_delivery_window') }}</small><div class="fw-bold">{{ optional($order->shipping_eta_from)->format('Y-m-d') ?: '-' }} — {{ optional($order->shipping_eta_to)->format('Y-m-d') ?: '-' }}</div></div></div>
                <div class="col-md-3"><div class="bg-section rounded p-3 h-100"><small>{{ translate('sales_due_date') }}</small><div class="fw-bold">{{ optional($order->sales_settlement_due_at)->format('Y-m-d') ?: '-' }}</div></div></div>
                <div class="col-md-3"><div class="bg-section rounded p-3 h-100"><small>{{ translate('shipping_due_date') }}</small><div class="fw-bold">{{ optional($order->shipping_settlement_due_at)->format('Y-m-d') ?: '-' }}</div></div></div>
                <div class="col-md-3"><div class="bg-section rounded p-3 h-100"><small>{{ translate('insurance_due_date') }}</small><div class="fw-bold">{{ optional($order->sellerOrderInsurance?->expires_at)->format('Y-m-d') ?: optional($postPurchaseInvoice?->payment_due_at)->format('Y-m-d') ?: '-' }}</div></div></div>
            </div>

            <form method="post" action="{{ route('admin.orders.shipping-allocation', $order->id) }}" class="row g-3 align-items-end mb-4">
                @csrf
                <div class="col-md-3"><label class="form-label">{{ translate('customer_shipping_paid') }}</label><input class="form-control" value="{{ $order->shipping_cost }}" disabled></div>
                <div class="col-md-3"><label class="form-label">{{ translate('seller_shipping_allocation') }}</label><input type="number" min="0" max="{{ $order->shipping_cost }}" step="0.01" class="form-control" name="seller_shipping_allocation" value="{{ $order->seller_shipping_allocation ?? $order->shipping_seller_cost }}" required></div>
                <div class="col-md-3"><label class="form-label">{{ translate('shipping_settlement_due_at') }}</label><input type="date" class="form-control" name="shipping_settlement_due_at" value="{{ optional($order->shipping_settlement_due_at ?: $order->sales_settlement_due_at)->format('Y-m-d') }}" required></div>
                <div class="col-md-3"><label class="form-label">{{ translate('change_reason') }}</label><input class="form-control" name="reason" required><button class="btn btn-primary mt-2 w-100">{{ translate('save_shipping_allocation') }}</button></div>
            </form>

            <div class="border-top pt-4">
                <h4 class="fs-14">{{ translate('Post-payment_stock-out_refund') }}</h4>
                @if($refundLedger)
                    <div class="alert alert-info mb-0">{{ translate('Refund_already_recorded') }}: {{ $refundLedger->purchase_amount }} / {{ $refundLedger->insurance_amount }} — {{ translate($refundLedger->status) }}</div>
                @elseif($order->payment_status === 'paid')
                    <form method="post" action="{{ route('admin.orders.stock-out-refund', $order->id) }}" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-3"><label class="form-label">{{ translate('purchase_refund') }}</label><input type="number" min="0.01" step="0.01" class="form-control" name="purchase_amount" required></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('insurance_refund') }}</label><input type="number" min="0" step="0.01" class="form-control" name="insurance_amount" value="{{ $postPurchaseInvoice?->insurance_amount ?: $order->insurance?->amount ?: 0 }}"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('available_on') }}</label><input type="date" class="form-control" name="available_at"></div>
                        <div class="col-md-3"><label class="form-label">{{ translate('reason') }}</label><input class="form-control" name="note" required><button class="btn btn-danger mt-2 w-100">{{ translate('execute_split_refund') }}</button></div>
                    </form>
                @endif
            </div>

            @if($shippingProofs->isNotEmpty())
                <div class="border-top mt-4 pt-4 table-responsive">
                    <p class="text-muted">إثبات التسليم يُتم الطلب عند رفعه. المراجعة هنا للتوثيق والمتابعة ولا تعلّق إتمام الطلب.</p>
                    <table class="table"><thead><tr><th>{{ translate('status') }}</th><th>{{ translate('file') }}</th><th>{{ translate('review') }}</th><th>{{ translate('action') }}</th></tr></thead><tbody>
                    @foreach($shippingProofs as $proof)
                        <tr><td>{{ translate($proof->shipping_status) }}</td><td><a target="_blank" rel="noopener" href="{{ route('admin.orders.shipping-proofs.preview', $proof->id) }}">عرض: {{ $proof->original_name ?: translate('file') }}</a><a class="d-block small" href="{{ route('admin.orders.shipping-proofs.download', $proof->id) }}">{{ translate('download') }}</a></td><td>{{ translate($proof->review_status) }}</td><td><form method="post" action="{{ route('admin.orders.shipping-proofs.review', $proof->id) }}" class="d-flex gap-2">@csrf<select name="review_status" class="form-control"><option value="approved">{{ translate('approve') }}</option><option value="rejected">{{ translate('reject') }}</option></select><input name="review_note" class="form-control" placeholder="{{ translate('note') }}"><button class="btn btn-outline-primary">{{ translate('save') }}</button></form></td></tr>
                    @endforeach
                    </tbody></table>
                </div>
            @endif
        </div>
    </div>
@endif
