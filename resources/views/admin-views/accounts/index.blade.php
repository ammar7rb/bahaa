@extends('layouts.admin.app')
@section('title', translate('directory_title'))
@push('css_or_js')
<link rel="stylesheet" href="{{ dynamicAsset(path:'public/assets/back-end/css/admin-account-directory.css') }}?v=2">
@endpush
@section('content')
<div class="account-directory">
    <div class="admin-page-heading"><div><h1>{{ translate('directory_title') }}</h1><p>{{ translate('directory_help') }}</p></div><span>{{ number_format($accounts->total()) }} {{ translate('directory_matching') }}</span></div>
    @if(session('directory_notice'))<p class="directory-feedback" role="status">{{ session('directory_notice') }}</p>@endif
    @if($errors->any())<div class="directory-feedback is-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @php
        $exportFilters=$filters ?? request()->only(['q','type','status','sort','direction','joined_from','joined_to','ordered_from','ordered_to','first']);
    @endphp
    <details class="directory-panel directory-filter-panel" open data-directory-filters>
        <summary class="directory-filter-heading"><span id="directory-filter-title"><i class="fi fi-rr-settings-sliders" aria-hidden="true"></i>{{ translate('directory_filter_title') }}</span><i class="fi fi-rr-angle-small-down" aria-hidden="true"></i></summary>
        <form id="directory-filter-form" class="directory-filters" method="get" action="{{ route('admin.account-directory.index') }}">
            <label><span>{{ translate('directory_type') }}</span><select name="type"><option value="">{{ translate('directory_all') }}</option>@foreach(['customer','vendor'] as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ translate($type) }}</option>@endforeach</select></label>
            <label><span>{{ translate('directory_status') }}</span><select name="status"><option value="">{{ translate('directory_all') }}</option>@foreach(['active','frozen','pending','rejected'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ translate('directory_'.$status) }}</option>@endforeach</select></label>
            <label><span>{{ translate('directory_first') }}</span><input type="number" name="first" min="1" max="100000" value="{{ request('first') }}" placeholder="{{ translate('directory_no_limit') }}"></label>
            <fieldset><legend>{{ translate('directory_joined') }}</legend><div class="directory-field-pair">@foreach(['joined_from'=>'directory_from','joined_to'=>'directory_to'] as $field=>$label)<label><span>{{ translate($label) }}</span><input type="date" name="{{ $field }}" value="{{ request($field) }}" aria-label="{{ translate('directory_'.$field) }}"></label>@endforeach</div></fieldset>
            <fieldset><legend>{{ translate('directory_ordered') }}</legend><div class="directory-field-pair">@foreach(['ordered_from'=>'directory_from','ordered_to'=>'directory_to'] as $field=>$label)<label><span>{{ translate($label) }}</span><input type="date" name="{{ $field }}" value="{{ request($field) }}" aria-label="{{ translate('directory_'.$field) }}"></label>@endforeach</div></fieldset>
            <fieldset><legend>{{ translate('directory_sort') }}</legend><div class="directory-field-pair"><label><span>{{ translate('directory_sort_column') }}</span><select name="sort">@foreach(['created_at'=>'directory_joined','name'=>'directory_name','identifier'=>'directory_identifier','orders_count'=>'directory_orders_count','order_amount'=>'directory_order_amount','status'=>'directory_status'] as $sort=>$label)<option value="{{ $sort }}" @selected(request('sort','created_at')===$sort)>{{ translate($label) }}</option>@endforeach</select></label><label><span>{{ translate('directory_direction') }}</span><select name="direction"><option value="desc" @selected(request('direction','desc')==='desc')>{{ translate('directory_desc') }}</option><option value="asc" @selected(request('direction')==='asc')>{{ translate('directory_asc') }}</option></select></label></div></fieldset>
            <div class="directory-filter-footer"><p>{{ translate('directory_filter_help') }}</p><a class="directory-reset" href="{{ route('admin.account-directory.index') }}">{{ translate('directory_reset') }}</a><button type="submit" class="directory-button is-primary"><i class="fi fi-rr-filter" aria-hidden="true"></i>{{ translate('directory_apply') }}</button></div>
        </form>
    </details>
    <section class="directory-panel" aria-label="{{ translate('directory_title') }}">
        <div class="directory-toolbar">
            <div class="directory-table-heading"><h2>{{ translate('directory_accounts') }}</h2><span>{{ number_format($accounts->total()) }}</span></div>
            <div class="directory-tools"><label class="directory-search"><span class="directory-visually-hidden">{{ translate('Search') }}</span><i class="fi fi-rr-search" aria-hidden="true"></i><input form="directory-filter-form" name="q" type="search" maxlength="100" value="{{ request('q') }}" placeholder="{{ translate('directory_search_hint') }}"></label><button form="directory-filter-form" type="submit" class="directory-button" aria-label="{{ translate('Search') }}"><i class="fi fi-rr-search" aria-hidden="true"></i></button>
                <a class="directory-button directory-export" href="{{ route('admin.account-directory.export',$exportFilters) }}" data-pjax-skip="true" title="{{ translate('directory_export_help') }}"><i class="fi fi-rr-download" aria-hidden="true"></i>{{ translate('directory_export') }}</a>
                <a class="directory-button" href="{{ route('admin.account-directory.print',$exportFilters) }}" target="_blank" rel="noopener" title="{{ translate('directory_export_help') }}"><i class="fi fi-rr-print" aria-hidden="true"></i>{{ translate('directory_print') }}</a>
            </div>
        </div>
        <p class="directory-id-note">{{ translate('directory_identifier_note') }}</p>
        <div class="directory-scroll" tabindex="0" role="region" aria-label="{{ translate('directory_table_scroll') }}">
            <table class="directory-table">
                <caption class="sr-only">{{ translate('directory_help') }}</caption>
                <thead><tr>
                    <th scope="col">#</th>
                    @foreach(['identifier'=>'directory_identifier','name'=>'directory_name'] as $sort=>$label)
                        <th scope="col" aria-sort="{{ request('sort','created_at')===$sort ? (request('direction','desc')==='asc'?'ascending':'descending') : 'none' }}"><a href="{{ request()->fullUrlWithQuery(['sort'=>$sort,'direction'=>request('sort')===$sort && request('direction')==='asc'?'desc':'asc','page'=>1]) }}">{{ translate($label) }} <i class="fi fi-rr-sort" aria-hidden="true"></i></a></th>
                    @endforeach
                    <th scope="col">{{ translate('directory_type') }}</th><th scope="col">{{ translate('directory_contacts') }}</th>
                    <th scope="col" aria-sort="{{ request('sort')==='status' ? (request('direction')==='asc'?'ascending':'descending') : 'none' }}"><a href="{{ request()->fullUrlWithQuery(['sort'=>'status','direction'=>request('sort')==='status' && request('direction')==='asc'?'desc':'asc','page'=>1]) }}">{{ translate('directory_management') }} <i class="fi fi-rr-sort" aria-hidden="true"></i></a></th>
                </tr></thead>
                <tbody>
                @forelse($accounts as $account)
                    @php
                        $identifier=($account->kind==='customer'?'c':'m').$account->id;
                        $name=trim($account->f_name.' '.$account->l_name) ?: translate('directory_missing');
                        $extra=$contacts[$account->kind.':'.$account->id] ?? [];
                        $profile=route($account->kind==='customer'?'admin.customer.view':'admin.vendors.view',$account->id);
                    @endphp
                    <tr>
                        <td class="directory-row-number">{{ $accounts->firstItem()+$loop->index }}</td>
                        <td><a class="directory-reference" href="{{ $profile }}"><bdi>{{ $identifier }}</bdi></a></td>
                        <td class="directory-person"><div class="directory-identity"><span class="directory-avatar is-{{ $account->kind }}" aria-hidden="true">{{ mb_substr($name,0,1) }}</span><div class="directory-name">@if($account->kind==='vendor')<div><span>{{ translate('directory_person_label') }}</span><a href="{{ $profile }}">{{ $name }}</a></div><div><span>{{ translate('directory_store_label') }}</span>{{ $account->store_name ?: translate('directory_missing') }}</div>@else<a href="{{ $profile }}">{{ $name }}</a>@endif<div class="directory-activity">{{ translate('directory_orders_count') }}: {{ number_format($account->orders_count) }}</div></div></div></td>
                        <td><span class="directory-type is-{{ $account->kind }}"><i class="fi fi-rr-{{ $account->kind==='vendor'?'shop':'user' }}" aria-hidden="true"></i>{{ translate($account->kind) }}</span></td>
                        <td class="directory-contact"><div><i class="fi fi-rr-envelope" aria-hidden="true"></i><bdi>{{ $account->email ?: translate('directory_missing') }}</bdi></div><div><i class="fi fi-rr-phone-call" aria-hidden="true"></i><bdi>{{ $account->phone ?: translate('directory_missing') }}</bdi></div>@if($extra)<details><summary aria-label="{{ translate('directory_extra_contacts') }}: {{ count($extra) }}"><bdi>+{{ count($extra) }}</bdi> {{ translate('directory_extra_contacts') }}</summary><ul>@foreach($extra as $contact)<li><bdi>{{ $contact }}</bdi></li>@endforeach</ul></details>@endif</td>
                        <td><div class="directory-management"><span class="directory-status is-{{ $account->account_status }}">{{ translate('directory_'.$account->account_status) }}</span><div class="directory-actions">
                            <a class="directory-button" href="{{ $profile }}" aria-label="{{ translate('directory_view') }} {{ $name }}"><i class="fi fi-rr-eye" aria-hidden="true"></i>{{ translate('directory_view') }}</a>
                            @if(in_array($account->account_status,['active','frozen']))<form action="{{ route('admin.account-directory.status',[$account->kind,$account->id]) }}" method="post">@csrf<input type="hidden" name="action" value="{{ $account->account_status==='frozen'?'reactivate':'freeze' }}"><button class="directory-button" aria-label="{{ translate($account->account_status==='frozen'?'directory_reactivate':'directory_freeze') }} {{ $name }}">{{ translate($account->account_status==='frozen'?'directory_reactivate':'directory_freeze') }}</button></form>@endif
                            @if((int)auth('admin')->user()->admin_role_id===1)<button type="button" class="directory-button is-danger" data-delete-account data-url="{{ route('admin.account-directory.destroy',[$account->kind,$account->id]) }}" data-identifier="{{ $identifier }}" data-name="{{ $name }}" aria-label="{{ translate('directory_delete') }} {{ $name }}"><i class="fi fi-rr-trash" aria-hidden="true"></i>{{ translate('directory_delete') }}</button>@endif
                        </div></div></td>
                    </tr>
                @empty<tr><td colspan="6"><div class="directory-empty"><i class="fi fi-rr-users" aria-hidden="true"></i><strong>{{ translate('directory_empty') }}</strong><span>{{ translate('directory_empty_help') }}</span></div></td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="directory-pagination"><div class="directory-page-size"><span>{{ $accounts->firstItem() ?? 0 }}–{{ $accounts->lastItem() ?? 0 }} / {{ number_format($accounts->total()) }}</span><label>{{ translate('directory_per_page') }} <select form="directory-filter-form" name="per_page" onchange="this.form.requestSubmit()">@foreach([20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',20)===$size)>{{ $size }}</option>@endforeach</select></label></div>{{ $accounts->links() }}</div>
    </section>
    <dialog class="directory-dialog" id="directory-delete-dialog" aria-labelledby="directory-delete-title" aria-describedby="directory-delete-description">
        <form method="post" id="directory-delete-form">@csrf @method('DELETE')
            <h2 id="directory-delete-title">{{ translate('directory_delete') }}</h2><p id="directory-delete-description">{{ translate('directory_delete_warning') }}</p><strong id="directory-delete-account"></strong>
            <label>{{ translate('directory_type_identifier') }} <input name="confirmation" autocomplete="off" required dir="ltr"></label>
            <label class="directory-acknowledge"><input type="checkbox" name="acknowledge" value="1" required>{{ translate('directory_irreversible') }}</label>
            <div class="directory-dialog-actions"><button type="button" class="directory-button" data-cancel-delete>{{ translate('cancel') }}</button><button class="directory-button is-danger">{{ translate('directory_confirm_delete') }}</button></div>
        </form>
    </dialog>
</div>
@endsection
@push('script')<script src="{{ dynamicAsset(path:'public/assets/back-end/js/admin-account-directory.js') }}?v=2"></script>@endpush
