<section class="dashboard-list-shell">
    <div class="dashboard-panel-header">
        <div><h3 class="mb-1 fw-bold fs-16"><i class="fi fi-sr-shop text-primary me-2"></i>{{ translate('Stores') }}</h3><p class="mb-0 text-muted fs-12">{{ translate('store_performance_overview') }}</p></div>
    </div>
    <div class="p-3">
        <div class="row g-3">
            @foreach([
                ['title'=>'Most_Popular_Stores','items'=>$topVendorListByWishlist,'url'=>route('admin.vendors.vendor-list', ['sort_by'=>'most-favorite']),'metric'=>'wishlist'],
                ['title'=>'top_selling_stores','items'=>$topVendorByEarning,'url'=>route('admin.vendors.vendor-list', ['sort_by'=>'orders_count']),'metric'=>'earning'],
            ] as $storeGroup)
                <div class="col-xl-6">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <h4 class="mb-0 fs-14 fw-bold">{{ translate($storeGroup['title']) }}</h4>
                            @if($storeGroup['items'] && count($storeGroup['items']) > 0)<a href="{{ $storeGroup['url'] }}" class="text-primary fs-12 fw-bold">{{ translate('View_All') }}</a>@endif
                        </div>
                        @if($storeGroup['items'] && count($storeGroup['items']) > 0)
                            <div class="d-grid gap-2">
                                @foreach($storeGroup['items'] as $key => $item)
                                    @php
                                        $seller = $storeGroup['metric'] === 'earning' ? $item->seller : $item;
                                        $shop = $seller?->shop;
                                    @endphp
                                    @if($shop)
                                        <a href="{{ route('admin.vendors.view', $seller->id) }}" class="dashboard-list-item text-decoration-none text-body">
                                            <span class="dashboard-rank">{{ $key + 1 }}</span>
                                            <img width="46" class="rounded-circle aspect-1 border" src="{{ getStorageImages(path: $shop->image_full_url, type:'backend-basic') }}" alt="">
                                            <span class="flex-grow-1 min-w-0"><strong class="d-block line-1 text-hover-primary">{{ $shop->name ?? translate('not_exist') }}</strong><span class="d-block text-muted fs-11">{{ $storeGroup['metric'] === 'earning' ? translate('total_earning') : translate('favorites') }}</span></span>
                                            <strong class="text-primary fs-12">{{ $storeGroup['metric'] === 'earning' ? setCurrencySymbol(amount: currencyConverter(amount: $item->total_earning)) : number_format($item->wishlist_count ?? 0) }}</strong>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="py-5">@include('layouts.admin.partials._empty-state', ['text'=>'No_stores_available','image'=>'store','width'=>45])</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
