@php($cardGroups = $vendorCardGroups ?? ['balances'=>['available','operating'],'positions'=>['pending','sales_total','pending_withdraw','withdrawn'],'deposits'=>['deposits_pending','deposits_paid','deposits_rejected','deposits_reversed'],'withdrawals'=>['withdrawals_0','withdrawals_1','withdrawals_2'],'checks'=>['missing_references','duplicate_keys']])
@foreach($cardGroups as $group=>$keys)
<section class="cf-category" aria-labelledby="vendor-group-{{ $group }}"><div class="fin-section-title"><h2 id="vendor-group-{{ $group }}">{{ __('financial-registers.groups.'.$group) }}</h2></div><p class="fin-help">{{ __('financial-registers.'.($group === 'balances' || $group === 'positions' ? 'current_scope' : 'period_scope')) }}</p>
<div class="cf-metrics">
@foreach($vendorCards as $card) @continue(!in_array($card['key'],$keys))
<div class="cf-metric"><span>{{ __('financial-registers.cards.'.$card['key']) }}</span>
    @if(array_key_exists('values',$card))<strong>@forelse($card['values'] as $value)<bdi>{{ number_format((float)$value->amount,3) }} {{ $value->currency ?: '—' }}</bdi>@empty — @endforelse</strong>
    @elseif($card['value'] !== null)<strong><bdi>{{ number_format((float)$card['value'],3) }} {{ $card['currency'] }}</bdi></strong>
    @elseif(isset($card['currency']))<strong>—</strong>
    @else<strong><bdi>{{ number_format($card['count']) }}</bdi></strong>@endif
    <small>{{ number_format($card['count']) }} {{ __('company-financials.count') }}</small>
    <a class="fin-button cf-register-button" href="{{ route('admin.report.financials.company',$card['filters']) }}">{{ __('financial-registers.open_register') }}<i class="tio-chevron-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }}" aria-hidden="true"></i></a>
</div>
@endforeach
</div></section>
@endforeach
