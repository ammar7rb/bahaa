<!doctype html>
<html lang="{{ session('local','ar') }}" dir="{{ session('direction','rtl') }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ translate('directory_title') }}</title>
    <link rel="stylesheet" href="{{ dynamicAsset(path:'public/assets/backend/libs/fonts/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path:'public/assets/back-end/css/admin-account-directory-print.css') }}?v=1">
</head>
<body>
    <div class="print-controls"><button type="button" onclick="window.print()">{{ translate('directory_print') }}</button><span>{{ translate('directory_print_help') }}</span></div>
    <header><h1>{{ translate('directory_title') }}</h1><p>{{ number_format($accounts->count()) }} {{ translate('directory_matching') }} · <bdi>{{ now()->format('Y-m-d H:i') }}</bdi></p>
        @foreach(['q'=>'Search','type'=>'directory_type','status'=>'directory_status','joined_from'=>'directory_joined_from','joined_to'=>'directory_joined_to','ordered_from'=>'directory_ordered_from','ordered_to'=>'directory_ordered_to','first'=>'directory_first'] as $key=>$label)
            @if(!empty($filters[$key]))<span class="print-filter">{{ translate($label) }}: <bdi>{{ in_array($key,['type','status']) ? translate($key==='status'?'directory_'.$filters[$key]:$filters[$key]) : $filters[$key] }}</bdi></span>@endif
        @endforeach
    </header>
    <table><thead><tr><th>#</th><th>{{ translate('directory_identifier') }}</th><th>{{ translate('directory_name') }}</th><th>{{ translate('directory_type') }}</th><th>{{ translate('directory_contacts') }}</th><th>{{ translate('directory_status') }}</th></tr></thead>
        <tbody>@forelse($accounts as $account)<tr>
            <td>{{ $loop->iteration }}</td><td><bdi>{{ ($account->kind==='customer'?'c':'m').$account->id }}</bdi></td>
            <td>@if($account->kind==='vendor'){{ translate('directory_person_label') }} @endif<strong>{{ trim($account->f_name.' '.$account->l_name) ?: translate('directory_missing') }}</strong>@if($account->kind==='vendor')<div>{{ translate('directory_store_label') }} {{ $account->store_name ?: translate('directory_missing') }}</div>@endif</td>
            <td>{{ translate($account->kind) }}</td><td><div><bdi>{{ $account->email ?: translate('directory_missing') }}</bdi></div><div><bdi>{{ $account->phone ?: translate('directory_missing') }}</bdi></div>@foreach($contacts[$account->kind.':'.$account->id] ?? [] as $contact)<div><bdi>{{ $contact }}</bdi></div>@endforeach</td>
            <td>{{ translate('directory_'.$account->account_status) }}</td>
        </tr>@empty<tr><td colspan="6">{{ translate('directory_empty') }}</td></tr>@endforelse</tbody>
    </table>
</body>
</html>
