@extends('layouts.admin.app')

@section('title', translate('Update_Customer_Purchase_Package'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/back-end/img/customer.png') }}" alt="">
                {{ translate('Update_Customer_Purchase_Package') }}
            </h2>
            <a href="{{ route('admin.customer.purchase-package.index') }}" class="btn btn-secondary">
                {{ translate('back') }}
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.customer.purchase-package.update', ['id' => $package->id]) }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Package_Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $package->name) }}" required>
                            @error('name') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">
                                {{ translate('Package_Price') }}
                                ({{ getCurrencySymbol(currencyCode: getCurrencyCode(type: 'default')) }})
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="package_price" class="form-control" min="0.01" step="0.01"
                                   value="{{ old('package_price', usdToDefaultCurrency(amount: $package->package_price)) }}" required>
                            @error('package_price') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">
                                {{ translate('Purchase_Limit') }}
                                ({{ getCurrencySymbol(currencyCode: getCurrencyCode(type: 'default')) }})
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="purchase_limit" class="form-control" min="0.01" step="0.01"
                                   value="{{ old('purchase_limit', usdToDefaultCurrency(amount: $package->purchase_limit)) }}" required>
                            @error('purchase_limit') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Sort_Order') }}</label>
                            <input type="number" name="sort_order" class="form-control" min="0"
                                   value="{{ old('sort_order', $package->sort_order) }}">
                            @error('sort_order') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Custom_Package_For_Customer') }}</label>
                            <div class="select-wrapper">
                                <select name="customer_id" class="form-select">
                                    <option value="">{{ translate('General_Package') }}</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id', $package->customer_id) == $customer->id ? 'selected' : '' }}>
                                            {{ trim($customer->f_name . ' ' . $customer->l_name) ?: $customer->email }}
                                            - {{ $customer->email ?: $customer->phone }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('customer_id') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Status') }}</label>
                            <div class="select-wrapper">
                                <select name="status" class="form-select">
                                    <option value="1" {{ old('status', $package->status) == 1 ? 'selected' : '' }}>{{ translate('Active') }}</option>
                                    <option value="0" {{ old('status', $package->status) == 0 ? 'selected' : '' }}>{{ translate('Inactive') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('Description') }}</label>
                            <textarea name="description" class="form-control" rows="4">{{ old('description', $package->description) }}</textarea>
                            @error('description') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-end gap-3">
                                <a href="{{ route('admin.customer.purchase-package.index') }}" class="btn btn-secondary">{{ translate('cancel') }}</a>
                                <button type="submit" class="btn btn-primary">{{ translate('update') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
