@php($checkoutShippingEnabled = app(\App\Services\CustomerThreeStepShippingService::class)->isEnabled())
@php($checkoutTheme = $checkoutTheme ?? theme_root_path())
<fieldset class="{{ $checkoutTheme === 'default' ? 'border-0 p-0' : 'care-shipping-options' }}" data-checkout-shipping-options data-shipping-mode="{{ $checkoutShippingEnabled ? 'sigma' : 'legacy' }}">
    <legend class="fs-16">اختر طريقة الشحن</legend>
    <p id="care-shipping-status" class="small mb-3" role="status" aria-live="polite">أكمل العنوان واختر الشحن لتحديث الإجمالي.</p>
    @if($checkoutShippingEnabled)
    <div class="{{ $checkoutTheme === 'default' ? 'd-flex flex-wrap gap-2' : 'care-shipping-grid' }}">
        @foreach(['normal' => 'شحن عادي', 'sigma' => 'شحن سيجما'] as $key => $title)
        <label class="{{ $checkoutTheme === 'default' ? 'border rounded p-3 flex-fill' : 'care-shipping-choice' }}">
            <input type="radio" name="preferred_shipping_option" value="{{ $key }}" @checked(session('checkout_shipping_option') === $key)>
            @if($checkoutTheme === 'default')
                <strong>{{ $title }}</strong>
                <small class="d-block mt-2">{{ $key === 'sigma' ? 'موثّق من سيجما، وتُضاف تكلفته إلى الفاتورة.' : 'التوصيل بالطريقة العادية المتاحة لعنوانك.' }}</small>
                <small class="d-block mt-2" data-shipping-option-price="{{ $key }}">—</small>
            @else
            <span class="care-shipping-option-body">
                <span class="care-shipping-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 6h11v11H3zM14 10h4l3 4v3h-7"/><circle cx="6.5" cy="17.5" r="2"/><circle cx="18.5" cy="17.5" r="2"/></svg></span>
                <span class="care-shipping-copy"><strong>{{ $title }}</strong><small>{{ $key === 'sigma' ? 'موثّق من سيجما، وتُضاف تكلفته إلى الفاتورة.' : 'التوصيل بالطريقة العادية المتاحة لعنوانك.' }}</small><small data-shipping-option-price="{{ $key }}">—</small></span>
                <span class="care-shipping-radio" aria-hidden="true"></span>
            </span>
            @endif
        </label>
        @endforeach
    </div>
    @else
        @php($checkoutLegacyGroups = app(\App\Services\CustomerCheckoutShippingService::class)->legacyGroups(\App\Utils\CartManager::get_cart_group_ids(type: 'checked')))
        @foreach($checkoutLegacyGroups as $groupId => $methods)
            <label class="d-block mb-3">طريقة شحن مجموعة المنتجات {{ $loop->iteration }}
                <select class="form-control fs-16" name="legacy_shipping_methods[{{ $groupId }}]" required>
                    <option value="">اختر طريقة الشحن</option>
                    @foreach($methods as $method)<option value="{{ $method->id }}">{{ $method->title }} — {{ webCurrencyConverter($method->cost) }}</option>@endforeach
                </select>
            </label>
        @endforeach
    @endif
</fieldset>
