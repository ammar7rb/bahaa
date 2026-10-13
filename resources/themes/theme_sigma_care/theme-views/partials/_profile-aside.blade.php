@php
    use function App\Utils\customer_info;
    $customer_info = auth('customer')->user();
@endphp
<div class="col-lg-3">
    <div class="card profile-sidebar-sticky">
        <div class="card-body position-relative">
            <div class="d-none d-lg-flex justify-content-end">
                <div class="dropdown">
                    <button class="btn-circle p-0 bg-primary absolute-white size-1-125rem" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots fs-10 grid-center"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="d-flex align-items-center gap-2" href="{{ route('customer.deletion.show') }}">
                                {{ translate('delete_my_account') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div
                class="d-lg-none bg-primary rounded px-1 text-white cursor-pointer position-absolute end-1 top-1 profile-menu-toggle">
                <i class="bi bi-list fs-18"></i>
            </div>
            <div class="d-flex flex-row flex-lg-column gap-2 gap-lg-4 align-items-center">
                <div class="avatar overflow-hidden profile-sidebar-avatar border border-primary rounded-circle d-flex">
                    <img class="img-fit dark-support"
                         src="{{ getStorageImages(path: $customer_info->image_full_url, type:'avatar') }}" alt="">
                </div>

                <div class="text-lg-center">
                    <h5 class="mb-1">{{$customer_info->f_name}} {{$customer_info->l_name}}</h5>
                    <p class="fw-medium">{{translate('joined')}} {{date('d M, Y',strtotime($customer_info->created_at))}}</p>
                </div>
            </div>
            <div class="profile-menu-aside">
                <div class="profile-menu-aside-close d-lg-none">
                    <i class="bi bi-x-lg text-primary"></i>
                </div>
<ul class="list-unstyled profile-menu gap-1 mt-3"><li><a href="{{ route('user-profile') }}"><i class="bi bi-person" aria-hidden="true"></i><span>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'الملف الشخصي' : 'Profile' }}</span></a></li>
@include('customer-account.sidebar-communication')
<li><a href="{{ route('customer.tracking.index') }}">@include('customer-subscription.icon',['name'=>'orders'])<span>تتبع الطلبات</span></a></li>
<li><a href="{{ route('shop-cart') }}"><i class="bi bi-cart3" aria-hidden="true"></i><span>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'سلة المشتريات' : 'Cart' }}</span></a></li><li><a href="{{ route('customer.subscription.index') }}"><i class="bi bi-calendar-check" aria-hidden="true"></i><span>الاشتراكات</span></a></li><li><a href="{{ route('wallet') }}"><i class="bi bi-wallet2" aria-hidden="true"></i><span>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'المحفظة' : 'Wallet' }}</span></a></li><li><a href="{{ route('account-address') }}"><i class="bi bi-geo-alt" aria-hidden="true"></i><span>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'العناوين المحفوظة' : 'Saved addresses' }}</span></a></li><li><a href="{{ route('account-oder') }}"><i class="bi bi-box-seam" aria-hidden="true"></i><span>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'الطلبات' : 'Orders' }}</span></a></li><li><a href="{{ route('wishlists') }}"><i class="bi bi-heart" aria-hidden="true"></i><span>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'المفضلة' : 'Wishlist' }}</span></a></li><li><a href="{{ route('customer.messages.index',['password_help'=>1]) }}"><i class="bi bi-key" aria-hidden="true"></i><span>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'نسيت كلمة المرور؟' : 'Forgot password?' }}</span></a></li><li><a href="{{ route('customer.auth.logout') }}"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'تسجيل الخروج' : 'Log out' }}</span></a></li></ul>
            </div><div class="aside-overlay"></div>
        </div>
    </div>
</div>

