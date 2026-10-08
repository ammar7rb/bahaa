@extends('layouts.vendor.app')

@section('title', $type == 'new-request' ? translate('active') . ' ' . translate('products') : translate($type == 'approved' ? 'Approved_Products' : 'products'))

@section('content')
    <div class="content container-fluid vendor-products">

        <div class="vendor-products-heading">
            <h2 class="h1 mb-0 text-capitalize d-flex gap-2 align-items-center">
                @include('layouts.vendor.partials.v2._workspace-icon',['icon'=>'products'])
                {{ $type == 'new-request' ? translate('active') . ' ' . translate('products') : translate($type=='approved'?'approved_products':'products') }}
                <span class="badge badge-soft-dark radius-50 fz-14 ml-1">
                    {{ $products->total() }}
                </span>
            </h2>
            <p>{{ translate('vendor_products_intro') }}</p>
        </div>

        <div class="row mt-20">
            <div class="col-md-12">
                <div class="card">
                    <div class="vendor-products-toolbar">
                        <div class="row align-items-center">
                            <div class="col-lg-8">

                                <form action="{{ url()->current() }}" method="GET">
                                    <div class="input-group input-group-custom input-group-merge">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                @include('layouts.vendor.partials.v2._workspace-icon',['icon'=>'search'])
                                            </div>
                                        </div>
                                        <input id="datatableSearch_" type="search" name="searchValue"
                                               class="form-control"
                                               placeholder="{{ translate('search_by_Product_Name') }}"
                                               aria-label="{{ translate('search_by_Product_Name') }}"
                                               value="{{ request('searchValue') }}">
                                        <button type="submit" class="btn btn--primary">{{ translate('search') }}</button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-lg-4 mt-3 mt-lg-0 d-flex flex-wrap gap-3 justify-content-lg-end">
                                @if($type != 'new-request' )
                                <a href="{{ route('vendor.products.add') }}" class="btn btn--primary flex-grow-1 flex-grow-sm-0">
                                    @include('layouts.vendor.partials.v2._workspace-icon',['icon'=>'add'])
                                    <span class="text">{{ translate('add_new_product') }}</span>
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="datatable"
                               class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100 text-start">
                            <thead class="thead-light thead-50 text-capitalize">
                            <tr>
                                <th class="vendor-product-serial">#</th>
                                <th class="text-capitalize">{{ translate('product_name') }}</th>

                                <th class="text-center text-capitalize">{{ translate('vendor_product_price') }}</th>
                                <th class="text-center">{{ translate('stock') }}</th>
                                @if ($productWiseTax)
                                    <th class="text-center">{{ translate('Vat/Tax') }}</th>
                                @endif

                                <!-- <th class="text-center text-capitalize">{{ translate('verify_status') }}</th> -->
                                @if($type != 'new-request' )
                                <th class="text-center text-capitalize">{{ translate('vendor_product_visibility') }}</th>
                                @endif
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($products as $key=>$product)
                                <tr>
                                    <th scope="row">{{ $products->firstItem()+$key}}</th>
                                    <td>
                                        <a href="{{ route('vendor.products.view', [$product['id']]) }}"
                                           class="media align-items-center gap-2">
                                            <img src="{{ getStorageImages(path:$product->thumbnail_full_url,type:'backend-product')}}"
                                                class="avatar border min-w-45 min-h-45 object-fit-cover" alt="">
                                            <div>
                                                <div class="d-flex align-items-center gap-1">
                                                    <div class="media-body title-color mb-1 hover-c1" data-toggle="tooltip" title="{{ $product['name'] }}">
                                                        {{ $product['name'] }}
                                                    </div>
                                                    @if($product?->clearanceSale)
                                                        <span class="text-secondary-base fs-12" data-placement="right" data-toggle="tooltip" title="{{ translate('Clearance_Sale') }}">
                                                            <i class="fi fi-sr-bahai"></i>
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <span class="text-secondary">{{ translate('vendor_product_reference') }} {{ $product->administrative_reference }}</span>
                                                </div>
                                            </div>
                                        </a>
                                    </td>

                                    <td class="text-center">
                                        {{setCurrencySymbol(amount: usdToDefaultCurrency(amount: $product['unit_price']), currencyCode: getCurrencyCode()) }}
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center mx-auto w-80px gap-3 align-items-center lh-1">
                                            @if ($product['product_type'] === 'physical')
                                                <span>{{ $product->current_stock }}</span>
                                                @if ($product->current_stock <= 0)
                                                    <span class="text-danger-dark fs-18"
                                                          data-toggle="tooltip" data-placement="right"
                                                          title="{{ translate('Out_of_Stock') }}">
                                                            <i class="fi fi-sr-exclamation"></i>
                                                        </span>
                                                @elseif ($product->current_stock <= 20)
                                                    <span class="text-warning-dark fs-18"
                                                          data-toggle="tooltip" data-placement="right"
                                                          title="{{ translate('Low_Stock') }}">
                                                        <i class="fi fi-sr-exclamation"></i>
                                                    </span>
                                                @endif
                                            @else
                                                <span>-</span>
                                            @endif
                                        </div>
                                    </td>
                                    @if ($productWiseTax)
                                        <td class="text-center">
                                                <span class="">
                                                    @forelse ($product?->taxVats as $key => $taxVat)
                                                        <span>{{ $taxVat?->tax?->name }} :
                                                            <span class="font-bold">
                                                                ({{ $taxVat?->tax?->tax_rate }}%)
                                                            </span>
                                                        </span>
                                                        <br>
                                                    @empty
                                                        <span>{{ translate('N/A') }}</span>
                                                    @endforelse
                                                </span>
                                        </td>
                                    @endif
                                    @if($type != 'new-request' )
                                        <td class="text-center">
                                            @php($productName = str_replace("'",'`',$product['name']))
                                            <form action="{{ route('vendor.products.status-update') }}" method="post" data-from="product-status"
                                                  id="product-status{{ $product['id']}}-form" class="admin-product-status-form">
                                                @csrf
                                                <input type="hidden" name="id" value="{{ $product['id']}}">
                                                <label class="switcher mx-auto">
                                                    <input type="checkbox" class="switcher_input toggle-switch-message"
                                                           name="status"
                                                           id="product-status{{ $product['id'] }}" value="1"
                                                           {{ $product['status'] == 1 ? 'checked' : '' }}
                                                           data-modal-id="toggle-status-modal"
                                                           data-toggle-id="product-status{{ $product['id'] }}"
                                                           data-on-image="product-status-on.png"
                                                           data-off-image="product-status-off.png"
                                                           data-on-title="{{ translate('Want_to_Turn_ON').' '.$productName.' '.translate('status') }}"
                                                           data-off-title="{{ translate('Want_to_Turn_OFF').' '.$productName.' '.translate('status') }}"
                                                           data-on-message="<p>{{ translate('if_enabled_this_product_will_be_available_on_the_website_and_customer_app') }}</p>"
                                                           data-off-message="<p>{{ translate('if_disabled_this_product_will_be_hidden_from_the_website_and_customer_app') }}</p>">
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </form>
                                        </td>
                                    @endif
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a class="btn btn-outline--success icon-btn square-btn" title="{{ translate('view') }}" aria-label="{{ translate('view') }}"
                                               href="{{ route('vendor.products.view', [$product['id']]) }}">
                                                @include('layouts.vendor.partials.v2._workspace-icon',['icon'=>'view'])
                                            </a>
                                            <a class="btn btn-outline--primary btn-sm square-btn"
                                               title="{{ translate('edit') }}" aria-label="{{ translate('edit') }}"
                                               href="{{ route('vendor.products.update',[$product['id']]) }}">
                                                @include('layouts.vendor.partials.v2._workspace-icon',['icon'=>'edit'])
                                            </a>
                                            <button type="button" class="btn btn-outline-danger btn-sm square-btn delete-data"
                                                  title="{{ translate('delete') }}" aria-label="{{ translate('delete') }}"
                                                  data-id="product-{{ $product['id']}}">
                                                @include('layouts.vendor.partials.v2._workspace-icon',['icon'=>'delete'])
                                            </button>
                                        </div>
                                        <form action="{{ route('vendor.products.delete',[$product['id']]) }}"
                                              method="post" id="product-{{ $product['id']}}">
                                            @csrf @method('delete')
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive mt-4">
                        <div class="px-4 d-flex justify-content-lg-end">
                            {{ $products->links() }}
                        </div>
                    </div>

                    @if(count($products)==0)
                        @include('layouts.vendor.partials._empty-state',['text'=>'no_product_found'],['image'=>'default'])
                    @endif


                </div>
            </div>
        </div>
    </div>
    <span id="message-select-word" data-text="{{ translate('select') }}"></span>
@endsection
