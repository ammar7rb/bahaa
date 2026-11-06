@extends('layouts.admin.app')

@section('title', translate('Homepage_Section_Builder'))

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h1 class="mb-1">{{ translate('Homepage_Section_Builder') }}</h1><p class="text-muted mb-0">{{ translate('Manage_both_themes_from_one_center_without_duplicating_banners_or_product_rules') }}.</p></div>
        <div class="d-flex gap-2"><a target="_blank" href="{{ route('admin.system-setup.theme.homepage-builder.preview', $theme) }}" class="btn btn-outline-primary">{{ translate('Preview_Draft') }}</a><a href="{{ route('admin.system-setup.theme.setup') }}" class="btn btn-outline-secondary">{{ translate('Theme_Setup') }}</a></div>
    </div>

    <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
        <i class="fi fi-sr-info mt-1"></i>
        <div>
            <strong>{{ translate('homepage_product_visibility_policy') }}</strong>
            <div class="fs-13 mt-1">{{ translate('homepage_product_visibility_note') }}</div>
            <div class="fs-13 mt-1">{{ translate('homepage_builder_controls_sections_while_priority_setup_controls_item_order_inside_automatic_sections') }} <a href="{{ route('admin.business-settings.priority-setup.index') }}">{{ translate('open_priority_setup') }}</a>.</div>
        </div>
    </div>

    <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2">
        @foreach($themes as $themeOption)<a href="{{ route('admin.system-setup.theme.homepage-builder.index', ['theme'=>$themeOption]) }}" class="btn {{ $theme === $themeOption ? 'btn-primary' : 'btn-outline-primary' }}">{{ $themeOption === 'default' ? 'Default' : 'Aster' }}</a>@endforeach
        <span class="ms-auto badge badge-soft-{{ $readiness['ready'] ? 'success' : 'warning' }} align-self-center">{{ $readiness['ready'] ? translate('Published_And_Ready') : translate('Not_Ready_For_Activation') }}</span>
    </div></div>

    @if(!$readiness['ready'])<div class="alert alert-warning"><strong>{{ translate('Activation_is_blocked_until_the_theme_has_a_valid_published_layout') }}.</strong><ul class="mb-0 mt-2">@foreach($readiness['errors'] as $error)<li>{{ translate($error) }}</li>@endforeach</ul></div>@endif

    <form method="post" action="{{ route('admin.system-setup.theme.homepage-builder.save') }}">@csrf
        <input type="hidden" name="theme" value="{{ $theme }}">
        <div class="card"><div class="card-header"><div><h2 class="mb-1">{{ translate('Draft') }} #{{ $draft->version }}</h2><p class="mb-0 text-muted fs-12">{{ translate('Automatic_uses_existing_platform_data_manual_uses_selected_ids_and_mixed_puts_selected_items_first') }}.</p></div></div>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ translate('Visible') }}</th><th>{{ translate('Section') }}</th><th>{{ translate('Order') }}</th><th>{{ translate('Source_Mode') }}</th><th>{{ translate('Automatic_Source') }}</th><th>{{ translate('Selected_IDs') }}</th><th>{{ translate('Language') }}</th><th>{{ translate('Period') }}</th><th>{{ translate('Limit') }}</th></tr></thead>
                <tbody>@foreach($catalog as $key=>$definition)@php($section=$sections->get($key))<tr>
                    <td><input type="hidden" name="sections[{{ $key }}][is_visible]" value="0"><input type="checkbox" name="sections[{{ $key }}][is_visible]" value="1" @checked($section?->is_visible ?? true)></td>
                    <td><strong>{{ translate($definition['label']) }}</strong><div class="fs-11 text-muted">{{ translate(ucfirst($definition['type']).'_Section') }}</div></td>
                    <td><input class="form-control" style="min-width:80px" type="number" min="1" name="sections[{{ $key }}][sort_order]" value="{{ old('sections.'.$key.'.sort_order', $section?->sort_order ?? $definition['default_order']) }}" required></td>
                    <td><select class="form-control" name="sections[{{ $key }}][source_mode]">@foreach(['automatic','manual','mixed'] as $mode)<option value="{{ $mode }}" @selected(($section?->source_mode ?? 'automatic')===$mode)>{{ translate($mode) }}</option>@endforeach</select></td>
                    <td>@if($definition['type']==='product')<select class="form-control" name="sections[{{ $key }}][automatic_source]">@foreach(\App\Services\HomepageSectionBuilderService::PRODUCT_SOURCES as $source)<option value="{{ $source }}" @selected(($section?->automatic_source ?? $definition['source'])===$source)>{{ translate($source) }}</option>@endforeach</select>@else<input type="hidden" name="sections[{{ $key }}][automatic_source]" value="{{ $definition['source'] }}"><span class="text-muted">{{ translate('Existing_Platform_Data') }}</span>@endif</td>
                    <td style="min-width:180px">@if($definition['type']==='product')<input class="form-control" name="sections[{{ $key }}][product_ids]" value="{{ implode(',', $section?->product_ids ?? []) }}" placeholder="12,25,40"><div class="fs-10 text-muted">{{ translate('Product_IDs_in_display_order') }}</div>@elseif($definition['type']==='banner')<input class="form-control" name="sections[{{ $key }}][banner_ids]" value="{{ implode(',', $section?->banner_ids ?? []) }}" placeholder="1,4"><div class="fs-10 text-muted">{{ translate('Banner_IDs_in_display_order') }}</div>@else — @endif</td>
                    <td><select class="form-control" name="sections[{{ $key }}][locale]"><option value="all" @selected(($section?->locale ?? 'all')==='all')>{{ translate('All') }}</option><option value="ar" @selected($section?->locale==='ar')>العربية</option><option value="en" @selected($section?->locale==='en')>English</option></select></td>
                    <td style="min-width:220px"><input type="datetime-local" class="form-control mb-1" name="sections[{{ $key }}][starts_at]" value="{{ $section?->starts_at?->format('Y-m-d\TH:i') }}"><input type="datetime-local" class="form-control" name="sections[{{ $key }}][expires_at]" value="{{ $section?->expires_at?->format('Y-m-d\TH:i') }}"></td>
                    <td><input class="form-control" style="min-width:70px" type="number" min="1" max="50" name="sections[{{ $key }}][limit]" value="{{ data_get($section?->settings, 'limit', 12) }}"></td>
                </tr>@endforeach</tbody>
            </table></div>
            <div class="card-footer"><label>{{ translate('Draft_Notes') }}</label><textarea class="form-control mb-3" name="notes" rows="2">{{ $draft->notes }}</textarea><button class="btn btn-primary">{{ translate('Save_Draft') }}</button></div>
        </div>
    </form>

    <div class="card mt-3"><div class="card-header"><h2 class="mb-0">{{ translate('Publish_And_Version_History') }}</h2></div><div class="card-body">
        <div class="d-flex gap-2 mb-3"><form method="post" action="{{ route('admin.system-setup.theme.homepage-builder.publish') }}">@csrf<input type="hidden" name="theme" value="{{ $theme }}"><button class="btn btn-success">{{ translate('Publish_Current_Draft') }}</button></form><a target="_blank" href="{{ route('admin.system-setup.theme.homepage-builder.preview', $theme) }}" class="btn btn-outline-primary">{{ translate('Preview_Before_Publishing') }}</a></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>{{ translate('Version') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Published_At') }}</th><th>{{ translate('Notes') }}</th><th>{{ translate('Action') }}</th></tr></thead><tbody>@forelse($history as $version)<tr><td>#{{ $version->version }}</td><td>{{ translate($version->status) }}</td><td>{{ $version->published_at }}</td><td>{{ $version->notes }}</td><td>@if($version->status==='archived')<form method="post" action="{{ route('admin.system-setup.theme.homepage-builder.rollback', $version) }}">@csrf<input type="hidden" name="theme" value="{{ $theme }}"><button class="btn btn-sm btn-outline-warning">{{ translate('Restore_This_Version') }}</button></form>@else — @endif</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">{{ translate('No_published_versions_yet') }}</td></tr>@endforelse</tbody></table></div>
    </div></div>
</div>
@endsection
