@extends('theme-views.layouts.app')
@section('title', translate('add_Address').' | '.$web_config['company_name'].' '.translate('ecommerce'))
@section('content')
    <main class="main-content d-flex flex-column gap-3 py-3 mb-5">
        <div class="container">
            <div class="row g-3">
                @include('theme-views.partials._profile-aside')
                <div class="col-lg-9">
                    <div class="card h-100">
                        <div class="card-body p-lg-4">
                            <div class="mt-4">
                                <form action="{{route('address-store')}}" method="post">
                                    @csrf
                                    <div class="row gy-4">
                                        <div class="col-md-6">
                                            <div class="">
                                                <h6 class="fw-semibold text-muted mb-3 text-capitalize">{{translate('choose_label')}}</h6>
                                                <ul class="option-select-btn flex-wrap style--two gap-4 mb-4">
                                                    <li>
                                                        <label>
                                                            <input type="radio" name="address_type" value="home" hidden
                                                                   checked>
                                                            <span><i class="bi bi-house"></i></span>
                                                        </label>
                                                        {{translate('home')}}
                                                    </li>
                                                    <li>
                                                        <label>
                                                            <input type="radio" name="address_type" value="permanent"
                                                                   hidden="">
                                                            <span><i class="bi bi-paperclip"></i></span>
                                                        </label>
                                                        {{translate('permanent')}}
                                                    </li>
                                                    <li>
                                                        <label>
                                                            <input type="radio" name="address_type" value="office"
                                                                   hidden="">
                                                            <span><i class="bi bi-briefcase"></i></span>
                                                        </label>
                                                        {{translate('office')}}
                                                    </li>
                                                </ul>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label class="text-capitalize"
                                                       for="name">{{translate('contact_person')}}</label>
                                                <input type="text" id="name" class="form-control" name="name"
                                                       placeholder="{{translate('ex').':'.translate('jhon').translate('doe')}}"
                                                       required>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="phone">{{translate('phone')}}</label>
                                                <input type="tel" id="phone" class="form-control phone-input-with-country-picker" required
                                                       placeholder="{{translate('ex').':'.translate('01xxxxxxxxx')}}" name="phone">
                                            </div>

                                            <div class="form-group mb-3 ">
                                                <label for="country">{{translate('country')}}</label>
                                                <input type="hidden" name="country" value="Egypt">
                                                <input type="text" class="form-control" value="Egypt" readonly>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="state">{{translate('governorate')}}</label>
                                                <select class="form-select" id="state" name="state" required>
                                                    <option value="">{{ translate('select') }} {{ translate('governorate') }}</option>
                                                    @foreach(config('egypt_governorates') as $governorate)
                                                        <option value="{{ $governorate }}">{{ $governorate }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="city">المدينة / المركز</label>
                                                <input class="form-control" type="text" id="city" name="city" required placeholder="مثال: مدينة نصر">
                                            </div>

                                            <div class="form-group">
                                                <label for="zip-code">{{translate('zip_code')}}</label>
                                                @if($zip_restrict_status)
                                                    <select name="zip" class="form-control select2 select_picker"
                                                            data-live-search="true" required>
                                                        @foreach($zip_codes as $code)
                                                            <option
                                                                value="{{ $code->zipcode }}">{{ $code->zipcode }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input class="form-control" type="text" id="zip" name="zip"
                                                           required placeholder="{{ translate('Ex') }}: {{ '1216' }}">
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-6 mt-5 mt-md-0">
                                            <div class="d-flex justify-content-end mb-5">
                                                <a href="{{ route('user-profile') }}"
                                                   class="btn-link text-secondary d-flex align-items-baseline">
                                                    <i class="bi bi-chevron-left fs-12"></i> {{translate('go_back')}}
                                                </a>
                                            </div>

                                            <h6 class="fw-semibold text-muted mb-3 text-capitalize">{{translate('choose_address_type')}}</h6>
                                            <div class="d-flex flex-wrap style--two gap-4 mb-3">
                                                <div>
                                                    <label
                                                        class="d-flex align-items-center gap-2 cursor-pointer text-capitalize">
                                                        <input type="radio" name="is_billing" checked="" value="1">
                                                        {{translate('billing_address')}}
                                                    </label>
                                                </div>
                                                <div>
                                                    <label
                                                        class="d-flex align-items-center gap-2 cursor-pointer text-capitalize">
                                                        <input type="radio" name="is_billing" value="0">
                                                        {{translate('shipping_address')}}
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="district">الحي أو القسم</label>
                                                <input name="district" id="district" class="form-control mb-3" required placeholder="مثال: الحي العاشر">
                                                <label for="area">المنطقة</label>
                                                <input name="area" id="area" class="form-control mb-3" required placeholder="مثال: المنطقة الأولى">
                                                <label for="street">الشارع</label>
                                                <input name="street" id="street" class="form-control mb-3" required placeholder="مثال: شارع أحمد الزمر">
                                                <label for="building-number">رقم العقار</label>
                                                <input name="building_number" id="building-number" class="form-control mb-3" required placeholder="مثال: 12">
                                                <label for="landmark">أقرب علامة مميزة</label>
                                                <input name="landmark" id="landmark" class="form-control mb-3" placeholder="مثال: بجوار مسجد السلام">
                                                <label for="address">تفاصيل إضافية للعنوان</label>
                                                <textarea name="address" id="address" rows="5" class="form-control"
                                                          placeholder="اكتب تفاصيل تساعد المندوب في الوصول"
                                                          required></textarea>
                                                <p class="text-muted small mt-2 mb-0">لن نطلب منك اختيار موقع على خريطة؛ يجري التحقق من العنوان في الخلفية.</p>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div
                                                class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                                                <label class="custom-checkbox"></label>
                                                <div class="d-flex justify-content-end gap-3">
                                                    <button type="reset"
                                                            class="btn btn-secondary">{{translate('reset')}}</button>
                                                    <button type="submit"
                                                            class="btn btn-primary text-capitalize">{{translate('add_address')}}</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
@push('script')
    <script src="{{ theme_asset('assets/plugins/select2/js/select2.min.js') }}"></script>
@endpush
