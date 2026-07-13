<div class="position-relative z-index-99 rtl w-100 text-align-direction {{ $displayClass }}">
    <div class="__rounded-10 bg-white position-relative">
        <div class="d-flex flex-wrap justify-content-between seller-details">
            <div class="d-flex align-items-center p-2 flex-grow-1">
                <div class="">
                    @if($shopInfoArray['id'] != 0)
                        <div
                            class="position-relative overflow-hidden d-flex align-items-center aspect-1 rounded w-90px">
                            @if(checkVendorAbility(type: 'vendor', status: 'temporary_close', vendor: $shopInfoArray))
                                <span class="temporary-closed-details p-1">
                                    <span>{{ translate('Temporary_OFF') }}</span>
                                </span>
                            @elseif(checkVendorAbility(type: 'vendor', status: 'vacation_status', vendor: $shopInfoArray))
                                <span class="temporary-closed-details p-1">
                                    <span>{{ translate('closed_now') }}</span>
                                </span>
                            @endif
                            <img class="img-fluid h-100 object-cover" alt="{{ $shopInfoArray['name'] }}"
                                 src="{{ getStorageImages(path: $shopInfoArray['image_full_url'], type: 'shop') }}">
                        </div>
                    @else
                        <div
                            class="position-relative overflow-hidden d-flex align-items-center aspect-1 rounded w-90px">
                            @if(checkVendorAbility(type: 'inhouse', status: 'temporary_close', vendor: $shopInfoArray))
                                <span class="temporary-closed-details px-2">
                                    <span>{{ translate('Temporary_OFF') }}</span>
                                </span>
                            @elseif(checkVendorAbility(type: 'inhouse', status: 'vacation_status', vendor: $shopInfoArray))
                                <span class="temporary-closed-details px-2">
                                    <span>{{ translate('closed_now') }}</span>
                                </span>
                            @endif
                            <img class="img-fluid h-100 object-cover" alt="{{ getInHouseShopConfig(key:'name') }}"
                                 src="{{ getStorageImages(path: getInHouseShopConfig(key: 'image_full_url'), type:'shop') }}">

                        </div>
                    @endif
                </div>
                <div
                    class="__w-100px flex-grow-1 {{Session::get('direction') === "rtl" ? ' pr-2 pr-sm-4' : ' pl-2 pl-sm-4'}}">
                    <div class="font-weight-bolder mb-2">
                        @if($shopInfoArray['id'] != 0)
                            {{ $shopInfoArray['name']}}
                        @else
                            {{ getInHouseShopConfig(key:'name') }}
                        @endif
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <div class="fs-12">
                            @for ($i = 1; $i <= 5; $i++)
                                @if ($i <=$shopInfoArray['average_rating'])
                                    <i class="tio-star text-warning"></i>
                                @elseif ($shopInfoArray['average_rating'] != 0 && $i <= (int)$shopInfoArray['average_rating'] + 1 && $shopInfoArray['average_rating']>=((int)$shopInfoArray['average_rating']+.30))
                                    <i class="tio-star-half text-warning"></i>
                                @else
                                    <i class="tio-star-outlined text-warning"></i>
                                @endif
                            @endfor
                            <span class="__inline-69"></span>
                            <span class="text-nowrap fs-13 font-semibold text-base">
                                {{ $shopInfoArray['total_review']}} {{ translate('reviews') }}
                            </span>
                        </div>

                        <div class="d-flex flex-wrap py-1 fs-12 web-text-primary">
                            <span class="text-nowrap">
                                {{ $shopInfoArray['total_order']}} {{ translate('orders') }}
                            </span>
                            @php($minimum_order_amount_status = getWebConfig(name: 'minimum_order_amount_status'))
                            @php($minimum_order_amount_by_seller = getWebConfig(name: 'minimum_order_amount_by_seller'))
                            @if ($minimum_order_amount_status == 1 && $minimum_order_amount_by_seller == 1 && $shopInfoArray['minimum_order_amount'] > 0)
                                <span class="__inline-69"></span>
                                <span>{{ webCurrencyConverter(amount: $shopInfoArray['minimum_order_amount']) }} {{ translate('minimum_order_amount') }}</span>
                            @endif
                        </div>

                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center">
                <div class="{{ session('direction') === "rtl" ? 'ml-sm-4' : 'mr-sm-4' }}">
                    @if (auth('customer')->check())
                        <div class="d-flex ml-4-mobile">
                            <a href="{{ route('account-tickets') }}"
                               class="btn btn--primary __inline-70 rounded-10 btn-sm text-capitalize chat-with-seller-button">
                                {{ translate('support_ticket') }}
                            </a>
                        </div>
                    @else
                        <div class="d-flex">
                            <a href="{{ route('customer.auth.login') }}"
                               class="btn btn--primary __inline-70 rounded-10 btn-sm text-capitalize chat-with-seller-button">
                                {{ translate('support_ticket') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
