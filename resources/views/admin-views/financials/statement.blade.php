@php($financialLabel=static fn($value)=>$value === null || $value === '' ? '—' : (\Illuminate\Support\Facades\Lang::has('financials.labels.'.$value) ? __('financials.labels.'.$value) : $value))
<div data-financial-statement data-account-name="{{ trim($account->f_name.' '.$account->l_name) }}">
    <section class="fa-statement-summary" aria-label="{{ __('financial-activity.account_summary') }}">
        <div><span>{{ __('company-financials.account_type') }}</span><strong>{{ __('company-financials.'.$account->account_type) }} <bdi>#{{ $account->id }}</bdi></strong></div>
        <div><span>{{ __('financial-activity.wallet') }}</span><strong><bdi>{{ $account->wallet_balance === null ? '—' : number_format((float)$account->wallet_balance,3).' '.$baseCurrency }}</bdi></strong></div>
        @if($account->payments_balance !== null)<div><span>{{ __('financial-activity.payments_wallet') }}</span><strong><bdi>{{ number_format((float)$account->payments_balance,3) }} {{ $baseCurrency }}</bdi></strong></div>@endif
        <div><span>{{ __('financial-activity.package') }}</span><strong>{{ $account->package_name ?: __('financial-activity.no_package') }}</strong>@if($account->package_status)<small>{{ $financialLabel($account->package_status) }}</small>@endif<small><bdi>{{ $account->expires_at ?: '—' }}</bdi></small></div>
    </section>
    <p class="fin-help fa-context-note">{{ __('financial-activity.statement_note') }}</p>
    @include('admin-views.financials._activity-filters')
    @include('admin-views.financials._activity-table')
</div>
