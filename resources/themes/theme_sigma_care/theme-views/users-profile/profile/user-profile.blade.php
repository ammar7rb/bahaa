@php
    use App\Utils\Helpers;
@endphp
@extends('theme-views.layouts.app')
@section('title', translate('my_Profile').' | '.$web_config['company_name'].' '.translate('ecommerce'))
@section('content')
    <main class="main-content d-flex flex-column gap-3 py-3 mb-4">
        <div class="container">
            <div class="row g-3">
                @include('theme-views.partials._profile-aside')
                <div class="col-lg-9">
                    <div class="card mb-3">
                        <div class="card-body">
                            @include('customer-subscription.profile-overview')
                            <div class="mt-30 bg-light rounded p-3">
                                <div class="d-flex align-items-center flex-wrap justify-content-between gap-3">
                                    <h5>{{translate('Personal_Details')}}</h5>
                                    <a href="{{route('user-account')}}"
                                       class="btn btn-outline-secondary rounded-pill px-3 px-sm-4"><span
                                            class="d-none d-sm-inline-block text-capitalize">{{ translate('edit_profile') }}</span> <i
                                            class="bi bi-pencil-square"></i></a>
                                </div>

                                <dl class="care-profile-details mt-3 mb-0">
                                    <div class="care-profile-field care-profile-field-account">
                                        <dt><i class="bi bi-person-badge" aria-hidden="true"></i><span>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'رقم حسابك هو' : 'Your account number is' }}</span></dt>
                                        <dd><bdi dir="ltr">{{ $customer_detail->administrative_reference }}</bdi></dd>
                                    </div>
                                    <div class="care-profile-field">
                                        <dt><i class="bi bi-person" aria-hidden="true"></i><span>{{translate('first_name')}}</span></dt>
                                        <dd>{{$customer_detail['f_name']}}</dd>
                                    </div>
                                    <div class="care-profile-field">
                                        <dt><i class="bi bi-person" aria-hidden="true"></i><span>{{translate('last_name')}}</span></dt>
                                        <dd>{{$customer_detail['l_name']}}</dd>
                                    </div>
                                    <div class="care-profile-field">
                                        <dt><i class="bi bi-telephone" aria-hidden="true"></i><span>{{translate('phone')}}</span></dt>
                                        <dd><a href="tel:{{$customer_detail['phone']}}"><bdi dir="ltr">{{$customer_detail['phone']}}</bdi></a>                                                    @if($customer_detail['phone'] && getLoginConfig(key: 'phone_verification'))
                                                        @if($customer_detail['is_phone_verified'])
                                                            <span data-bs-toggle="tooltip" data-bs-placement="top" title="{{ translate('Your_phone_is_verified.') }}">
                                                            <img width="16"
                                                                 src="{{theme_asset('assets/img/icons/verified.svg')}}"
                                                                 class="dark-support" alt="">
                                                        </span>
                                                            @else
                                                                <span data-bs-toggle="tooltip" data-bs-placement="top" title="{{ translate('Phone_not_verified.') }} {{ translate('Please_verify_through_the_user_app.') }}">
                                                            <img width="16"
                                                                 src="{{theme_asset('assets/img/icons/verified-error.svg')}}"
                                                                 class="dark-support" alt="">
                                                        </span>
                                                        @endif
                                                    @endif</dd>
                                    </div>
                                    <div class="care-profile-field">
                                        <dt><i class="bi bi-envelope" aria-hidden="true"></i><span>{{translate('email')}}</span></dt>
                                        <dd><a href="mailto:{{$customer_detail['email']}}"><bdi dir="ltr">{{$customer_detail['email']}}</bdi></a>                                                    @if($customer_detail['email'] && getLoginConfig(key: 'email_verification'))
                                                        @if($customer_detail['is_email_verified'])
                                                            <span data-bs-toggle="tooltip" data-bs-placement="top" title="{{ translate('Your_email_is_verified.') }}">
                                                            <img width="16"
                                                                 src="{{theme_asset('assets/img/icons/verified.svg')}}"
                                                                 class="dark-support" alt="">
                                                        </span>
                                                            @else
                                                                <span data-bs-toggle="tooltip" data-bs-placement="top" title="{{ translate('Email_not_verified.') }} {{ translate('Please_verify_through_the_user_app.') }}">
                                                            <img width="16"
                                                                 src="{{theme_asset('assets/img/icons/verified-error.svg')}}"
                                                                 class="dark-support" alt="">
                                                        </span>
                                                        @endif
                                                    @endif</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <h5 class="text-capitalize">{{translate('my_addresses')}}</h5>
                                <a href="{{route('account-address-add')}}"
                                   class="btn btn-outline-secondary rounded-pill px-3 px-sm-4">
                                    <span class="d-none d-sm-inline-block text-capitalize">{{translate('add_address')}}</span>
                                        <i class="bi bi-geo-alt-fill"></i>
                                </a>
                            </div>
                            <div class="mt-3">
                                <div class="row gy-3 text-dark">
                                    @foreach($addresses as $address)
                                        <div class="col-md-6">
                                            <div class="card border-0">
                                                <div
                                                    class="card-header gap-2 align-items-center d-flex justify-content-between">
                                                    <h6>{{translate($address['address_type'])}}
                                                        ({{ $address['is_billing'] == 1 ? translate('billing_address'):translate('shipping_address')}})</h6>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <a href="{{route('address-edit',$address->id)}}" class="p-0 bg-transparent border-0">
                                                            <img src="{{theme_asset('assets/img/svg/location-edit.svg')}}"
                                                                alt="" class="svg">
                                                        </a>

                                                        <a href="javascript:"
                                                           data-action="{{route('address-delete',['id'=>$address->id])}}"
                                                           data-message="{{translate('want_to_delete_this_address').'?'}}"
                                                           class="p-0 bg-transparent border-0 delete-action">
                                                            <img src="{{theme_asset('assets/img/svg/delete.svg')}}"
                                                                 alt="" class="svg">
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <address>
                                                        <dl class="mb-0 flexible-grid width--5rem">
                                                            <dt>{{translate('name')}}</dt>
                                                            <dd>{{$address['contact_person_name']}}</dd>

                                                            <dt>{{translate('phone')}}</dt>
                                                            <dd><a href="javascript:" class="text-dark">{{$address['phone']}}</a>
                                                            </dd>
                                                            <dt>{{translate('address')}}</dt>
                                                            <dd>{{$address['address']}}</dd>
                                                        </dl>
                                                    </address>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if(count($addresses) == 0)
                                    <div class="d-flex justify-content-center align-items-center">
                                        <div class="d-flex flex-column justify-content-center align-items-center gap-2 py-5 w-100">
                                            <img width="60" class="mb-3" src="{{ theme_asset('assets/img/empty-state/empty-address.svg') }}" alt="">
                                            <h5 class="text-center text-muted">
                                                {{ translate('No_address_found') }}!
                                            </h5>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

@push("css_or_js")
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-profile-overview.css') }}?v=20261012.4">
@endpush
