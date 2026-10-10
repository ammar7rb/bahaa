@extends('layouts.admin.app')
@section('title', translate('directory_title'))
@push('css_or_js')
<link rel="stylesheet" href="{{ dynamicAsset(path:'public/assets/back-end/css/admin-account-directory.css') }}?v=1">
@endpush
@section('content')
<div class="account-directory">
    <div class="admin-page-heading"><div><h1>{{ translate('directory_title') }}</h1><p>{{ translate('directory_help') }}</p></div><span>{{ number_format($accounts->total()) }} {{ translate('directory_matching') }}</span></div>
    @if(session('directory_notice'))<p class="directory-feedback" role="status">{{ session('directory_notice') }}</p>@endif
    @if($errors->any())<div class="directory-feedback is-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="directory-panel" aria-label="{{ translate('directory_title') }}">
        <form class="directory-filters" method="get">
            <label class="directory-search"><span>{{ translate('Search') }}</span><input name="q" type="search" maxlength="100" value="{{ request('q') }}" placeholder="{{ translate('directory_search_hint') }}"></label>
            <label><span>{{ translate('directory_type') }}</span><select name="type"><option value="">{{ translate('directory_all') }}</option>@foreach(['customer','vendor'] as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ translate($type) }}</option>@endforeach</select></label>
            <label><span>{{ translate('directory_status') }}</span><select name="status"><option value="">{{ translate('directory_all') }}</option>@foreach(['active','frozen','pending','rejected'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ translate('directory_'.$status) }}</option>@endforeach</select></label>
            <label><span>{{ translate('directory_per_page') }}</span><select name="per_page">@foreach([20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',20)===$size)>{{ $size }}</option>@endforeach</select></label>
            <input type="hidden" name="sort" value="{{ request('sort','created_at') }}"><input type="hidden" name="direction" value="{{ request('direction','desc') }}">
            <button type="submit" class="directory-button is-primary">{{ translate('Search') }}</button>
            @if(request()->hasAny(['q','type','status']))<a class="directory-reset" href="{{ route('admin.account-directory.index') }}">{{ translate('directory_reset') }}</a>@endif
        </form>
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
                        <td class="directory-person">@if($account->kind==='vendor')<div><span>{{ translate('directory_person_label') }}</span><strong>{{ $name }}</strong></div><div><span>{{ translate('directory_store_label') }}</span>{{ $account->store_name ?: translate('directory_missing') }}</div>@else<strong>{{ $name }}</strong>@endif</td>
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
        <div class="directory-pagination"><span>{{ $accounts->firstItem() ?? 0 }}–{{ $accounts->lastItem() ?? 0 }} / {{ number_format($accounts->total()) }}</span>{{ $accounts->links() }}</div>
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
@push('script')<script src="{{ dynamicAsset(path:'public/assets/back-end/js/admin-account-directory.js') }}?v=1"></script>@endpush
