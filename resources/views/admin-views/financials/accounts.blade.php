@extends('layouts.admin.app')
@section('title', __('financials.customers'))
@include('admin-views.financials._setup')
@push('css_or_js')<link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/financial-activity.css') }}?v=20261022.4">@endpush
@section('content')
@php($accountLabel=static fn($value)=>\Illuminate\Support\Facades\Lang::has('financials.labels.'.$value) ? __('financials.labels.'.$value) : ($value ?: '—'))
<div class="content container-fluid financials financial-accounts" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-financial-accounts data-date-error="{{ __('financial-activity.date_error') }}" @if($openStatement) data-open-statement="{{ $openStatement }}" @endif>
    <header class="fin-heading"><div><h1>{{ __('financials.customers') }}</h1><p>{{ __('financial-activity.accounts_intro') }}</p></div><span class="fin-readonly"><i class="tio-lock-outlined" aria-hidden="true"></i>{{ __('financials.read_only') }}</span></header>
    @include('admin-views.financials._navigation')
    @if($errors->any())<p class="fin-notice" role="alert">{{ $errors->first() }}</p>@endif
    <form method="get" class="fin-filters" action="{{ route('admin.report.financials.customers') }}"><div class="fin-filter-grid">
        <label>{{ __('financial-activity.account_search') }}<input name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}"></label>
        <label>{{ __('company-financials.account_type') }}<select name="account_type"><option value="">{{ __('financials.all') }}</option>@foreach(['customer','vendor'] as $type)<option value="{{ $type }}" @selected(($filters['account_type'] ?? '') === $type)>{{ __('company-financials.'.$type) }}</option>@endforeach</select></label>
        <label>{{ __('financial-activity.account_id') }}<input type="number" name="account_id" min="1" value="{{ $filters['account_id'] ?? '' }}"></label>
        <label>{{ __('financials.per_page') }}<select name="per_page">@foreach([20,50,100] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 20) == $size)>{{ $size }}</option>@endforeach</select></label>
    </div><div class="fin-filter-actions"><button class="fin-button fin-primary">{{ __('financials.apply') }}</button><a class="fin-button" href="{{ route('admin.report.financials.customers') }}">{{ __('financials.reset') }}</a></div></form>
    <section class="fin-panel fa-register"><div class="fin-panel-heading"><h2>{{ __('financial-activity.accounts') }}</h2><span>{{ number_format($accounts->total()) }} {{ __('financials.results') }}</span></div>
    <div class="fin-table-scroll" role="region" tabindex="0" aria-label="{{ __('financial-activity.accounts') }}"><table class="fin-table fa-account-table"><thead><tr><th scope="col">{{ __('financials.party') }}</th><th scope="col">{{ __('financial-activity.wallet') }}</th><th scope="col">{{ __('financial-activity.payments_wallet') }}</th><th scope="col">{{ __('financial-activity.package') }}</th><th scope="col">{{ __('financials.expiry') }}</th><th scope="col">{{ __('financials.customer_statement') }}</th></tr></thead><tbody>
    @forelse($accounts as $account)<tr>
        <td><strong>{{ trim($account->f_name.' '.$account->l_name) ?: '#'.$account->id }}</strong><small>{{ __('company-financials.'.$account->account_type) }} <bdi>#{{ $account->id }}</bdi></small>@if($account->account_type === 'customer' || \App\Utils\Helpers::module_permission_check('people'))<small><bdi>{{ $account->phone ?: '—' }}</bdi></small>@endif</td>
        <td class="fin-number"><bdi>{{ $account->wallet_balance === null ? '—' : number_format((float)$account->wallet_balance,3) }}</bdi><small>{{ $baseCurrency }}</small></td>
        <td class="fin-number"><bdi>{{ $account->payments_balance === null ? '—' : number_format((float)$account->payments_balance,3) }}</bdi>@if($account->payments_balance !== null)<small>{{ $baseCurrency }}</small>@endif</td>
        <td><strong>{{ $account->package_name ?: __('financial-activity.no_package') }}</strong>@if($account->package_status)<small><span class="fin-status" data-tone="{{ $account->package_status === 'active' ? 'success' : 'neutral' }}">{{ $accountLabel($account->package_status) }}</span></small>@endif @if($account->remaining !== null)<small>{{ __('financials.remaining') }}: <bdi>{{ number_format($account->remaining,3) }}</bdi> — {{ __('financial-activity.credit_not_cash') }}</small>@endif</td>
        <td><bdi>{{ $account->expires_at ? substr($account->expires_at,0,10) : '—' }}</bdi></td>
        <td><a class="fa-statement-trigger" data-statement-open data-account-name="{{ trim($account->f_name.' '.$account->l_name) }}" href="{{ route('admin.report.financials.statement',[$account->account_type,$account->id]) }}" aria-label="{{ __('financials.customer_statement') }} — {{ trim($account->f_name.' '.$account->l_name) }}" title="{{ __('financials.customer_statement') }}"><i class="tio-document-text" aria-hidden="true"></i><span class="fa-sr-only">{{ __('financials.customer_statement') }}</span></a></td>
    </tr>@empty<tr><td colspan="6" class="fin-empty"><strong>{{ __('financials.empty') }}</strong><span>{{ __('financials.empty_help') }}</span></td></tr>@endforelse
    </tbody></table></div><div class="fin-pagination">{{ $accounts->links() }}</div></section>
    <p class="fin-help fa-context-note">{{ __('financial-activity.directory_note') }}</p>
    <dialog class="fa-dialog" aria-labelledby="fa-statement-title" data-statement-dialog>
        <header class="fa-dialog-heading"><div><h2 id="fa-statement-title">{{ __('financials.customer_statement') }}</h2><p data-statement-owner></p></div><button type="button" class="fa-dialog-close" data-statement-close aria-label="{{ __('financial-activity.close') }}"><i class="tio-clear" aria-hidden="true"></i></button></header>
        <div class="fa-dialog-content" data-statement-content aria-live="polite"></div>
    </dialog>
    <template data-statement-loading><div class="fa-dialog-state" role="status"><span class="fa-loading-indicator" aria-hidden="true"></span>{{ __('company-financials.loading') }}</div></template>
    <template data-statement-error><div class="fa-dialog-state" role="alert"><p>{{ __('financial-activity.load_error') }}</p><button type="button" class="fin-button" data-statement-retry>{{ __('financial-activity.retry') }}</button></div></template>
</div>
@endsection
@push('script')<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/financial-accounts.js') }}?v=20261022.4" defer></script>@endpush
