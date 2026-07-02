@extends('layouts.admin.app')

@section('title', translate('Customer_Purchase_Packages'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-4">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/back-end/img/customer.png') }}" alt="">
                {{ translate('Customer_Purchase_Packages') }}
                <span class="badge badge-soft-dark radius-50">{{ $packages->total() }}</span>
            </h2>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form action="{{ route('admin.customer.purchase-package.store') }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label">{{ translate('Package_Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                                   placeholder="{{ translate('Ex') }} : {{ translate('Starter') }}" required>
                            @error('name') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label">
                                {{ translate('Package_Price') }}
                                ({{ getCurrencySymbol(currencyCode: getCurrencyCode(type: 'default')) }})
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="package_price" class="form-control" min="0.01" step="0.01"
                                   value="{{ old('package_price') }}" placeholder="{{ translate('Ex') }} : 100" required>
                            @error('package_price') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label">
                                {{ translate('Purchase_Limit') }}
                                ({{ getCurrencySymbol(currencyCode: getCurrencyCode(type: 'default')) }})
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="purchase_limit" class="form-control" min="0.01" step="0.01"
                                   value="{{ old('purchase_limit') }}" placeholder="{{ translate('Ex') }} : 1000" required>
                            @error('purchase_limit') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label">{{ translate('Sort_Order') }}</label>
                            <input type="number" name="sort_order" class="form-control" min="0"
                                   value="{{ old('sort_order', 0) }}" placeholder="{{ translate('Ex') }} : 1">
                            @error('sort_order') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label">{{ translate('Custom_Package_For_Customer') }}</label>
                            <div class="select-wrapper">
                                <select name="customer_id" class="form-select">
                                    <option value="">{{ translate('General_Package') }}</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                            {{ trim($customer->f_name . ' ' . $customer->l_name) ?: $customer->email }}
                                            - {{ $customer->email ?: $customer->phone }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('customer_id') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label">{{ translate('Status') }}</label>
                            <div class="select-wrapper">
                                <select name="status" class="form-select">
                                    <option value="1" {{ old('status', 1) == 1 ? 'selected' : '' }}>{{ translate('Active') }}</option>
                                    <option value="0" {{ old('status') === '0' ? 'selected' : '' }}>{{ translate('Inactive') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('Description') }}</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="{{ translate('Short_description') }}">{{ old('description') }}</textarea>
                            @error('description') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-end gap-3">
                                <button type="reset" class="btn btn-secondary">{{ translate('reset') }}</button>
                                <button type="submit" class="btn btn-primary">{{ translate('submit') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                    <h3 class="mb-0">
                        {{ translate('Package_List') }}
                        <span class="badge badge-info text-bg-info">{{ $packages->total() }}</span>
                    </h3>
                    <form action="{{ url()->current() }}" method="GET" class="min-w-100-mobile">
                        <div class="d-flex flex-wrap gap-2">
                            <div class="select-wrapper">
                                <select name="type" class="form-select">
                                    <option value="">{{ translate('All_Types') }}</option>
                                    <option value="general" {{ request('type') === 'general' ? 'selected' : '' }}>{{ translate('General') }}</option>
                                    <option value="custom" {{ request('type') === 'custom' ? 'selected' : '' }}>{{ translate('Custom') }}</option>
                                </select>
                            </div>
                            <div class="select-wrapper">
                                <select name="status" class="form-select">
                                    <option value="">{{ translate('All_Status') }}</option>
                                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ translate('Active') }}</option>
                                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ translate('Inactive') }}</option>
                                </select>
                            </div>
                            <div class="input-group max-w-280">
                                <input type="search" name="searchValue" class="form-control"
                                       placeholder="{{ translate('search_by_package_or_customer') }}"
                                       value="{{ request('searchValue') }}">
                                <div class="input-group-append search-submit">
                                    <button type="submit">
                                        <i class="fi fi-rr-search"></i>
                                    </button>
                                </div>
                            </div>
                            <a href="{{ route('admin.customer.purchase-package.index') }}" class="btn btn-secondary">
                                {{ translate('reset') }}
                            </a>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light thead-50 text-capitalize">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Package') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th class="text-center">{{ translate('Package_Price') }}</th>
                            <th class="text-center">{{ translate('Purchase_Limit') }}</th>
                            <th class="text-center">{{ translate('Assigned_Customers') }}</th>
                            <th class="text-center">{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($packages as $key => $package)
                            <tr>
                                <td>{{ $packages->firstItem() + $key }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $package->name }}</div>
                                    @if($package->description)
                                        <div class="fs-12 text-muted text-truncate max-w-300">{{ $package->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($package->is_custom)
                                        <span class="badge badge-soft-info">{{ translate('Custom') }}</span>
                                        <div class="fs-12 mt-1">
                                            {{ $package->customer ? trim($package->customer->f_name . ' ' . $package->customer->l_name) : translate('customer_not_found') }}
                                        </div>
                                    @else
                                        <span class="badge badge-soft-success">{{ translate('General') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $package->package_price)) }}</td>
                                <td class="text-center">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $package->purchase_limit)) }}</td>
                                <td class="text-center">
                                    <span class="badge badge-info text-bg-info">{{ $package->subscriptions_count }}</span>
                                </td>
                                <td>
                                    <form action="{{ route('admin.customer.purchase-package.status') }}" method="post"
                                          id="purchase-package-status-{{ $package->id }}-form" class="no-reload-form">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $package->id }}">
                                        <label class="switcher mx-auto" for="purchase-package-status-{{ $package->id }}">
                                            <input class="switcher_input custom-modal-plugin" type="checkbox"
                                                   value="1" name="status"
                                                   id="purchase-package-status-{{ $package->id }}"
                                                   {{ $package->status ? 'checked' : '' }}
                                                   data-modal-type="input-change-form" data-reload="true"
                                                   data-modal-form="#purchase-package-status-{{ $package->id }}-form"
                                                   data-on-title="{{ translate('Want_to_Turn_ON') . ' ' . $package->name . ' ' . translate('status') }}"
                                                   data-off-title="{{ translate('Want_to_Turn_OFF') . ' ' . $package->name . ' ' . translate('status') }}"
                                                   data-on-message="<p>{{ translate('if_enabled_customers_can_buy_this_package') }}</p>"
                                                   data-off-message="<p>{{ translate('if_disabled_customers_cannot_buy_this_package') }}</p>"
                                                   data-on-button-text="{{ translate('turn_on') }}"
                                                   data-off-button-text="{{ translate('turn_off') }}">
                                            <span class="switcher_control"></span>
                                        </label>
                                    </form>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <a title="{{ translate('edit') }}" class="btn btn-outline-info icon-btn"
                                           href="{{ route('admin.customer.purchase-package.edit', ['id' => $package->id]) }}">
                                            <i class="fi fi-rr-pencil"></i>
                                        </a>
                                        <a title="{{ translate('delete') }}" class="btn btn-outline-danger icon-btn delete-data"
                                           href="javascript:" data-id="purchase-package-{{ $package->id }}">
                                            <i class="fi fi-rr-trash"></i>
                                        </a>
                                    </div>
                                    <form action="{{ route('admin.customer.purchase-package.delete', ['id' => $package->id]) }}"
                                          method="post" id="purchase-package-{{ $package->id }}">
                                        @csrf
                                        @method('delete')
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end p-4">
                    {!! $packages->links() !!}
                </div>
                @if(count($packages) == 0)
                    @include('layouts.admin.partials._empty-state', ['text' => 'no_package_found'], ['image' => 'default'])
                @endif
            </div>
        </div>
    </div>
@endsection
