@extends('theme-views.layouts.app')

@section('title', translate('shopping_Details').' | '.$web_config['company_name'].' '.translate('ecommerce'))

@section('content')
    <main class="main-content d-flex flex-column gap-3 py-3 mb-5">
        <div class="container">
            <h4 class="text-center mb-3 text-capitalize">{{ translate('shipping_details') }}</h4>
            <div class="row">
                <div class="col-lg-8 mb-3 mb-lg-0">
                    <div class="card h-100">
                        <div class="card-body  px-sm-4">
                            <div class="d-flex justify-content-center mb-30">
                                <ul class="cart-step-list">
                                    <li class="done cursor-pointer get-view-by-onclick"
                                        data-link="{{route('shop-cart')}}"><span><i
                                                class="bi bi-check2"></i></span> {{ translate('cart') }}</li>
                                    <li class="current cursor-pointer get-view-by-onclick text-capitalize"
                                        data-link="{{ route('checkout-details') }}"><span><i
                                                class="bi bi-check2"></i></span> {{ translate('shipping_details') }}
                                    </li>
                                    <li><span><i class="bi bi-check2"></i></span> {{ translate('payment') }}</li>
                                </ul>
                            </div>
                            <input type="hidden" id="physical-product" name="physical_product"
                                   value="{{ $physical_product_view ? 'yes':'no'}}">
                            <input type="hidden" id="billing-input-enable" name="billing_input_enable"
                                   value="{{ $billing_input_by_customer }}">
                            @if($physical_product_view)
                                <form method="post" id="address-form">
                                    <h5 class="mb-3 text-capitalize">{{ translate('delivery_information_details') }}</h5>

                                    <div class="card">
                                        <div class="card-body" id="collapseThree">
                                            <div class="bg-light p-3 rounded d-flex flex-wrap justify-content-between gap-3 mb-3">
                                                <h6 class="text-capitalize">{{ translate('Shipping_Address') }}</h6>
                                                @if(auth('customer')->check())
                                                    <a href="javascript:" type="button" data-bs-toggle="modal"
                                                       data-bs-target="#shippingSavedAddressModal"
                                                       class="btn-link text-primary text-capitalize">{{ translate('Saved_Address') }}</a>
                                                @endif
                                            </div>

                                            @if(auth('customer')->check())
                                                <div class="modal fade" id="shippingSavedAddressModal" data-bs-backdrop="static"
                                                     data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered justify-content-center">
                                                        <div class="modal-content border-0">
                                                            <div class="modal-header">
                                                                <h5 class="text-capitalize"
                                                                    id="contact_sellerModalLabel">{{translate('saved_addresses')}}</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                        aria-label="Close"></button>
                                                            </div>

                                                            <div class="modal-body custom-scrollbar">
                                                                <div class="product-quickview">
                                                                    <div
                                                                        class="shipping-saved-addresses {{ $shipping_addresses->count()<1 ? 'd--none':'' }}">
                                                                        <div class="row gy-3 text-dark py-4">
                                                                            @foreach($shipping_addresses as $key=>$address)
                                                                                <div class="col-md-12">
                                                                                    <div class="card border-0">
                                                                                        <div
                                                                                            class="card-header bg-transparent gap-2 align-items-center d-flex flex-wrap justify-content-between">
                                                                                            <label
                                                                                                class="d-flex align-items-center gap-3 cursor-pointer mb-0">
                                                                                                <input type="radio"
                                                                                                       name="shipping_method_id"
                                                                                                       value="{{$address['id']}}" {{$key==0?'checked':''}}>
                                                                                                <h6>{{$address['address_type']}}</h6>
                                                                                            </label>
                                                                                            <div
                                                                                                class="d-flex align-items-center gap-3">
                                                                                                <button type="button"
                                                                                                        onclick="location.href='{{ route('address-edit', ['id' => $address->id]) }}'"
                                                                                                        class="p-0 bg-transparent border-0 text-primary">
                                                                                                    <img
                                                                                                        src="{{ theme_asset('assets/img/svg/location-edit-dynamic.svg') }}"
                                                                                                        alt="" class="svg">
                                                                                                </button>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="card-body">
                                                                                            <address>
                                                                                                <dl class="mb-0 flexible-grid sm-down-1 width--5rem">
                                                                                                    <dt>{{ translate('name') }}</dt>
                                                                                                    <dd class="shipping-contact-person">{{$address['contact_person_name']}}</dd>

                                                                                                    <dt>{{ translate('phone') }}</dt>
                                                                                                    <dd class="">
                                                                                                        <a href="tel:{{$address['phone']}}"
                                                                                                           class="text-dark shipping-contact-phone">{{$address['phone']}}</a>
                                                                                                    </dd>

                                                                                                    <dt>{{ translate('address') }}</dt>
                                                                                                    <dd>{{$address['address']}}
                                                                                                        , {{$address['city']}}
                                                                                                        , {{$address['zip']}}</dd>
                                                                                                    <span
                                                                                                        class="shipping-contact-address d-none">{{ $address['address'] }}</span>
                                                                                                    <span
                                                                                                        class="shipping-contact-city d-none">{{ $address['city'] }}</span>
                                                                                                    <span
                                                                                                        class="shipping-contact-state d-none">{{ $address['state'] ?? $address['city'] }}</span>
                                                                                                    <span class="shipping-contact-district d-none">{{ $address['district'] }}</span>
                                                                                                    <span class="shipping-contact-area d-none">{{ $address['area'] }}</span>
                                                                                                    <span class="shipping-contact-street d-none">{{ $address['street'] }}</span>
                                                                                                    <span class="shipping-contact-building-number d-none">{{ $address['building_number'] }}</span>
                                                                                                    <span class="shipping-contact-landmark d-none">{{ $address['landmark'] }}</span>
                                                                                                    <span
                                                                                                        class="shipping-contact-zip d-none">{{ $address['zip'] }}</span>
                                                                                                    <span class="shipping-contact-country d-none">{{ $address['country'] }}</span>
                                                                                                    <span class="shipping-contact-address-type d-none">{{ $address['address_type'] }}</span>
                                                                                                    <span class="shipping-contact-latitude-type d-none">{{ $address['latitude'] }}</span>
                                                                                                    <span class="shipping-contact-longitude-type d-none">{{ $address['longitude'] }}</span>
                                                                                                </dl>
                                                                                            </address>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                    <div
                                                                        class="text-center {{ $shipping_addresses->count()>0 ? 'd--none':'' }}">
                                                                        <img src="{{theme_asset('assets/img/svg/address.svg')}}"
                                                                             alt="address" class="w-25">
                                                                        <h5 class="my-3 pt-1 text-muted">
                                                                            {{translate('no_address_is_saved')}}!
                                                                        </h5>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary"
                                                                        data-bs-dismiss="modal">{{ translate('close') }}</button>
                                                                <button type="button" class="btn btn-primary"
                                                                        data-bs-dismiss="modal">{{ translate('save') }}</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="row">
                                                        <div class="col-sm-{{ auth('customer')->check() ? 6 : 12 }}">
                                                            <div class="form-group mb-4">
                                                                <label for="name"
                                                                       class="text-capitalize">{{ translate('contact_person_name')}} <span class="text-danger">*</span></label>
                                                                <input type="text" name="contact_person_name" id="name"
                                                                       class="form-control"
                                                                       placeholder="{{ translate('ex') }}: {{translate('Jhon_Doe')}}" {{$shipping_addresses->count()==0?'required':''}}>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="phone">{{ translate('phone') }} <span class="text-danger">*</span></label>
                                                                <input type="tel" id="phoneNumber" name="phone"
                                                                       class="form-control"
                                                                       placeholder="{{ translate('ex') }}: {{translate('+8801000000000')}}" {{$shipping_addresses->count()==0?'required':''}}>
                                                            </div>
                                                        </div>
                                                        @if(!auth('customer')->check())
                                                            <div class="col-sm-6">
                                                                <div class="form-group mb-4">
                                                                    <label for="email">{{ translate('email') }} <span class="text-danger">*</span></label>
                                                                    <input type="email" name="email" id="email"
                                                                           class="form-control"
                                                                           placeholder="{{ translate('ex') }}: {{translate('email@domain.com')}}"
                                                                           required>
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="address-type"
                                                                       class="text-capitalize">{{ translate('address_type')}}</label>
                                                                <select name="address_type" id="address-type"
                                                                        class="form-select">
                                                                    <option value="permanent">{{ translate('permanent')}}</option>
                                                                    <option value="home">{{ translate('home')}}</option>
                                                                    <option value="office">{{ translate('office')}}</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="country">{{ translate('country') }} <span class="text-danger">*</span></label>
                                                                <input type="hidden" name="country" value="Egypt">
                                                                <input type="text" class="form-control" value="Egypt" readonly>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="state">{{ translate('governorate') }} <span class="text-danger">*</span></label>
                                                                <select name="state" id="state" class="form-control" {{$shipping_addresses->count()==0?'required':''}}>
                                                                    <option value="" selected disabled>{{ translate('select') }} {{ translate('governorate') }}</option>
                                                                    @foreach(config('egypt.governorates') as $governorate)
                                                                        <option value="{{ $governorate }}">{{ $governorate }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                         <div class="col-sm-6">
                                                             <div class="form-group mb-4">
                                                                 <label for="city">المدينة / المركز <span class="text-danger">*</span></label>
                                                                 <input type="text" name="city" id="city"
                                                                       placeholder="{{ translate('ex') }}: {{translate('dhaka')}}"
                                                                       class="form-control" {{$shipping_addresses->count()==0?'required':''}}>
                                                             </div>
                                                         </div>
                                                         <div class="col-sm-6">
                                                             <div class="form-group mb-4">
                                                                 <label for="district">الحي أو القسم <span class="text-danger">*</span></label>
                                                                 <input type="text" name="district" id="district" class="form-control" required placeholder="مثال: مدينة نصر">
                                                             </div>
                                                         </div>
                                                         <div class="col-sm-6">
                                                             <div class="form-group mb-4">
                                                                 <label for="area">المنطقة <span class="text-danger">*</span></label>
                                                                 <input type="text" name="area" id="area" class="form-control" required placeholder="مثال: الحي العاشر">
                                                             </div>
                                                         </div>
                                                         <div class="col-sm-6">
                                                             <div class="form-group mb-4">
                                                                 <label for="street">الشارع <span class="text-danger">*</span></label>
                                                                 <input type="text" name="street" id="street" class="form-control" required placeholder="مثال: شارع أحمد الزمر">
                                                             </div>
                                                         </div>
                                                         <div class="col-sm-6">
                                                             <div class="form-group mb-4">
                                                                 <label for="building-number">رقم العقار <span class="text-danger">*</span></label>
                                                                 <input type="text" name="building_number" id="building-number" class="form-control" required placeholder="مثال: 12">
                                                             </div>
                                                         </div>
                                                         <div class="col-sm-6">
                                                             <div class="form-group mb-4">
                                                                 <label for="landmark">أقرب علامة مميزة</label>
                                                                 <input type="text" name="landmark" id="landmark" class="form-control" placeholder="مثال: بجوار مسجد السلام">
                                                             </div>
                                                         </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="city"
                                                                       class="text-capitalize">{{ translate('zip_code') }} <span class="text-danger">*</span></label>
                                                                @if($zip_restrict_status == 1)
                                                                    <select name="zip" id="zip"
                                                                            class="form-control select2 select_picker"
                                                                            data-live-search="true" required>
                                                                        @forelse($zip_codes as $code)
                                                                            <option
                                                                                value="{{ $code->zipcode }}">{{ $code->zipcode }}</option>
                                                                        @empty
                                                                            <option
                                                                                value="">{{ translate('no_zip_to_deliver') }}</option>
                                                                        @endforelse
                                                                    </select>
                                                                @else
                                                                    <input type="text" class="form-control" id="zip"
                                                                           name="zip"
                                                                           placeholder="{{ translate('ex') }}: {{translate('1216')}}" {{$shipping_addresses->count()==0?'required':''}}>
                                                                @endif
                                                            </div>
                                                        </div>
                                                         <div class="col-sm-12">
                                                             <div class="form-group mb-4">
                                                                 <label for="address" class="mb-2">تفاصيل إضافية للعنوان <span class="text-danger">*</span></label>
                                                                 <p class="text-muted fs-12 mb-2">لن تظهر لك خريطة. يتحقق النظام من العنوان في الخلفية ويحسب مسافة الطريق من مركز الشحن.</p>
                                                                 <textarea name="address" id="address" class="form-control" rows="3" placeholder="اكتب أي تفاصيل تساعد المندوب في الوصول" required></textarea>
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

                                                        <div class="col-sm-12">
                                                            <label class="custom-checkbox align-items-center fw-bold"
                                                                   id="save-address-label">
                                                                <input type="hidden" name="shipping_method_id"
                                                                       id="shipping-method-id" value="0">
                                                                @if(auth('customer')->check())
                                                                    <input type="checkbox" name="save_address"
                                                                           id="saveAddress">
                                                                    {{ translate('save_this_address') }}
                                                                @endif
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </form>

                                @if(!Auth::guard('customer')->check() && $web_config['guest_checkout_status'])
                                    <div class="card __card mt-3">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center flex-wrap justify-content-between gap-3">
                                                <div class="min-h-45 d-flex gap-2 align-items-center cursor-pointer user-select-none">
                                                    <input type="checkbox" id="is_check_create_account" name="is_check_create_account">
                                                    <label class="fw-bold fs-13 mb-0" for="is_check_create_account">
                                                        {{translate('Create_an_account_with_the_above_info')}}
                                                    </label>
                                                </div>

                                                <div class="is_check_create_account_password_group d--none">
                                                    <div class="d-flex gap-3 flex-wrap flex-sm-nowrap">
                                                        <div class="">
                                                            <div class="input-inner-end-ele">
                                                                <input name="customer_password" type="password" id="customer_password" class="form-control" placeholder="{{ translate('new_Password') }}" required="">
                                                                <i class="bi bi-eye-slash-fill togglePassword"></i>
                                                            </div>
                                                        </div>
                                                        <div class="">
                                                            <div class="input-inner-end-ele">
                                                                <input name="customer_confirm_password" type="password" id="customer_confirm_password" class="form-control" placeholder="{{ translate('confirm_Password') }}" required="">
                                                                <i class="bi bi-eye-slash-fill togglePassword"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                            @endif

                            @if($billing_input_by_customer)
                                <div class="card card-body mt-3 {{ $billing_input_by_customer ? '':'d-none' }}">
                                    <div class="bg-light rounded p-3">
                                        <div class="d-flex flex-wrap justify-content-between gap-3">
                                            <h6 class="text-capitalize">{{ translate('billing_address') }}</h6>
                                            <div class="d-flex gap-3 align-items-center flex-wrap">
                                                @if(auth('customer')->check())
                                                    <a href="javascript:" type="button" data-bs-toggle="modal"
                                                        data-bs-target="#billingSavedAddressModal"
                                                        class="btn-link text-primary text-capitalize">{{ translate('Saved_Address') }}</a>
                                                @endif
                                                @if($physical_product_view)
                                                    <label class="custom-checkbox" class="text-capitalize">
                                                        {{ translate('same_as_delivery_address') }}
                                                        <input type="checkbox" id="same-as-shipping-address"
                                                               name="same_as_shipping_address"
                                                               class="billing-address-checkbox" checked>
                                                    </label>
                                                @endif
                                            </div>

                                        </div>
                                    </div>

                                    @if(!$physical_product_view)
                                        <div class="mt-3 alert--info">
                                            <div class="d-flex align-items-center gap-2">
                                                <img class="mb-1" src="{{ theme_asset('assets/img/icons/info-light.svg') }}" alt="Info">
                                                <span>{{ translate('When you input all the required information and save or update it, this billing address will be stored for future purchases.') }}</span>
                                            </div>
                                        </div>
                                    @endif

                                    @if(auth('customer')->check())
                                        <div class="modal fade" id="billingSavedAddressModal"
                                             data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                                             aria-hidden="true">
                                            <div
                                                class="modal-dialog modal-lg modal-dialog-centered justify-content-center">
                                                <div class="modal-content border-0 max-width-500">
                                                    <div class="modal-header">
                                                        <h5 class="text-capitalize"
                                                            id="contact_sellerModalLabel">{{translate('saved_addresses')}}</h5>
                                                        <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal"
                                                                aria-label="Close"></button>
                                                    </div>

                                                    <div class="modal-body custom-scrollbar">
                                                        <div class="product-quickview">
                                                            <div
                                                                class="billing-saved-addresses {{ $billing_addresses->count()<1 ? 'd--none':'' }}">
                                                                <div class="row gy-3 text-dark py-4">
                                                                    @foreach($billing_addresses as $key=>$address)
                                                                        <div class="col-md-12">
                                                                            <div class="card border-0 ">
                                                                                <div
                                                                                    class="card-header bg-transparent gap-2 align-items-center d-flex flex-wrap justify-content-between">
                                                                                    <label
                                                                                        class="d-flex align-items-center gap-3 cursor-pointer mb-0">
                                                                                        <input type="radio"
                                                                                               value="{{$address['id']}}"
                                                                                               name="billing_method_id" {{$key==0?'checked':''}}>
                                                                                        <h6>{{$address['address_type']}}</h6>
                                                                                    </label>
                                                                                    <div
                                                                                        class="d-flex align-items-center gap-3">
                                                                                        <button type="button"
                                                                                                onclick="location.href='{{ route('address-edit', ['id' => $address->id]) }}'"
                                                                                                class="p-0 bg-transparent border-0 text-primary">
                                                                                            <img
                                                                                                src="{{ theme_asset('assets/img/svg/location-edit-dynamic.svg') }}"
                                                                                                alt=""
                                                                                                class="svg">
                                                                                        </button>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="card-body pb-0">
                                                                                    <address>
                                                                                        <dl class="mb-0 flexible-grid sm-down-1 width--5rem">
                                                                                            <dt>{{ translate('name') }}</dt>
                                                                                            <dd class="billing-contact-name">{{$address['contact_person_name']}}</dd>

                                                                                            <dt>{{ translate('phone') }}</dt>
                                                                                            <dd class="">
                                                                                                <a href="tel:{{$address['phone']}}"
                                                                                                   class="text-dark billing-contact-phone">{{$address['phone']}}</a>
                                                                                            </dd>

                                                                                            <dt>{{ translate('address') }}</dt>
                                                                                            <dd>{{$address['address']}}
                                                                                                , {{$address['city']}}
                                                                                                , {{$address['zip']}}</dd>
                                                                                            <span
                                                                                                class="billing-contact-address d-none">{{ $address['address'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-city d-none">{{ $address['city'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-state d-none">{{ $address['state'] ?? $address['city'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-zip d-none">{{ $address['zip'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-country d-none">{{ $address['country'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-address-type d-none">{{ $address['address_type'] }}</span>
                                                                                            <span class="billing-contact-latitude-type d-none">{{ $address['latitude'] }}</span>
                                                                                            <span class="billing-contact-longitude-type d-none">{{ $address['longitude'] }}</span>
                                                                                        </dl>
                                                                                    </address>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="text-center {{ $billing_addresses->count()>0 ? 'd--none':'' }}">
                                                                <img
                                                                    src="{{theme_asset('assets/img/svg/address.svg')}}"
                                                                    alt="address" class="w-25">
                                                                <h5 class="my-3 pt-1 text-muted">
                                                                    {{translate('no_address_is_saved')}}!
                                                                </h5>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">{{ translate('close') }}</button>
                                                        <button type="button" class="btn btn-primary"
                                                                data-bs-dismiss="modal">{{ translate('save') }}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    <form method="post" id="billing-address-form">
                                        <div class="toggle-billing-address mt-3 d--none" id="hide-billing-address">
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="row">
                                                        <div
                                                            class="col-sm-{{ auth('customer')->check() ? 6 : 12 }}">
                                                            <div class="form-group mb-4">
                                                                <label for="billing-contact-person-name"
                                                                       class="text-capitalize">{{ translate('contact_person_name')}} <span class="text-danger">*</span></label>
                                                                <input type="text"
                                                                       name="billing_contact_person_name"
                                                                       id="billing-contact-person-name"
                                                                       class="form-control"
                                                                       placeholder="{{ translate('ex') }}: {{translate('Jhon_Doe')}}" {{$billing_addresses->count()==0?'required':''}}>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="billing-phone">
                                                                    {{ translate('phone') }} <span class="text-danger">*</span>
                                                                </label>
                                                                <input type="tel" name="billing_phone"
                                                                       id="billing-phone" class="form-control"
                                                                       placeholder="{{ translate('ex') }}: {{translate('+88 01000000000')}}" {{$billing_addresses->count()==0?'required':''}}>
                                                            </div>
                                                        </div>
                                                        @if(!auth('customer')->check())
                                                            <div class="col-sm-6">
                                                                <div class="form-group mb-4">
                                                                    <label for="billing_contact_email">
                                                                        {{ translate('email') }} <span class="text-danger">*</span>
                                                                    </label>
                                                                    <input type="email"
                                                                           name="billing_contact_email"
                                                                           id="billing-contact-email"
                                                                           class="form-control"
                                                                           placeholder="{{ translate('ex') }}: {{translate('email@domain.com')}}"
                                                                           required>
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="billing_address_type"
                                                                       class="text-capitalize">{{ translate('address_type')}}</label>
                                                                <select name="billing_address_type"
                                                                        id="billing-address-type"
                                                                        class="form-select">
                                                                    <option
                                                                        value="permanent">{{ translate('permanent')}}</option>
                                                                    <option
                                                                        value="home">{{ translate('home')}}</option>
                                                                    <option
                                                                        value="office">{{ translate('office')}}</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="billing-country">
                                                                    {{ translate('country') }} <span class="text-danger">*</span>
                                                                </label>
                                                                <input type="hidden" name="billing_country" value="Egypt">
                                                                <input type="text" class="form-control" value="Egypt" readonly>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="billing-state">{{ translate('governorate') }} <span class="text-danger">*</span></label>
                                                                <select name="billing_state" id="billing-state" class="form-control" {{$billing_addresses->count()==0?'required':''}}>
                                                                    <option value="" selected disabled>{{ translate('select') }} {{ translate('governorate') }}</option>
                                                                    @foreach(config('egypt.governorates') as $governorate)
                                                                        <option value="{{ $governorate }}">{{ $governorate }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label
                                                                    for="billing-city">{{ translate('city') }} <span class="text-danger">*</span></label>
                                                                <input type="text" name="billing_city"
                                                                       id="billing-city"
                                                                       placeholder="{{ translate('ex') }}: {{translate('Dhaka')}}"
                                                                       class="form-control" {{$billing_addresses->count()==0?'required':''}}>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label
                                                                    for="billing-zip">{{ translate('Zip_Code') }} <span class="text-danger">*</span></label>
                                                                @if($zip_restrict_status == 1)
                                                                    <select name="billing_zip" id="billing-zip"
                                                                            class="form-control select2 select_picker"
                                                                            data-live-search="true" required>
                                                                        @forelse($zip_codes as $code)
                                                                            <option
                                                                                value="{{ $code->zipcode }}">{{ $code->zipcode }}</option>
                                                                        @empty
                                                                            <option
                                                                                value="">{{ translate('no_zip_to_deliver') }}</option>
                                                                        @endforelse
                                                                    </select>
                                                                @else
                                                                    <input type="text" class="form-control"
                                                                        id="billing-zip" name="billing_zip"
                                                                        placeholder="{{ translate('ex') }}: {{translate('1216')}}" {{$billing_addresses->count()==0?'required':''}}>
                                                                @endif
                                                            </div>
                                                        </div>
                                                         <div class="col-sm-12">
                                                             <div class="form-group mb-4">
                                                                 <label class="mb-2" for="billing_address">{{ translate('address') }} <span class="text-danger">*</span></label>
                                                                 <input type="text" name="billing_address"
                                                                    id="billing_address"
                                                                    class="form-control"
                                                                    placeholder="{{ translate('your_address') }}" {{$shipping_addresses->count()==0?'required':''}}>
                                                            </div>
                                                        </div>

                                                        <input type="hidden" name="billing_method_id"
                                                               id="billing-method-id" value="0">
                                                        @if(auth('customer')->check())
                                                            <div class="col-sm-12">
                                                                <label
                                                                    class="custom-checkbox save-billing-address fw-bold"
                                                                    id="save-billing-address-label">
                                                                    <input type="checkbox"
                                                                           name="save_address_billing"
                                                                           id="save_address_billing">
                                                                    {{ translate('save_this_address') }}
                                                                </label>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </form>
                                </div>

                                @if(!Auth::guard('customer')->check() && $web_config['guest_checkout_status'] && !$physical_product_view)
                                    <div class="card __card mt-3">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center flex-wrap justify-content-between gap-3">
                                                <div class="d-flex gap-2 align-items-center cursor-pointer user-select-none">
                                                    <input type="checkbox" id="is_check_create_account" name="is_check_create_account">
                                                    <label class="fw-bold fs-13 text-capitalize mb-0" for="is_check_create_account">
                                                        {{ translate('Create_an_account_with_the_above_info') }}
                                                    </label>
                                                </div>

                                                <div class="is_check_create_account_password_group d--none">
                                                    <div class="d-flex gap-3 flex-wrap flex-sm-nowrap">
                                                        <div class="">
                                                            <div class="input-inner-end-ele">
                                                                <input name="customer_password" type="password" id="customer_password" class="form-control" placeholder="{{ translate('new_Password') }}" required="">
                                                                <i class="bi bi-eye-slash-fill togglePassword"></i>
                                                            </div>
                                                        </div>
                                                        <div class="">
                                                            <div class="input-inner-end-ele">
                                                                <input name="customer_confirm_password" type="password" id="customer_confirm_password" class="form-control" placeholder="{{ translate('confirm_Password') }}" required="">
                                                                <i class="bi bi-eye-slash-fill togglePassword"></i>
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
                    </div>
                </div>
                @if($physical_product_view && !empty($shipping_quote_options))
                    <div class="card mt-3" id="automatic-shipping-options">
                        <div class="card-body">
                            <h5 class="mb-1">اختر طريقة الشحن</h5>
                            <p class="text-muted mb-3">اختر الشحن المناسب لعنوانك. تُضاف تكلفة الشحن تلقائيًا إلى الفاتورة.</p>
                            <div class="row g-3">
                                @foreach(['normal', 'sigma'] as $shippingOptionKey)
                                    @php($shippingOption = $shipping_quote_options[$shippingOptionKey] ?? null)
                                    @if($shippingOption)
                                        <div class="col-md-6">
                                            <button type="button" class="btn btn-outline-primary w-100 text-start p-3 select-automatic-shipping"
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
                            @if($automatic_shipping_selected)
                                <a href="{{ route('checkout-payment') }}" class="btn btn-primary w-100 mt-3">المتابعة إلى الدفع</a>
                            @endif
                        </div>
                    </div>
                @endif
                @include('theme-views.partials._order-summery')
            </div>
        </div>
    </main>

    <span id="shipping-address-location"
          data-latitude="{{ $default_location ? $default_location['lat'] : '' }}"
          data-longitude="{{ $default_location ? $default_location['lng'] : '' }}">
</span>
    <span id="route-set-automatic-shipping" data-url="{{ route('customer.set-shipping-method') }}"></span>
    <span id="route-checkout-payment" data-url="{{ route('checkout-payment') }}"></span>
    <span id="automatic-shipping-selected" data-value="{{ $automatic_shipping_selected ? 1 : 0 }}"></span>
@endsection
@push('script')
    <script src="{{ theme_asset('assets/js/shipping-page.js') }}?v=shipping-flow-20260827"></script>
    <script>
        document.querySelectorAll('.select-automatic-shipping').forEach((button) => {
            button.addEventListener('click', async () => {
                button.disabled = true;
                const url = document.getElementById('route-set-automatic-shipping').dataset.url;
                const params = new URLSearchParams({id: button.dataset.methodId, address_id: button.dataset.addressId, cart_group_id: 'all_cart_group'});
                const response = await fetch(`${url}?${params.toString()}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
                const data = await response.json();
                if (data.status) window.location.reload();
                else button.disabled = false;
            });
        });
        const preferredShipping = sessionStorage.getItem('sigma_preferred_shipping');
        if (preferredShipping) {
            sessionStorage.removeItem('sigma_preferred_shipping');
            document.querySelector(`.select-automatic-shipping[data-shipping-option="${preferredShipping}"]`)?.click();
        }
    </script>

@endpush
