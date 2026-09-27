@extends('layouts.front-end.app')

@section('title', translate('my_Address'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/vendor/nouislider/distribute/nouislider.min.css')}}"/>
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/bootstrap-select.min.css') }}">
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/address.css') }}">
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/plugin/intl-tel-input/css/intlTelInput.css') }}">
@endpush

@section('content')
<div class="container py-4 rtl __account-address text-align-direction">

    <div class="row g-3">
        @include('web-views.partials._profile-aside')
        <section class="col-lg-9 col-md-8">

            <div class="card">
                <div class="card-body">
                    <h5 class="font-bold m-0 fs-16">{{translate('Update_Addresses')}}</h5>
                    <form action="{{route('address-update')}}" method="post">
                        @csrf
                        <div class="row pb-1">
                            <div class="col-md-6">
                                <input type="hidden" name="id" value="{{$shippingAddress->id}}">
                                <ul class="donate-now d-flex gap-2">
                                    <li class="address_type_li">
                                        <input type="radio" class="address_type" id="a25" name="address_type" value="permanent"
                                            {{ Str::lower($shippingAddress->address_type) == 'permanent' ? 'checked' : ''}} />
                                        <label for="a25" class="component">{{translate('permanent')}}</label>
                                    </li>
                                    <li class="address_type_li">
                                        <input type="radio" class="address_type" id="a50" name="address_type" value="home"
                                            {{ Str::lower($shippingAddress->address_type) == 'home' ? 'checked' : ''}} />
                                        <label for="a50" class="component">{{translate('home')}}</label>
                                    </li>
                                    <li class="address_type_li">
                                        <input type="radio" class="address_type" id="a75" name="address_type" value="office"
                                            {{ Str::lower($shippingAddress->address_type) == 'office' ? 'checked' : ''}}/>
                                        <label for="a75" class="component">{{translate('office')}}</label>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <input type="hidden" id="is_billing" value="{{$shippingAddress->is_billing}}">
                                <ul class="donate-now d-flex gap-2">
                                    <li class="address_type_bl">
                                        <input type="radio" class="bill_type" id="b25" name="is_billing" value="0"  {{ $shippingAddress->is_billing == '0' ? 'checked' : ''}} />
                                        <label for="b25" class="component">{{translate('shipping')}}</label>
                                    </li>
                                    <li class="address_type_bl">
                                        <input type="radio" class="bill_type" id="b50" name="is_billing" value="1" {{ $shippingAddress->is_billing == '1' ? 'checked' : ''}} />
                                        <label for="b50" class="component">{{translate('billing')}}</label>
                                    </li>

                                </ul>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="person_name">{{translate('contact_person_name')}}</label>
                                <input class="form-control" type="text" id="person_name"
                                    name="name"
                                    value="{{$shippingAddress->contact_person_name}}"
                                    required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="own_phone">{{ translate('phone') }}</label>
                                <input class="form-control" type="tel" name="phone" id="own_phone" value="{{ $shippingAddress->phone }}" required="required">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="state">{{translate('governorate')}}</label>
                                <select class="form-control" id="state" name="state" required>
                                    @if($shippingAddress->state && !in_array($shippingAddress->state, config('egypt_governorates'), true))
                                        <option value="{{ $shippingAddress->state }}" selected>{{ $shippingAddress->state }}</option>
                                    @endif
                                    <option value="">{{ translate('select') }} {{ translate('governorate') }}</option>
                                    @foreach(config('egypt_governorates') as $governorate)
                                        <option value="{{ $governorate }}" @selected(($shippingAddress->state ?? $shippingAddress->city) === $governorate)>{{ $governorate }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="zip_code">{{translate('zip_code')}}</label>
                                @if($zip_restrict_status)
                                    <select name="zip" class="form-control selectpicker" data-live-search="true" id="" required>
                                        @foreach($delivery_zipcodes as $zip)
                                            <option value="{{ $zip->zipcode }}" {{ $zip->zipcode == $shippingAddress->zip? 'selected' : ''}}>{{ $zip->zipcode }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input class="form-control" type="text" id="zip_code" name="zip" value="{{$shippingAddress->zip}}" required>
                                @endif
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="city">المدينة / المركز</label>
                                <input class="form-control" type="text" id="city" name="city" value="{{ $shippingAddress->city }}" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="district">الحي أو القسم</label>
                                <input class="form-control" type="text" id="district" name="district" value="{{ $shippingAddress->district }}" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="area">المنطقة</label>
                                <input class="form-control" type="text" id="area" name="area" value="{{ $shippingAddress->area }}" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="street">الشارع</label>
                                <input class="form-control" type="text" id="street" name="street" value="{{ $shippingAddress->street }}" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="building-number">رقم العقار</label>
                                <input class="form-control" type="text" id="building-number" name="building_number" value="{{ $shippingAddress->building_number }}" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="landmark">أقرب علامة مميزة</label>
                                <input class="form-control" type="text" id="landmark" name="landmark" value="{{ $shippingAddress->landmark }}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="city">{{translate('country')}}</label>
                                <input type="hidden" name="country" value="Egypt">
                                <input type="text" class="form-control" value="Egypt" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class=" col-md-12">
                                <div class="form-group mb-1">
                                    <label for="own_address">{{translate('address')}}</label>
                                    <textarea class="form-control" id="address"
                                              type="text"  name="address" required>{{$shippingAddress->address}}</textarea>
                                    <span class="fs-14 text-danger font-semi-bold opacity-0 map-address-alert">
                                        {{ translate('note') }}: {{ translate('you_need_to_select_address_from_your_selected_country') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        @php($shipping_latitude=$shippingAddress->latitude)
                        @php($shipping_longitude=$shippingAddress->longitude)
                        <input type="hidden" id="latitude"
                            name="latitude" class="form-control d-inline"
                            placeholder="{{ translate('ex')}} : -94.22213" value="{{$shipping_latitude??0}}" required readonly>
                        <input type="hidden"
                            name="longitude" class="form-control"
                            placeholder="{{ translate('ex')}} : 103.344322" id="longitude" value="{{$shipping_longitude??0}}" required readonly>
                        <div class="modal-footer">
                            <a href="{{ route('account-address') }}" class="closeB btn btn-secondary fs-14 font-semi-bold py-2 px-4">{{translate('close')}}</a>
                            <button type="submit" class="btn btn--primary fs-14 font-semi-bold py-2 px-4">{{translate('update')}}  </button>
                        </div>
                    </form>
                </div>
            </div>

        </section>
    </div>
</div>
<span id="system-country-restrict-status" data-value="{{ $country_restrict_status }}"></span>
@endsection

@push('script')
<script>
    'use strict'
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
</script>
<script src="{{ theme_asset(path: 'public/assets/front-end/js/bootstrap-select.min.js') }}"></script>

{{-- Customer maps are intentionally disabled; address resolution runs on the server. --}}
@if(false)
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{getWebConfig('map_api_key')}}&callback=callBackFunction&loading=async&libraries=places&v=3.56" defer>
    </script>
    <script>
        async function initAutocomplete() {
            var myLatLng = { lat: {{$shipping_latitude??'30.0444'}}, lng: {{$shipping_longitude??'31.2357'}} };
            const { Map } = await google.maps.importLibrary("maps");
            const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");
            const map = new google.maps.Map(document.getElementById("location_map_canvas"), {
                center: { lat: {{$shipping_latitude??'-33.8688'}}, lng: {{$shipping_longitude??'151.2195'}} },
                zoom: 13,
                mapId: 'roadmap',
                restriction: {latLngBounds: {north: 31.7, south: 22.0, east: 36.9, west: 24.7}, strictBounds: true}
            });

            var marker = new AdvancedMarkerElement({
                map,
                position: myLatLng,
            });

            marker.setMap( map );
            var geocoder = geocoder = new google.maps.Geocoder();
            google.maps.event.addListener(map, 'click', function (mapsMouseEvent) {
                var coordinates = JSON.stringify(mapsMouseEvent.latLng.toJSON(), null, 2);
                var coordinates = JSON.parse(coordinates);
                var latlng = new google.maps.LatLng( coordinates['lat'], coordinates['lng'] ) ;
                marker.position={lat:coordinates['lat'], lng:coordinates['lng']};
                map.panTo( latlng );

                document.getElementById('latitude').value = coordinates['lat'];
                document.getElementById('longitude').value = coordinates['lng'];

                geocoder.geocode({ 'latLng': latlng }, function (results, status) {
                    if (status == google.maps.GeocoderStatus.OK) {
                        if (results[1]) {
                            document.getElementById('address').value = results[1].formatted_address;

                            let systemCountryRestrictStatus = $('#system-country-restrict-status').data('value');
                            if (systemCountryRestrictStatus) {
                                const countryObject = findCountryObject(results[1].address_components);
                                deliveryRestrictedCountriesCheck(countryObject.long_name, '.location-map-address-canvas-area', '#address')
                            }
                        }
                    }
                });
            });

            const input = document.getElementById("pac-input");
            const searchBox = new google.maps.places.SearchBox(input);
            map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
            map.addListener("bounds_changed", () => {
                searchBox.setBounds(map.getBounds());
            });
            let markers = [];

            searchBox.addListener("places_changed", () => {
                const places = searchBox.getPlaces();

                if (places.length == 0) {
                    return;
                }

                markers.forEach((marker) => {
                    marker.setMap(null);
                });
                markers = [];
                const bounds = new google.maps.LatLngBounds();
                places.forEach((place) => {
                    if (!place.geometry || !place.geometry.location) {
                        console.log("Returned place contains no geometry");
                        return;
                    }
                    var mrkr = new AdvancedMarkerElement({
                        map,
                        title: place.name,
                        position: place.geometry.location,
                    });

                    google.maps.event.addListener(mrkr, "click", function (event) {
                        document.getElementById('latitude').value = this.position.lat();
                        document.getElementById('longitude').value = this.position.lng();
                    });

                    markers.push(mrkr);

                    if (place.geometry.viewport) {
                        bounds.union(place.geometry.viewport);
                    } else {
                        bounds.extend(place.geometry.location);
                    }
                });
                map.fitBounds(bounds);
            });
        }
        function callBackFunction(){
            initAutocomplete();
        }

        $(document).on("keydown", "input", function(e) {
            if (e.which==13) e.preventDefault();
        });
    </script>
@endif
@endpush
