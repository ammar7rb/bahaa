@if(\Illuminate\Support\Facades\Schema::hasColumn('orders', 'shipping_workflow_status') && ($order->shipping_responsibility === 'sellerwise_shipping' || $order->shipping_fulfillment_mode === 'seller_shipping' || $order->shipping_responsible_party === 'seller'))
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between gap-3 flex-wrap">
                <div><h3 class="fs-16 mb-1">{{ translate('Shipping_progress_and_private_proof') }}</h3><p class="text-muted mb-0">{{ translate('Proofs_are_private_and_can_only_be_reviewed_by_the_admin.') }}</p></div>
                <span class="badge badge-soft-info">{{ translate($order->shipping_workflow_status ?: 'pending') }}</span>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-3"><div class="bg-section p-3 rounded"><small>{{ translate('sales_due_date') }}</small><div class="fw-bold">{{ optional($order->sales_settlement_due_at)->format('Y-m-d') ?: '-' }}</div></div></div>
                <div class="col-md-3"><div class="bg-section p-3 rounded"><small>{{ translate('shipping_due_date') }}</small><div class="fw-bold">{{ optional($order->shipping_settlement_due_at)->format('Y-m-d') ?: '-' }}</div></div></div>
                <div class="col-md-3"><div class="bg-section p-3 rounded"><small>{{ translate('insurance_due_date') }}</small><div class="fw-bold">{{ optional($order->sellerOrderInsurance?->reusable_at)->format('Y-m-d') ?: '-' }}</div></div></div>
                <div class="col-md-3"><div class="bg-section p-3 rounded"><small>{{ translate('seller_shipping_amount') }}</small><div class="fw-bold">{{ webCurrencyConverter(amount: $order->seller_shipping_allocation ?? $order->shipping_seller_cost) }}</div></div></div>
            </div>
            @if(!in_array($order->order_status, ['delivered', 'canceled', 'returned', 'failed']))
            <p class="mt-3 mb-0">رفع إثبات التسليم سيُنهي الطلب مباشرة، وتستطيع الإدارة الاطلاع عليه لاحقًا.</p>
            <form method="post" enctype="multipart/form-data" action="{{ route('vendor.orders.shipping-proof.store', $order->id) }}" class="row g-3 align-items-end mt-2">
                @csrf
                <input type="hidden" name="shipping_status" value="delivered">
                <div class="col-md-4"><label class="form-label">إثبات التسليم (صورة أو فيديو أو PDF)</label><input type="file" name="proof" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm,application/pdf" class="form-control" required><small class="text-muted">الحد الأقصى 100 ميجابايت، ولا يظهر إلا للإدارة.</small></div>
                <div class="col-md-3"><label class="form-label">{{ translate('note') }}</label><input name="note" class="form-control"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">رفع الإثبات وإتمام الطلب</button></div>
            </form>
            @else
            <p class="mt-3 mb-0">{{ translate($order->order_status) }}</p>
            @endif
        </div>
    </div>
@endif
