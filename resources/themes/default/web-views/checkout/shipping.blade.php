@extends('layouts.front-end.app')

@section('title',translate('shipping_Address'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/bootstrap-select.min.css') }}">
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/plugin/intl-tel-input/css/intlTelInput.css') }}">
@endpush

@section('content')
    @php($billingInputByCustomer=getWebConfig(name: 'billing_input_by_customer'))
    <div class="container py-4 pt-3 rtl __inline-56 px-0 px-md-3 text-align-direction checkout-shipping-page">
        <div class="row mx-max-md-0">
            <section class="col-lg-8 px-max-md-0">
                <div class="checkout_details">
                    <div class="px-3 px-md-3 mb-20">
                        @include('web-views.partials._checkout-steps',['step'=>2])
                    </div>
                    @php($defaultLocation = getWebConfig(name: 'default_location'))

                    @if($physical_product_view)
                        <input type="hidden" id="physical_product" name="physical_product" value="{{ $physical_product_view ? 'yes':'no'}}">
                        <div class="px-3 px-md-0">
                            <h4 class="pb-2 mt-4 fs-18 text-capitalize">{{ translate('shipping_address')}}</h4>
                        </div>

                        @php($shippingAddresses= \App\Models\ShippingAddress::where(['customer_id'=>auth('customer')->id(), 'is_guest'=>0])->get())
                        <form method="post" class="card __card" id="address-form">
                            <div class="card-body p-0">
                                <ul class="list-group">
                                    <li class="list-group-item add-another-address">
                                        @if ($shippingAddresses->count() >0)
                                            <div class="d-flex align-items-center justify-content-end gap-3">
                                                <div class="dropdown">
                                                    <button class="form-control dropdown-toggle text-capitalize" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        {{translate('saved_address')}}
                                                    </button>

                                                    <div class="dropdown-menu dropdown-menu-right saved-address-dropdown scroll-bar-saved-address" aria-labelledby="dropdownMenuButton">
                                                        @foreach($shippingAddresses as $key => $address)
                                                            <div class="dropdown-item select_shipping_address {{$key == 0 ? 'active' : ''}}" id="shippingAddress{{$key}}">
                                                                <input type="hidden" class="selected_shippingAddress{{$key}}" value="{{$address}}">
                                                                <input type="hidden" name="shipping_method_id" value="{{$address['id']}}">
                                                                <div class="media gap-2">
                                                                    <div class="">
                                                                        <i class="tio-briefcase"></i>
                                                                    </div>
                                                                    <div class="media-body">
                                                                        <div class="mb-1 text-capitalize">{{$address->address_type}}</div>
                                                                        <div class="text-muted fs-12 text-capitalize text-wrap">{{$address->address}}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        <div id="accordion">
                                            <div class="">
                                                <div class="mt-3">
                                                    <div class="row">
                                                        <div class="col-sm-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('contact_person_name')}}
                                                                    <span class="text-danger">*</span>
                                                                </label>
                                                                <input type="text" class="form-control" name="contact_person_name" {{$shippingAddresses->count()==0?'required':''}} id="name">
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('phone')}}
                                                                    <span class="text-danger">*</span>
                                                                </label>
                                                                <input type="tel" class="form-control" id="phone" {{ $shippingAddresses->count()==0?'required':'' }} name="phone">
                                                            </div>
                                                        </div>
                                                        @if(!auth('customer')->check())
                                                            <div class="col-sm-12">
                                                                <div class="form-group">
                                                                    <label for="exampleInputEmail1">
                                                                        {{ translate('email')}}
                                                                        <span class="text-danger">*</span>
                                                                    </label>
                                                                    <input type="email" class="form-control"  name="email" id="email" {{$shippingAddresses->count()==0?'required':''}}>
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('address_type')}}</label>
                                                                <select class="form-control" name="address_type" id="address_type">
                                                                    <option value="permanent">{{ translate('permanent')}}</option>
                                                                    <option value="home">{{ translate('home')}}</option>
                                                                    <option value="office">{{ translate('office')}}</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('country')}}
                                                                    <span class="text-danger">*</span></label>
                                                                <input type="hidden" name="country" value="Egypt">
                                                                <input type="text" class="form-control" value="Egypt" readonly>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('governorate')}}<span class="text-danger">*</span></label>
                                                                <select class="form-control" name="state" id="state" {{$shippingAddresses->count()==0?'required':''}}>
                                                                    <option value="" selected disabled>{{ translate('select')}} {{ translate('governorate') }}</option>
                                                                    @foreach(config('egypt.governorates') as $governorate)
                                                                        <option value="{{ $governorate }}">{{ $governorate }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('city')}}<span  class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" name="city" id="city" {{$shippingAddresses->count()==0?'required':''}}>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>الحي أو القسم (اختياري)</label>
                                                                <input type="text" class="form-control" name="district" id="district">
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>المنطقة (اختياري)</label>
                                                                <input type="text" class="form-control" name="area" id="area">
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>الشارع (اختياري)</label>
                                                                <input type="text" class="form-control" name="street" id="street">
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>رقم العقار (اختياري)</label>
                                                                <input type="text" class="form-control" name="building_number" id="building-number">
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>أقرب علامة مميزة</label>
                                                                <input type="text" class="form-control" name="landmark" id="landmark">
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('zip_code')}} ({{ translate('optional') }})</label>
                                                                @if($zip_restrict_status == 1)
                                                                    <select name="zip" class="form-control selectpicker" data-live-search="true" id="select2-zip-container">
                                                                        <option value="">{{ translate('optional') }}</option>
                                                                        @forelse($zip_codes as $code)
                                                                            <option value="{{ $code->zipcode }}">{{ $code->zipcode }}</option>
                                                                        @empty
                                                                            <option value="">{{ translate('no_zip_to_deliver') }}</option>
                                                                        @endforelse
                                                                    </select>
                                                                @else
                                                                    <input type="text" class="form-control"
                                                                           name="zip" id="zip">
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="form-group mb-1">
                                                                <label>{{ translate('address')}}<span class="text-danger">*</span></label>
                                                                <textarea class="form-control" id="address" type="text" name="address" {{$shippingAddresses->count()==0?'required':''}}></textarea>
                                                                <span class="fs-14 text-danger font-semi-bold opacity-0 map-address-alert">
                                                                    {{ translate('note') }}: {{ translate('you_need_to_select_address_from_your_selected_country') }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        @if($physical_product_view)
                                                            <div class="col-12 mb-3">
                                                                <strong class="d-block mb-2">اختر طريقة الشحن لهذا العنوان</strong>
                                                                <div class="d-flex flex-wrap gap-2">
                                                                    <label class="border rounded p-3 flex-fill cursor-pointer"><input type="radio" name="preferred_shipping_option" value="normal"> شحن عادي</label>
                                                                    <label class="border rounded p-3 flex-fill cursor-pointer"><input type="radio" name="preferred_shipping_option" value="sigma"> شحن سيجما <small class="d-block text-primary">موثّق وموصى به من سيجما، ويُضاف تلقائيًا إلى الفاتورة.</small></label>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="d-flex gap-3 align-items-center">
                                                        <label class="form-check-label d-flex gap-2 align-items-center" id="save_address_label">
                                                            <input type="hidden" name="shipping_method_id" id="shipping_method_id" value="0">
                                                            @if(auth('customer')->check())
                                                                <input type="checkbox" name="save_address" id="save_address">
                                                                {{ translate('save_this_Address') }}
                                                            @endif
                                                        </label>
                                                    </div>

                                                    <input type="hidden" id="latitude"
                                                           name="latitude" class="form-control d-inline"
                                                           placeholder="{{ translate('ex')}} : -94.22213"
                                                           value="{{$defaultLocation?$defaultLocation['lat']:0}}" required
                                                           readonly>
                                                    <input type="hidden"
                                                           name="longitude" class="form-control"
                                                           placeholder="{{ translate('ex')}} : 103.344322" id="longitude"
                                                           value="{{$defaultLocation?$defaultLocation['lng']:0}}" required
                                                           readonly>

                                                    <button type="submit" class="btn btn--primary d--none" id="address_submit"></button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </form>

                        @if(!Auth::guard('customer')->check() && $web_config['guest_checkout_status'])
                            <div class="card __card mt-3">
                                <div class="card-body">
                                    <div class="d-flex align-items-center flex-wrap justify-content-between gap-3">
                                        <div class="min-h-45 form-check d-flex gap-3 align-items-center cursor-pointer user-select-none">
                                            <input type="checkbox" id="is_check_create_account" name="is_check_create_account" class="form-check-input mt-0" value="1">
                                            <label class="form-check-label font-weight-bold fs-13" for="is_check_create_account">
                                                {{translate('Create_an_account_with_the_above_info')}}
                                            </label>
                                        </div>

                                        <div class="is_check_create_account_password_group d--none">
                                            <div class="d-flex gap-3 flex-wrap flex-sm-nowrap">
                                                <div class="w-100">
                                                    <div class="password-toggle rtl">
                                                        <input class="form-control text-align-direction" name="customer_password" type="password" id="customer_password" placeholder="{{ translate('new_Password') }}" required>
                                                        <label class="password-toggle-btn">
                                                            <input class="custom-control-input" type="checkbox">
                                                            <i class="tio-hidden password-toggle-indicator"></i>
                                                            <span class="sr-only">{{ translate('show_password') }}</span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="w-100">
                                                    <div class="password-toggle rtl">
                                                        <input class="form-control text-align-direction w-100" name="customer_confirm_password" type="password" id="customer_confirm_password" placeholder="{{ translate('confirm_Password') }}" required>
                                                        <label class="password-toggle-btn">
                                                            <input class="custom-control-input" type="checkbox">
                                                            <i class="tio-hidden password-toggle-indicator"></i>
                                                            <span class="sr-only">{{ translate('show_password') }}</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                    @if($billingInputByCustomer)
                        <div>
                            <div class="billing-methods_label d-flex flex-wrap justify-content-between gap-2 mt-4 pb-3 px-3 px-md-0">
                                <h4 class="mb-0 fs-18 text-capitalize">{{ translate('billing_address')}}</h4>

                                @php($billingAddresses=\App\Models\ShippingAddress::where(['customer_id'=>auth('customer')->id(), 'is_guest'=>'0'])->get())
                                @if($physical_product_view)
                                    <div class="form-check d-flex gap-3 align-items-center">
                                        <input type="checkbox" id="same_as_shipping_address" name="same_as_shipping_address"
                                               class="form-check-input action-hide-billing-address mt-0" {{$billingInputByCustomer==1?'':'checked'}}>
                                        <label class="form-check-label user-select-none" for="same_as_shipping_address">
                                            {{ translate('same_as_shipping_address')}}
                                        </label>
                                    </div>
                                @endif
                            </div>

                            @if(!$physical_product_view)
                                <div class="mb-3 alert--info">
                                    <div class="d-flex align-items-center gap-2">
                                        <img class="mb-1" src="{{ theme_asset('public/assets/front-end/img/icons/info-light.svg') }}" alt="Info">
                                        <span>{{ translate('When you input all the required information and save or update it, this billing address will be stored for future purchases.') }}</span>
                                    </div>
                                </div>
                            @endif

                            <form method="post" class="card __card" id="billing-address-form">
                                <div id="hide_billing_address" class="">
                                    <ul class="list-group">

                                        <li class="list-group-item action-billing-address-hide">
                                            @if ($billingAddresses->count() >0)
                                                <div class="d-flex align-items-center justify-content-end gap-3">

                                                    <div class="dropdown">
                                                        <button class="form-control dropdown-toggle text-capitalize" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                            {{translate('saved_address')}}
                                                        </button>

                                                        <div class="dropdown-menu dropdown-menu-right saved-address-dropdown scroll-bar-saved-address" aria-labelledby="dropdownMenuButton">
                                                            @foreach($billingAddresses as $key=>$address)
                                                                <div class="dropdown-item select_billing_address {{$key == 0 ? 'active' : ''}}" id="billingAddress{{$key}}">
                                                                    <input type="hidden" class="selected_billingAddress{{$key}}" value="{{$address}}">
                                                                    <input type="hidden" name="billing_method_id" value="{{$address['id']}}">
                                                                    <div class="media gap-2">
                                                                        <div class="">
                                                                            <i class="tio-briefcase"></i>
                                                                        </div>
                                                                        <div class="media-body">
                                                                            <div class="mb-1 text-capitalize">{{$address->address_type}}</div>
                                                                            <div class="text-muted fs-12 text-capitalize text-wrap">{{$address->address}}</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                            <div id="accordion">
                                                <div class="">
                                                    <div class="">
                                                        <div class="row">
                                                            <div class="col-sm-6">
                                                                <div class="form-group">
                                                                    <label>{{ translate('contact_person_name')}}<span class="text-danger">*</span></label>
                                                                    <input type="text" class="form-control"
                                                                           name="billing_contact_person_name" id="billing_contact_person_name"  {{$billingAddresses->count()==0?'required':''}}>
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <div class="form-group">
                                                                    <label>{{ translate('phone')}}
                                                                        <span class="text-danger">*</span>
                                                                    </label>
                                                                    <input type="tel" class="form-control phone-input-with-country-picker-2" name="billing_phone"
                                                                           id="billing_phone" {{ $billingAddresses->count()==0 ? 'required' : '' }}>
                                                                </div>
                                                            </div>
                                                            @if(!auth('customer')->check())
                                                                <div class="col-sm-12">
                                                                    <div class="form-group">
                                                                        <label
                                                                            for="exampleInputEmail1">{{ translate('email')}}
                                                                            <span class="text-danger">*</span></label>
                                                                        <input type="email" class="form-control"
                                                                               name="billing_contact_email" id="billing_contact_email" id {{$billingAddresses->count()==0?'required':''}}>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label>{{ translate('address_type')}}</label>
                                                                    <select class="form-control" name="billing_address_type" id="billing_address_type">
                                                                        <option value="permanent">{{ translate('permanent')}}</option>
                                                                        <option value="home">{{ translate('home')}}</option>
                                                                        <option value="office">{{ translate('office')}}</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label>{{ translate('country')}}<span class="text-danger">*</span></label>
                                                                    <input type="hidden" name="billing_country" value="Egypt">
                                                                    <input type="text" class="form-control" value="Egypt" readonly>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label>{{ translate('governorate')}}<span class="text-danger">*</span></label>
                                                                    <select class="form-control" name="billing_state" id="billing_state" {{$billingAddresses->count()==0?'required':''}}>
                                                                        <option value="" selected disabled>{{ translate('select')}} {{ translate('governorate') }}</option>
                                                                        @foreach(config('egypt.governorates') as $governorate)
                                                                            <option value="{{ $governorate }}">{{ $governorate }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label for="exampleInputEmail1">{{ translate('city')}}<span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="text" class="form-control" id="billing_city"
                                                                           name="billing_city" {{$billingAddresses->count()==0?'required':''}}>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label>{{ translate('zip_code')}}
                                                                        <span class="text-danger">*</span></label>
                                                                    @if($zip_restrict_status)
                                                                        <select name="billing_zip" class="form-control selectpicker" data-live-search="true" id="billing_zip">
                                                                            @foreach($zip_codes as $code)
                                                                                <option value="{{ $code->zipcode }}">{{ $code->zipcode }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    @else
                                                                        <input type="text" class="form-control" id="billing_zip"
                                                                               name="billing_zip">
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="form-group mb-1">
                                                            <label>{{ translate('address')}}<span class="text-danger">*</span></label>
                                                            <textarea class="form-control" id="billing_address" type="billing_text" name="billing_address" id="billing_address" {{$billingAddresses->count()==0?'required':''}}></textarea>

                                                            <span class="fs-14 text-danger font-semi-bold opacity-0 map-address-alert">
                                                            {{ translate('note') }}: {{ translate('you_need_to_select_address_from_your_selected_country') }}
                                                        </span>
                                                        </div>
                                                        @if(false)
                                                            <div class="form-group map-area-alert-border location-map-billing-canvas-area">
                                                                <input id="pac-input-billing" class="controls rounded __inline-46 location-search-input-field"
                                                                       title="{{translate('search_your_location_here')}}"
                                                                       type="text"
                                                                       placeholder="{{translate('search_here')}}"/>
                                                                <div class="__h-200px" id="location_map_canvas_billing"></div>
                                                            </div>
                                                        @endif

                                                        <input type="hidden" name="billing_method_id" id="billing_method_id" value="0">
                                                        @if(auth('customer')->check())
                                                            <div class=" d-flex gap-3 align-items-center">
                                                                <label class="form-check-label d-flex gap-2 align-items-center" id="save-billing-address-label">
                                                                    <input type="checkbox" name="save_address_billing" id="save_address_billing">
                                                                    {{ translate('save_this_Address') }}
                                                                </label>
                                                            </div>
                                                        @endif

                                                        <input type="hidden" id="billing_latitude"
                                                               name="billing_latitude" class="form-control d-inline"
                                                               placeholder="{{ translate('ex')}} : -94.22213"
                                                               value="{{$defaultLocation?$defaultLocation['lat']:0}}" required
                                                               readonly>
                                                        <input type="hidden"
                                                               name="billing_longitude" class="form-control"
                                                               placeholder="{{ translate('ex')}} : 103.344322" id="billing_longitude"
                                                               value="{{$defaultLocation?$defaultLocation['lng']:0}}" required
                                                               readonly>

                                                        <button type="submit" class="btn btn--primary d--none" id="address_submit"></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </form>
                        </div>

                        @if(!Auth::guard('customer')->check() && $web_config['guest_checkout_status'] && !$physical_product_view)
                            <div class="card __card mt-3">
                                <div class="card-body">
                                    <div class="d-flex align-items-center flex-wrap justify-content-between gap-3">
                                        <div class="min-h-45 form-check d-flex gap-3 align-items-center cursor-pointer user-select-none">
                                            <input type="checkbox" id="is_check_create_account" name="is_check_create_account" class="form-check-input mt-0" value="1">
                                            <label class="form-check-label font-weight-bold fs-13" for="is_check_create_account">
                                                {{translate('Create_an_account_with_the_above_info')}}
                                            </label>
                                        </div>

                                        <div class="is_check_create_account_password_group d--none">
                                            <div class="d-flex gap-3 flex-wrap flex-sm-nowrap">
                                                <div class="w-100">
                                                    <div class="password-toggle rtl">
                                                        <input class="form-control text-align-direction" name="customer_password" type="password" id="customer_password" placeholder="{{ translate('new_Password')}}" required>
                                                        <label class="password-toggle-btn">
                                                            <input class="custom-control-input" type="checkbox">
                                                            <i class="tio-hidden password-toggle-indicator"></i>
                                                            <span class="sr-only">{{ translate('show_password') }}</span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="w-100">
                                                    <div class="password-toggle rtl">
                                                        <input class="form-control text-align-direction" name="customer_confirm_password" type="password" id="customer_confirm_password" placeholder="{{ translate('confirm_Password')}}" required>
                                                        <label class="password-toggle-btn">
                                                            <input class="custom-control-input" type="checkbox">
                                                            <i class="tio-hidden password-toggle-indicator"></i>
                                                            <span class="sr-only">{{ translate('show_password') }}</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            @if($physical_product_view && !empty($shipping_quote_options))
                <div class="card __card mt-3" id="automatic-shipping-options">
                    <div class="card-body">
                        <h5 class="mb-1">اختر طريقة الشحن</h5>
                        <p class="text-muted mb-3">اختر الشحن المناسب لعنوانك. تُضاف تكلفة الشحن تلقائيًا إلى الفاتورة.</p>
                        <div class="row g-3">
                            @foreach(['normal', 'sigma'] as $shippingOptionKey)
                                @php($shippingOption = $shipping_quote_options[$shippingOptionKey] ?? null)
                                @if($shippingOption)
                                    @php($optionSelected = $automatic_shipping_selected && \App\Models\CartShipping::whereIn('cart_group_id', \App\Utils\CartManager::get_cart_group_ids(type: 'checked'))->where('shipping_method_id', $shippingOption['method_id'])->exists())
                                    <div class="col-md-6">
                                        <button type="button" aria-pressed="{{ $optionSelected ? 'true' : 'false' }}" class="btn btn-outline-primary w-100 text-start p-3 select-automatic-shipping {{ $optionSelected ? 'is-selected' : '' }}"
                                                data-method-id="{{ $shippingOption['method_id'] }}" data-address-id="{{ $selected_shipping_address_id }}" data-shipping-option="{{ $shippingOptionKey }}">
                                            <div class="d-flex justify-content-between align-items-center gap-2">
                                                <strong>{{ $shippingOptionKey === 'sigma' ? 'شحن سيجما' : 'شحن عادي' }}</strong>
                                            </div>
                                            @if($shippingOptionKey === 'sigma')
                                                <div class="small text-primary mt-2">شحن موثّق وموصى به من سيجما، ويُضاف تلقائيًا إلى الفاتورة.</div>
                                            @endif
                                            <div class="small text-muted mt-2">
                                                مدة التوصيل:
                                                @if($shippingOption['minimum_business_days'] === $shippingOption['maximum_business_days'])
                                                    {{ $shippingOption['minimum_business_days'] }} يوم عمل
                                                @else
                                                    {{ $shippingOption['minimum_business_days'] }} - {{ $shippingOption['maximum_business_days'] }} يوم عمل
                                                @endif
                                            </div>
                                        </button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
            </section>
            @include('web-views.partials._order-summary')
        </div>
    </div>

    <span id="message-update-this-address" data-text="{{ translate('Update_this_Address') }}"></span>
    <span id="route-customer-choose-shipping-address-other" data-url="{{ route('customer.choose-shipping-address-other') }}"></span>
    <span id="default-latitude-address" data-value="{{ $defaultLocation ? $defaultLocation['lat']:'-33.8688' }}"></span>
    <span id="default-longitude-address" data-value="{{ $defaultLocation ? $defaultLocation['lng']:'151.2195' }}"></span>
    <span id="route-action-checkout-function" data-route="checkout-details"></span>
    <span id="system-country-restrict-status" data-value="{{ $country_restrict_status }}"></span>
    <span id="route-set-automatic-shipping" data-url="{{ route('customer.set-shipping-method') }}"></span>
    <span id="route-checkout-payment" data-url="{{ route('checkout-payment') }}"></span>
    <span id="automatic-shipping-selected" data-value="{{ $automatic_shipping_selected ? 1 : 0 }}"></span>
@endsection

@push('script')
    <script>
        "use strict";
        const deliveryRestrictedCountries = @json($countriesName);
        function deliveryRestrictedCountriesCheck(countryOrCode, elementSelector, inputElement) {
            const foundIndex = deliveryRestrictedCountries.findIndex(country => country.toLowerCase() === countryOrCode.toLowerCase());
            if (foundIndex !== -1) {
                $(elementSelector).removeClass('map-area-alert-danger');
                $(inputElement).parent().find('.map-address-alert').removeClass('opacity-100').addClass('opacity-0')
            } else {
                $(elementSelector).addClass('map-area-alert-danger');
                $(inputElement).val('')
                $(inputElement).parent().find('.map-address-alert').removeClass('opacity-0').addClass('opacity-100')
            }
        }

        $('#is_check_create_account').on('change', function() {
            if($(this).is(':checked')) {
                $('.is_check_create_account_password_group').fadeIn();
            } else {
                $('.is_check_create_account_password_group').fadeOut();
            }
        });
    </script>
    <script>
        let shippingAddressDirty = false;
        document.querySelectorAll('.select-automatic-shipping').forEach((button) => {
            button.addEventListener('click', async () => {
                const buttons = document.querySelectorAll('.select-automatic-shipping');
                buttons.forEach(item => item.disabled = true);
                document.querySelectorAll('.action-checkout-function').forEach(item => item.classList.add('custom-disabled'));
                try {
                if (shippingAddressDirty) {
                    await $.ajax({
                        url: $('#route-customer-choose-shipping-address-other').data('url'),
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')},
                        data: {
                            physical_product: 'yes', shipping: $('#address-form').serialize(),
                            billing: $('#billing-address-form').serialize(), billing_addresss_same_shipping: true,
                            is_check_create_account: $('#is_check_create_account').prop('checked') ? 1 : 0,
                            customer_password: $('#customer_password').val(),
                            customer_confirm_password: $('#customer_confirm_password').val()
                        }
                    }).then(data => {
                        if (data.errors) throw new Error(typeof data.errors === 'string' ? data.errors : 'راجع بيانات العنوان');
                    });
                    shippingAddressDirty = false;
                }
                const url = document.getElementById('route-set-automatic-shipping').dataset.url;
                const params = new URLSearchParams({id: button.dataset.methodId, cart_group_id: 'all_cart_group'});
                const response = await fetch(`${url}?${params.toString()}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
                const data = await response.json();
                if (!response.ok || !data.status) throw new Error(data.message || 'تعذر تحديث الشحن، حاول مرة أخرى');
                const page = await fetch(window.location.href, {cache: 'no-store'});
                if (!page.ok) throw new Error('تعذر تحديث الإجمالي');
                const updated = new DOMParser().parseFromString(await page.text(), 'text/html');
                for (const selector of ['.order-summery-aside', '.bottom-sticky3']) {
                    const current = document.querySelector(selector);
                    const replacement = updated.querySelector(selector);
                    if (current && replacement) current.replaceWith(replacement);
                }
                $('#automatic-shipping-selected').attr('data-value', '1').data('value', 1);
                buttons.forEach(item => {
                    item.classList.toggle('is-selected', item === button);
                    item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
                });
                } catch (error) {
                    toastr.error(typeof error.responseJSON?.errors === 'string' ? error.responseJSON.errors : (error.message || 'تعذر تحديث الشحن، حاول مرة أخرى'));
                    $('#automatic-shipping-selected').attr('data-value', '0').data('value', 0);
                } finally {
                    buttons.forEach(item => item.disabled = false);
                    document.querySelectorAll('.action-checkout-function').forEach(item => item.classList.remove('custom-disabled'));
                }
            });
        });
        const preferredShipping = sessionStorage.getItem('sigma_preferred_shipping');
        if (preferredShipping) {
            sessionStorage.removeItem('sigma_preferred_shipping');
            document.querySelector(`.select-automatic-shipping[data-shipping-option="${preferredShipping}"]`)?.click();
        }
        const invalidateShippingQuote = () => {
            shippingAddressDirty = true;
            $('#automatic-shipping-selected').attr('data-value', '0').data('value', 0);
            document.querySelectorAll('.select-automatic-shipping').forEach(button => {
                button.classList.remove('is-selected');
                button.setAttribute('aria-pressed', 'false');
            });
        };
        document.getElementById('address-form')?.addEventListener('input', invalidateShippingQuote);
        document.getElementById('address-form')?.addEventListener('change', invalidateShippingQuote);
        document.querySelectorAll('.select_shipping_address').forEach(item => item.addEventListener('click', invalidateShippingQuote));
    </script>

    <script src="{{ theme_asset(path: 'public/assets/front-end/js/bootstrap-select.min.js') }}"></script>
    <script src="{{ theme_asset(path: 'public/assets/front-end/js/shipping.js') }}?v=shipping-flow-20260929"></script>

    {{-- Customer maps are intentionally disabled; address resolution runs on the server. --}}
    @if(false)
        <script
            src="https://maps.googleapis.com/maps/api/js?key={{getWebConfig('map_api_key')}}&callback=mapsShopping&loading=async&libraries=places&v=3.56"
            defer>
        </script>
    @endif
@endpush
