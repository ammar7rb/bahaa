<div class="mb-3" data-funding-intent>
    <label for="funding-kind-{{ $method->id }}" class="form-label">بعد اعتماد الشحن</label>
    <select id="funding-kind-{{ $method->id }}" name="purchase_kind" class="form-control" data-purchase-kind>
        <option value="none">إضافة الرصيد فقط</option>
        <option value="package">شراء أو تجديد باقة أختارها</option>
        @if(($fundingChoices['extra_settings']['enabled'] ?? false) && ($fundingChoices['subscription'] ?? null))<option value="extra">شراء مساحات إضافية للدورة الحالية</option>@endif
    </select>
    <fieldset class="mt-3" data-purchase-package hidden disabled>
        <label for="funding-plan-{{ $method->id }}" class="form-label">الباقة والسعر المثبت</label>
        <select id="funding-plan-{{ $method->id }}" name="package_id" class="form-control" data-funding-package required>
            @foreach($fundingChoices['packages'] ?? [] as $choice)<option value="{{ $choice['id'] }}" data-price="{{ $choice['package_price'] }}">{{ $choice['name'] }} — {{ $choice['product_limit'] }} إعلانات — {{ $choice['package_price'] }} EGP</option>@endforeach
        </select>
        <input type="hidden" name="expected_purchase_amount" data-purchase-amount>
        <p>يحفظ الطلب السعر والشروط الآن. عند اعتماد الشحن يُنفذ الشراء من operating. التجديد يبدأ بعد الدورات المدفوعة الحالية.</p>
    </fieldset>
    <fieldset class="mt-3" data-purchase-extra hidden disabled>
        <input type="hidden" name="subscription_id" value="{{ $fundingChoices['subscription']['id'] ?? '' }}">
        <input type="hidden" name="expected_unit_price" value="{{ $fundingChoices['extra_settings']['unit_price'] ?? '' }}">
        <label class="form-label">عدد المساحات الإضافية<input type="number" class="form-control" name="quantity" value="1" min="1" max="{{ $fundingChoices['extra_settings']['max_slots'] ?? 100 }}"></label>
        <p>سعر الإعلان {{ $fundingChoices['extra_settings']['unit_price'] ?? '—' }} EGP، وينتهي مع الاشتراك. إذا انتهى قبل اعتماد التحويل يبقى المال معلقًا لتسوية يدوية دون رصيد أو إعلانات.</p>
    </fieldset>
    <p class="mt-2">الزيادة تبقى في رصيد المدفوعات. إذا لم يكفِ الرصيد، يظل الشراء معلقًا حتى تضغط إعادة المحاولة بعد استكماله. الشحن لا يفوض تجديدًا دوريًا.</p>
</div>
