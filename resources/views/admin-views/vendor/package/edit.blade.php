@extends('layouts.admin.app')

@section('title', translate('Update_Vendor_Package'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="h1 mb-1 text-capitalize">{{ translate('Update_Vendor_Package') }}</h2>
                @if($package->subscriptions_count > 0)
                    <p class="mb-0 text-muted fs-12">{{ translate('changes_apply_to_new_purchases_only_existing_subscription_snapshots_remain_unchanged') }}.</p>
                @endif
            </div>
            <a href="{{ route('admin.vendors.packages.index') }}" class="btn btn-secondary">{{ translate('back') }}</a>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.vendors.packages.update', ['id' => $package->id]) }}" method="post">
                    @csrf
                    @include('admin-views.vendor.package.partials.form-fields', ['package' => $package])
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="{{ route('admin.vendors.packages.index') }}" class="btn btn-secondary">{{ translate('cancel') }}</a>
                        <button type="submit" class="btn btn-primary"><i class="fi fi-sr-disk"></i> {{ translate('Update_Package') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
