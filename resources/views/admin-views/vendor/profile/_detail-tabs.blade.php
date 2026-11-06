<div class="page-header border-0 mb-4">
    <div class="position-relative nav--tab-wrapper">
        <ul class="nav nav-pills nav--tab">
            <li class="nav-item"><a class="nav-link active" href="{{ route('admin.vendors.view', $seller->id) }}">{{ translate('seller_administrative_profile') }}</a></li>
            @if ($seller->status != 'pending')
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'order']) }}">{{ translate('order') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'product']) }}">{{ translate('product') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'clearance_sale']) }}">{{ translate('clearance_sale_products') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'setting']) }}">{{ translate('setting') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'transaction']) }}">{{ translate('transaction') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.vendors.view', ['id' => $seller->id, 'tab' => 'review']) }}">{{ translate('review') }}</a></li>
            @endif
        </ul>
        <div class="nav--tab__prev"><button type="button" class="btn btn-circle border-0 bg-white text-primary"><i class="fi fi-sr-angle-left"></i></button></div>
        <div class="nav--tab__next"><button type="button" class="btn btn-circle border-0 bg-white text-primary"><i class="fi fi-sr-angle-right"></i></button></div>
    </div>
</div>
