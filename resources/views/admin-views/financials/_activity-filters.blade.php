<form method="get" action="{{ $action }}" class="fin-filters fa-filters" @if($statement ?? false) data-statement-form @else data-cf-form @endif>
    @unless($statement ?? false)<input type="hidden" name="tab" value="incoming">@endunless
    <div class="fin-filter-grid">
        <label>{{ __('financial-activity.search') }}<input name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}"></label>
        @if($statement ?? false)
            <label>{{ __('financials.from') }}<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label>{{ __('financials.to') }}<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        @else
            <label>{{ __('company-financials.account_type') }}<select name="account_type"><option value="">{{ __('financials.all') }}</option>@foreach(['customer','vendor'] as $type)<option value="{{ $type }}" @selected(($filters['account_type'] ?? '') === $type)>{{ __('company-financials.'.$type) }}</option>@endforeach</select></label>
            <label>{{ __('financial-activity.payment_state') }}<select name="confirmation"><option value="">{{ __('financials.all') }}</option><option value="confirmed" @selected(($filters['confirmation'] ?? '') === 'confirmed')>{{ __('financial-activity.confirmed') }}</option><option value="unconfirmed" @selected(($filters['confirmation'] ?? '') === 'unconfirmed')>{{ __('financial-activity.unconfirmed') }}</option></select></label>
        @endif
        <label>{{ __('financial-activity.category') }}<select name="category"><option value="">{{ __('financials.all') }}</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ __('financial-activity.categories.'.$category) }}</option>@endforeach</select></label>
    </div>
    @unless($statement ?? false)
    <details class="fa-advanced" @if(!empty($filters['from']) || !empty($filters['to']) || !empty($filters['account_id']) || !empty($filters['method']) || !empty($filters['movement'])) open @endif><summary>{{ __('financial-activity.more_filters') }}<i class="tio-chevron-down" aria-hidden="true"></i></summary><div class="fin-filter-grid">
        <label>{{ __('financials.from') }}<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label><label>{{ __('financials.to') }}<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label>{{ __('financial-activity.account_id') }}<input type="number" name="account_id" min="1" value="{{ $filters['account_id'] ?? '' }}"></label>
        <label>{{ __('financial-activity.movement') }}<select name="movement"><option value="">{{ __('financials.all') }}</option>@foreach(['external','internal','vendor_collected','unclassified','adjustment'] as $movement)<option value="{{ $movement }}" @selected(($filters['movement'] ?? '') === $movement)>{{ __('financial-activity.movements.'.$movement) }}</option>@endforeach</select></label>
        <label>{{ __('financials.method') }}<select name="method"><option value="">{{ __('financials.all') }}</option>@foreach($methods as $method)<option value="{{ $method }}" @selected(($filters['method'] ?? '') === $method)>{{ $financialLabel($method) }}</option>@endforeach</select></label>
        <label>{{ __('financials.per_page') }}<select name="per_page">@foreach([20,50,100] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 20) == $size)>{{ $size }}</option>@endforeach</select></label>
    </div></details>
    @endunless
    <div class="fin-filter-actions"><button class="fin-button fin-primary">{{ __('financials.apply') }}</button><a class="fin-button" @if($statement ?? false) data-statement-reset @endif href="{{ $action }}">{{ __('financials.reset') }}</a><span class="cf-load fin-help" role="status" hidden>{{ __('company-financials.loading') }}</span></div>
    <p class="fin-help fa-period-note">{{ __('financial-activity.period_note') }}</p>
</form>
