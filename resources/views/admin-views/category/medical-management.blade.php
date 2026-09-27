@extends('layouts.admin.app')

@section('title', translate('medical_category_management'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="h1 mb-0">{{ translate('medical_category_management') }}</h2>
                <p class="text-muted mb-0">{{ translate('manage_category_availability_and_order_without_deleting_used_categories') }}</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ route('admin.category.view') }}">{{ translate('open_full_category_setup') }}</a>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ translate('category') }}</th><th>{{ translate('level') }}</th><th>{{ translate('products') }}</th><th>{{ translate('priority') }}</th><th>{{ translate('availability') }}</th></tr></thead>
                    <tbody>
                    @forelse($categories as $category)
                        @include('admin-views.category.partials._medical-category-row', ['category' => $category, 'level' => 0])
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('no_category_found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
