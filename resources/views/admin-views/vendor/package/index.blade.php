@extends('layouts.admin.app')

@section('title', translate('Vendor_Packages'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="h1 mb-1 text-capitalize">{{ translate('Vendor_Packages') }}</h2>
                <p class="mb-0 text-muted fs-12">{{ translate('create_any_number_of_vendor_packages_and_control_every_quota_and_duration') }}.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.vendors.package-payments.index') }}" class="btn btn-primary">{{ translate('Package_Offline_Reviews') }}</a>
                <a href="{{ route('admin.vendors.insurance-payments.index') }}" class="btn btn-secondary">{{ translate('Insurance_Offline_Reviews') }}</a>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form action="{{ route('admin.vendors.packages.store') }}" method="post">
                    @csrf
                    @include('admin-views.vendor.package.partials.form-fields')
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button type="reset" class="btn btn-secondary">{{ translate('reset') }}</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fi fi-sr-add"></i>
                            {{ translate('Add_Package') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                    <h3 class="mb-0">{{ translate('Package_List') }} <span class="badge badge-info text-bg-info">{{ $packages->total() }}</span></h3>
                    <form action="{{ url()->current() }}" method="get">
                        <div class="d-flex flex-wrap gap-2">
                            <div class="select-wrapper">
                                <select name="status" class="form-select">
                                    <option value="">{{ translate('All_Status') }}</option>
                                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ translate('Active') }}</option>
                                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ translate('Inactive') }}</option>
                                </select>
                            </div>
                            <div class="input-group max-w-280">
                                <input type="search" name="searchValue" class="form-control"
                                       placeholder="{{ translate('search_by_package_name') }}" value="{{ request('searchValue') }}">
                                <div class="input-group-append search-submit"><button type="submit"><i class="fi fi-rr-search"></i></button></div>
                            </div>
                            <a href="{{ route('admin.vendors.packages.index') }}" class="btn btn-secondary">{{ translate('reset') }}</a>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light thead-50 text-capitalize">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Package') }}</th>
                            <th>{{ translate('Product_Listings') }}</th>
                            <th>{{ translate('Featured_Search') }}</th>
                            <th>{{ translate('Homepage_Featured') }}</th>
                            <th>{{ translate('Subscriptions') }}</th>
                            <th class="text-center">{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($packages as $key => $package)
                            <tr>
                                <td>{{ $packages->firstItem() + $key }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $package->name }}</div>
                                    <div class="fs-12 text-primary">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $package->package_price)) }}</div>
                                    <div class="fs-12 text-muted">
                                        {{ $package->package_validity_days ? $package->package_validity_days . ' ' . translate('days_validity') : translate('No_Expiry') }}
                                        · {{ translate('Order') }}: {{ $package->sort_order }}
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ $package->product_limit }}</strong> {{ translate('products') }}
                                    <div class="fs-12 text-muted">{{ $package->product_duration_days }} {{ translate('days_each') }}</div>
                                </td>
                                <td>
                                    @if($package->search_promotion_limit > 0)
                                        <strong>{{ $package->search_promotion_limit }}</strong> {{ translate('promotions') }}
                                        <div class="fs-12 text-muted">{{ $package->search_promotion_duration_days }} {{ translate('days_each') }}</div>
                                    @else
                                        <span class="text-muted">{{ translate('Not_Included') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($package->homepage_promotion_limit > 0)
                                        <strong>{{ $package->homepage_promotion_limit }}</strong> {{ translate('promotions') }}
                                        <div class="fs-12 text-muted">{{ $package->homepage_promotion_duration_days }} {{ translate('days_each') }}</div>
                                    @else
                                        <span class="text-muted">{{ translate('Not_Included') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-soft-dark">{{ $package->subscriptions_count }}</span>
                                    <div class="fs-12 text-muted">{{ $package->active_subscriptions_count }} {{ translate('active') }}</div>
                                </td>
                                <td>
                                    <form action="{{ route('admin.vendors.packages.status') }}" method="post"
                                          id="seller-package-status-{{ $package->id }}-form" class="no-reload-form">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $package->id }}">
                                        <label class="switcher mx-auto" for="seller-package-status-{{ $package->id }}">
                                            <input class="switcher_input custom-modal-plugin" type="checkbox" value="1" name="status"
                                                   id="seller-package-status-{{ $package->id }}" {{ $package->status ? 'checked' : '' }}
                                                   data-modal-type="input-change-form" data-reload="true"
                                                   data-modal-form="#seller-package-status-{{ $package->id }}-form"
                                                   data-on-title="{{ translate('Activate') }} {{ $package->name }}?"
                                                   data-off-title="{{ translate('Deactivate') }} {{ $package->name }}?"
                                                   data-on-message="<p>{{ translate('vendors_will_be_able_to_buy_this_package') }}</p>"
                                                   data-off-message="<p>{{ translate('new_purchases_will_stop_but_existing_subscriptions_will_not_change') }}</p>">
                                            <span class="switcher_control"></span>
                                        </label>
                                    </form>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <a class="btn btn-outline-info icon-btn" title="{{ translate('edit') }}"
                                           href="{{ route('admin.vendors.packages.edit', ['id' => $package->id]) }}"><i class="fi fi-rr-pencil"></i></a>
                                        <a class="btn btn-outline-danger icon-btn delete-data" title="{{ translate('delete') }}"
                                           href="javascript:" data-id="seller-package-{{ $package->id }}"><i class="fi fi-rr-trash"></i></a>
                                    </div>
                                    <form action="{{ route('admin.vendors.packages.delete', ['id' => $package->id]) }}" method="post" id="seller-package-{{ $package->id }}">
                                        @csrf
                                        @method('delete')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">{{ translate('No_data_found') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end p-4">{!! $packages->links() !!}</div>
            </div>
        </div>
    </div>
@endsection
