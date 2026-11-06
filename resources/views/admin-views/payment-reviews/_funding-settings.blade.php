@php
    $canManageOffline = $canManageOffline ?? \App\Utils\Helpers::module_permission_check('3rd_party_setup');
    $fieldLabel = fn ($key) => in_array($key, ['account_holder_name','account_name','wallet_number','instapay_account','sender_name','sender_phone','instructions','bank_name','account_number','iban','payment_reference','payment_proof'], true) ? __('payments.field_'.$key) : translatePaymentText($key);
@endphp
<section class="pr-offline-settings" aria-labelledby="payments-settings-title">
    <header class="pr-settings-heading"><div><h2 id="payments-settings-title"><i class="fi fi-rr-bank" aria-hidden="true"></i>{{ __('payments.offline_title') }}</h2><p>{{ __('payments.offline_intro') }}</p></div>
        @if($canManageOffline)<div class="pr-settings-actions"><a class="btn btn--primary" href="{{ route('admin.third-party.offline-payment-method.add') }}"><i class="fi fi-rr-plus" aria-hidden="true"></i>{{ __('payments.add_method') }}</a><a class="btn btn-outline-primary" href="{{ route('admin.third-party.offline-payment-method.index') }}"><i class="fi fi-rr-settings-sliders" aria-hidden="true"></i>{{ __('payments.manage_methods') }}</a></div>@endif
    </header>
    <div class="pr-methods-summary"><span>{{ __('payments.methods_total') }} <strong>{{ $offlinePaymentMethods->count() }}</strong></span><span>{{ __('payments.active_method') }} <strong>{{ $offlinePaymentMethods->where('status',1)->count() }}</strong></span><span>{{ __('payments.inactive_method') }} <strong>{{ $offlinePaymentMethods->where('status',0)->count() }}</strong></span></div>
    <div class="pr-offline-grid">
        @forelse($offlinePaymentMethods as $method)
        @php($channel = in_array($method->payment_channel,['wallet','instapay'],true) ? $method->payment_channel : 'other')
        <article class="pr-method-card" aria-labelledby="offline-method-{{ $method->id }}">
            <header><div class="pr-method-identity"><span class="pr-method-icon"><i class="fi fi-rr-{{ ['wallet'=>'wallet','instapay'=>'exchange','other'=>'bank'][$channel] }}" aria-hidden="true"></i></span><div><h3 id="offline-method-{{ $method->id }}">{{ translatePaymentText($method->method_name) }}</h3><small>{{ __('payments.channel_'.$channel) }}</small></div></div><span class="badge badge-soft-{{ $method->status ? 'success' : 'info' }}"><i class="fi fi-rr-{{ $method->status ? 'check-circle' : 'pause-circle' }}" aria-hidden="true"></i>{{ __('payments.'.($method->status ? 'active_method' : 'inactive_method')) }}</span></header>
            <section class="pr-receiving"><h4><i class="fi fi-rr-bank" aria-hidden="true"></i>{{ __('payments.receiving_details') }}</h4><dl>
                @forelse($method->method_fields ?? [] as $field)
                <div><dt>{{ $fieldLabel($field['input_name'] ?? '') }}</dt><dd><bdi>{{ $field['input_data'] ?? '—' }}</bdi>@if(!empty($field['input_data']))<button class="pr-copy-button" type="button" data-pr-copy="{{ $field['input_data'] }}" data-copied-label="{{ __('payments.copied') }}" data-copy-failed-label="{{ __('payments.copy_failed') }}" aria-label="{{ __('payments.copy') }} {{ $fieldLabel($field['input_name'] ?? '') }}" title="{{ __('payments.copy') }}"><i class="fi fi-rr-copy" aria-hidden="true"></i></button>@endif</dd></div>
                @empty<p class="pr-muted">{{ __('payments.no_receiving_details') }}</p>@endforelse
            </dl></section>
            <section class="pr-customer-fields"><h4><i class="fi fi-rr-user" aria-hidden="true"></i>{{ __('payments.customer_fields') }}</h4><ul>
                @forelse($method->method_informations ?? [] as $field)<li><div><strong>{{ $fieldLabel($field['customer_input'] ?? '') }}</strong>@if(!empty($field['customer_placeholder']))<small>{{ $field['customer_placeholder'] }}</small>@endif</div><span>{{ __('payments.'.(!empty($field['is_required']) ? 'required_field' : 'optional_field')) }}</span></li>@empty<li class="pr-muted">{{ __('payments.no_customer_fields') }}</li>@endforelse
            </ul></section>
            @if($canManageOffline)<footer><a class="btn btn-outline-primary" href="{{ route('admin.third-party.offline-payment-method.update',['id'=>$method->id]) }}"><i class="fi fi-rr-edit" aria-hidden="true"></i>{{ __('payments.edit_method') }}</a></footer>@endif
        </article>
        @empty<div class="pr-empty"><i class="fi fi-rr-bank" aria-hidden="true"></i><h3>{{ __('payments.no_methods') }}</h3><p>{{ __('payments.no_methods_hint') }}</p></div>@endforelse
    </div>
    <p class="pr-offline-note"><i class="fi fi-rr-info" aria-hidden="true"></i>{{ __('payments.offline_policy') }}</p>
</section>
