@php
    use function App\Utils\customer_info;
    $customer_info = auth('customer')->user();
@endphp
<div class="col-lg-3">
    <div class="card profile-sidebar-sticky">
        <div class="card-body position-relative">
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
                    <bdi class="d-block mb-1" dir="ltr">{{ $customer_info->administrative_reference }}</bdi>
                    <p class="mb-1" data-account-status="{{ $customer_info->is_active ? 'active' : 'suspended' }}">{{ app()->getLocale() === 'ar' ? ($customer_info->is_active ? 'نشط' : 'معلق') : ($customer_info->is_active ? 'Active' : 'Suspended') }}</p>
                    <p class="fw-medium">{{translate('joined')}} {{date('d M, Y',strtotime($customer_info->created_at))}}</p>
                </div>
            </div>
            <div class="profile-menu-aside">
                <div class="profile-menu-aside-close d-lg-none">
                    <i class="bi bi-x-lg text-primary"></i>
                </div>
<ul class="list-unstyled profile-menu gap-1 mt-3">
@foreach([['user-profile','user-profile|user-account|account-address*','bi-person','الملف الشخصي'],['account-oder','account-oder|account-order*|customer/tracking*','bi-box-seam','الطلبات'],['wallet','wallet*','bi-wallet2','المحفظة'],['customer.subscription.index','customer/subscription*','bi-calendar-check','الاشتراكات'],['customer.settings','account-settings|customer/account-deletion*','bi-gear','الإعدادات'],['customer.messages.index','account-messages|account-tickets|support-ticket*','bi-headset','الدعم الفني']] as [$menuRoute,$patterns,$icon,$label])
<li @class(['active' => request()->is(...explode('|', $patterns))])><a href="{{ route($menuRoute) }}" @if(request()->is(...explode('|', $patterns))) aria-current="page" @endif><i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a></li>
@endforeach
</ul>
            </div><div class="aside-overlay"></div>
        </div>
    </div>
</div>

