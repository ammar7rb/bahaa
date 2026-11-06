@php($sellerRegistrationFallback = dynamicAsset(path: 'public/assets/front-end/img/media/sigma-medical-registration.webp'))
@php($isArabic = in_array(app()->getLocale(), ['ar', 'sa']))
<div class="col-lg-4">
    <div class="">
        <div class="d-flex justify-content-center">
            <div class="ext-center">
                <h3 class="mb-2 text-capitalize">{{ $isArabic ? 'تسجيل التاجر' : ($vendorRegistrationHeader?->title ?? 'Vendor Registration') }}</h3>
                <p>{{ $isArabic ? 'أنشئ متجرك الآن. لديك متجر بالفعل؟' : 'Create your own store. Already have a store?' }}
                    <a class="text-primary fw-bold" href="{{route('vendor.auth.login')}}">{{translate('login')}}</a>
                </p>
                <div class="my-4 text-center">
                    <img width="308"
                         src="{{ !empty($vendorRegistrationHeader?->image) ? getStorageImages(path:imagePathProcessing(imageData: $vendorRegistrationHeader?->image, path: 'vendor-registration-setting'),type: 'product') : $sellerRegistrationFallback }}"
                         onerror="this.onerror=null;this.src='{{ $sellerRegistrationFallback }}';"
                         alt="{{ $isArabic ? 'مستلزمات طبية' : 'Medical supplies' }}" class="dark-support">
                </div>
            </div>
        </div>
    </div>
</div>
