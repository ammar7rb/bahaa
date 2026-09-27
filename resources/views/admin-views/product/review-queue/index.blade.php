@extends('layouts.admin.app')

@section('title', translate('seller_product_review_queue'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="h1 mb-0">{{ translate('seller_product_review_queue') }}</h2>
                <p class="text-muted mb-0">{{ translate('every_decision_is_recorded_with_its_reason') }}</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ route('admin.products.export-excel', [
                'type' => 'vendor', 'request_status' => 0, 'searchValue' => request('q'),
                'seller_id' => request('seller_id'), 'filter_brand_ids' => request('brand_id'),
                'filter_category_ids' => request('category_id'),
            ]) }}">
                <i class="fi fi-sr-inbox-in"></i> {{ translate('export') }}
            </a>
        </div>

        <div class="alert alert-info mb-3">
            <strong>{{ translate('how_product_review_queue_works') }}</strong>
            <div class="mt-1">{{ translate('product_review_queue_help') }}</div>
            <div class="mt-1 small">
                <i class="fi fi-sr-barcode"></i> {{ translate('barcode') }} ·
                <i class="fi fi-sr-eye"></i> {{ translate('view') }} ·
                <i class="fi fi-sr-pencil"></i> {{ translate('edit') }} ·
                <i class="fi fi-rr-trash"></i> {{ translate('delete') }}
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-lg-3"><label class="form-label">{{ translate('search') }}</label><input class="form-control" name="q" value="{{ request('q') }}" placeholder="{{ translate('search_product_code_seller_email_or_phone') }}"></div>
                    <div class="col-lg-2"><label class="form-label">{{ translate('review_box') }}</label><select class="form-select" name="box">
                        @foreach($reviewBoxes as $box => $statuses)
                            <option value="{{ $box }}" @selected($selectedBox === $box)>{{ $box === 'pending' ? translate('new_Products_Requests') : ($box === 'all' ? translate('all_review_boxes') : translate($box)) }}</option>
                        @endforeach
                    </select></div>
                    <div class="col-lg-2"><label class="form-label">{{ translate('seller') }}</label><select class="form-select" name="seller_id"><option value="">{{ translate('all') }}</option>@foreach($sellers as $seller)<option value="{{ $seller->id }}" @selected((int) request('seller_id') === $seller->id)>{{ $seller->f_name }} {{ $seller->l_name }}</option>@endforeach</select></div>
                    <div class="col-lg-2"><label class="form-label">{{ translate('brand') }}</label><select class="form-select" name="brand_id"><option value="">{{ translate('all') }}</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((int) request('brand_id') === $brand->id)>{{ $brand->name }}</option>@endforeach</select></div>
                    <div class="col-lg-2"><label class="form-label">{{ translate('category') }}</label><select class="form-select" name="category_id"><option value="">{{ translate('all') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) request('category_id') === $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                    <div class="col-lg-1"><button class="btn btn--primary w-100" type="submit">{{ translate('filter') }}</button></div>
                    <div class="col-lg-2"><label class="form-label">{{ translate('sort_by') }}</label><select class="form-select" name="sort"><option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ translate('latest') }}</option><option value="oldest" @selected(request('sort') === 'oldest')>{{ translate('oldest') }}</option></select></div>
                    <div class="col-lg-2"><a class="btn btn-outline-secondary w-100" href="{{ route('admin.products.list', ['type' => 'vendor', 'request_status' => 0]) }}">{{ translate('clear_all_filters') }}</a></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered align-middle mb-0">
                    <thead class="text-capitalize"><tr>
                        <th>{{ translate('product Name') }}</th><th class="text-center">{{ translate('unit_price') }}</th><th>{{ translate('seller') }}</th><th class="text-center">{{ translate('quantity') }}</th>
                        @if($productWiseTax)<th class="text-center">{{ translate('Vat/Tax') }}</th>@endif
                        <th>{{ translate('review_details') }}</th><th>{{ translate('decision_history') }}</th><th class="text-center">{{ translate('action') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($products as $product)
                        @php($currentStatus = app(\App\Services\SellerProductReviewService::class)->statusFor($product))
                        <tr>
                            <td><a href="{{ route('admin.products.view', ['addedBy' => 'vendor', 'id' => $product->id]) }}" class="media align-items-center gap-2"><img src="{{ getStorageImages(path: $product->thumbnail_full_url, type: 'backend-product') }}" class="avatar border object-fit-cover flex-shrink-0" alt=""><div><div class="text-dark text-hover-primary line-1">{{ \Illuminate\Support\Str::limit($product->name, 35) }}</div><small class="text-muted">{{ translate('Id') }} #{{ $product->id }} · {{ $product->code }}</small></div></a></td>
                            <td class="text-center">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $product->unit_price), currencyCode: getCurrencyCode()) }}</td>
                            <td>{{ $product->seller?->f_name }} {{ $product->seller?->l_name }}<br><small class="text-muted">{{ $product->seller?->email }}</small></td>
                            <td class="text-center">{{ $product->current_stock }}</td>
                            @if($productWiseTax)<td class="text-center">@forelse($product->taxVats as $taxVat)<small>{{ $taxVat?->tax?->name }} ({{ $taxVat?->tax?->tax_rate }}%)</small><br>@empty<small>{{ translate('N/A') }}</small>@endforelse</td>@endif
                            <td><span class="badge bg-info text-dark">{{ translate($currentStatus) }}</span><br><small class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($product->details), 110) }}</small></td>
                            <td>@forelse($product->sellerReviewDecisions as $decision)<div class="mb-2"><strong>{{ translate($decision->status) }}</strong><br><small>{{ $decision->reason ?: translate('no_reason') }} · {{ $decision->decided_at?->format('Y-m-d H:i') }}</small></div>@empty<small class="text-muted">{{ translate('no_decisions_yet') }}</small>@endforelse</td>
                            <td style="min-width: 255px"><div class="d-flex justify-content-center gap-2 mb-2">
                                <a class="btn btn-outline-warning icon-btn" title="{{ translate('barcode') }}" href="{{ route('admin.products.barcode', $product->id) }}"><i class="fi fi-sr-barcode"></i></a>
                                <a class="btn btn-outline-success icon-btn" title="{{ translate('view') }}" href="{{ route('admin.products.view', ['addedBy' => 'vendor', 'id' => $product->id]) }}"><i class="fi fi-sr-eye"></i></a>
                                <a class="btn btn-outline-info icon-btn" title="{{ translate('edit') }}" href="{{ route('admin.products.update', $product->id) }}"><i class="fi fi-sr-pencil"></i></a>
                                <span class="btn btn-outline-danger icon-btn delete-data" title="{{ translate('delete') }}" data-id="review-product-{{ $product->id }}"><i class="fi fi-rr-trash"></i></span>
                            </div><form action="{{ route('admin.products.delete', $product->id) }}" method="post" id="review-product-{{ $product->id }}">@csrf @method('delete')</form>
                            <form method="post" action="{{ route('admin.products.review-queue.decision') }}" class="d-grid gap-2">@csrf<input type="hidden" name="id" value="{{ $product->id }}"><select class="form-select form-select-sm" name="status" required><option value="under_review">{{ translate('mark_under_review') }}</option><option value="approved_published">{{ translate('approve_and_publish') }}</option><option value="suspended">{{ translate('suspend') }}</option><option value="rejected">{{ translate('reject') }}</option></select><textarea class="form-control form-control-sm" name="reason" required maxlength="2000" placeholder="{{ translate('decision_reason_required') }}"></textarea><button class="btn btn-sm btn--primary" type="submit">{{ translate('save_decision') }}</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $productWiseTax ? 8 : 7 }}" class="text-center text-muted py-4">{{ translate('no_seller_products_match_this_review_box') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body d-flex justify-content-end">{{ $products->links() }}</div>
        </div>
    </div>
@endsection
