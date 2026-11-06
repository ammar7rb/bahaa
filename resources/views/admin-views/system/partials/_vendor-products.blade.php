<section class="dashboard-list-shell">
    <div class="dashboard-panel-header">
        <div><h3 class="mb-1 fw-bold fs-16"><i class="fi fi-sr-boxes text-primary me-2"></i>{{ translate('Vendor_Products') }}</h3><p class="mb-0 text-muted fs-12">{{ translate('best_selling_vendor_products') }}</p></div>
        @if(isset($vendorTopSellProduct) && count($vendorTopSellProduct) > 0)
            <a href="{{ route('admin.products.list', ['type'=>'vendor','filter_sort_by'=>'best-selling','request_status'=>1]) }}" class="btn btn-outline-primary btn-sm">{{ translate('View_All') }}</a>
        @endif
    </div>
    <div class="p-3">
        @if(isset($vendorTopSellProduct) && count($vendorTopSellProduct) > 0)
            <div class="row g-2">
                @foreach($vendorTopSellProduct as $key => $product)
                    @if(isset($product['id']))
                        <div class="col-md-6 col-xl-4">
                            <a href="{{ route('admin.products.view', ['addedBy'=>'vendor','id'=>$product['id']]) }}" class="dashboard-list-item text-decoration-none text-body">
                                <span class="dashboard-rank">{{ $key + 1 }}</span>
                                <img width="52" class="rounded-3 aspect-1 border" src="{{ getStorageImages(path: $product->thumbnail_full_url, type:'backend-product') }}" alt="{{ $product['name'] }}">
                                <span class="flex-grow-1 min-w-0"><strong class="d-block line-1 text-hover-primary">{{ $product['name'] }}</strong><span class="d-block text-muted fs-11 mt-1">{{ translate('sold') }}: {{ number_format($product->total_qty_sold ?? 0) }}</span></span>
                                <strong class="text-primary fs-12">{{ webCurrencyConverter($product->totalSoldAmount ?? 0) }}</strong>
                            </a>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            <div class="py-5">@include('layouts.admin.partials._empty-state', ['text'=>'No_Product_Available','image'=>'product','width'=>45])</div>
        @endif
    </div>
</section>
