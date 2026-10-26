<div class="position-relative nav--tab-wrapper mb-4">
    <ul class="nav nav-pills nav--tab p-0" id="pills-tab" role="tablist">
        <li class="nav-item">
            <a class="nav-link {{ Request::is('admin/business-settings/web-config') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.web-config.index') }}">
                {{ translate('General') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ Request::is('admin/business-settings/website-setup') ? 'active' : '' }}"
            href="{{ route('admin.business-settings.website-setup') }}">
                {{ translate('Website_Setup') }}
            </a>
        </li>
        {{-- Legacy Delivery Man settings are hidden; stored values remain available for historical order records. --}}
        <li class="nav-item">
            <a class="nav-link {{ Request::is('admin/business-settings/invoice-settings') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.invoice-settings.index') }}">
                {{ translate('Invoice') }}
            </a>
        </li>
    </ul>
    <div class="nav--tab__prev">
        <button type="button" class="btn btn-circle border-0 bg-white text-primary">
            <i class="fi fi-sr-angle-left"></i>
        </button>
    </div>
    <div class="nav--tab__next">
        <button class="btn btn-circle border-0 bg-white text-primary">
            <i class="fi fi-sr-angle-right"></i>
        </button>
    </div>

</div>
