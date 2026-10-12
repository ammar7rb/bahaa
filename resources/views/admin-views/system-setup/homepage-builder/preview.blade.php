@extends('layouts.admin.app')

@section('title', translate('Homepage_Draft_Preview'))

@section('content')
<div class="content container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="mb-1">{{ translate('Homepage_Draft_Preview') }} &mdash; {{ $theme === 'default' ? 'Default' : 'Aster' }}</h1><p class="text-muted mb-0">{{ translate('This_is_an_administrative_data_preview_no_public_version_changes_until_publish') }}.</p></div><a href="{{ route('admin.system-setup.theme.homepage-builder.index',['theme'=>$theme]) }}" class="btn btn-outline-primary">{{ translate('Back_To_Builder') }}</a></div>
    <div class="d-flex flex-column gap-3">
        @foreach(collect($layout['sections'])->sortBy('sort_order') as $key=>$section)
            @if($section['is_visible'])<div class="card"><div class="card-header d-flex justify-content-between"><h2 class="mb-0">{{ translate($catalog[$key]['label'] ?? $key) }}</h2><span class="badge badge-soft-info">#{{ $section['sort_order'] }} &middot; {{ translate($section['source_mode']) }} &middot; {{ translate($section['automatic_source']) }}</span></div><div class="card-body">
                @if(($catalog[$key]['type'] ?? null)==='product')<div class="row g-2">@forelse($resolved[$key] as $product)<div class="col-md-3"><div class="border rounded p-2 h-100"><strong>#{{ $product->id }} &mdash; {{ $product->name }}</strong>@if($product->is_homepage_promoted ?? false)<div><span class="badge badge-warning">{{ translate($product->advertising_badge_label ?: 'Featured_Ad') }}</span></div>@endif</div></div>@empty<div class="text-muted">{{ translate('No_products_resolved_for_this_section') }}</div>@endforelse</div>
                @elseif(($catalog[$key]['type'] ?? null)==='banner')<div class="d-flex flex-wrap gap-2">@forelse($resolved[$key] as $banner)<span class="border rounded p-2">#{{ $banner->id }} &mdash; {{ $banner->title ?: $banner->banner_type }}</span>@empty<span class="text-muted">{{ translate('No_banners_resolved_for_this_section') }}</span>@endforelse</div>
                @else<p class="mb-0 text-muted">{{ translate('This_section_reuses_its_existing_platform_data_and_template') }}.</p>@endif
            </div></div>@endif
        @endforeach
    </div>
</div>
@endsection
