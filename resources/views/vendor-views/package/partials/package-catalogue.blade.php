<section class="vendor-package-catalogue" aria-labelledby="vendor-packages-title">
    <header class="vendor-package-heading"><h2 id="vendor-packages-title">الباقات المتاحة</h2></header>
    <div class="vendor-package-grid">
    @forelse($packages as $package)
        @php($isCurrent = $summary['active_subscription']?->seller_package_id === $package->id)
        @php($isSelected = $summary['pending_subscription']?->seller_package_id === $package->id)
        @php($duration = app(\App\Services\SubscriptionDurationService::class)->fromPackage($package))
        @php($actionLabel = $isCurrent ? 'تجديد الآن' : ($summary['active_subscription'] ? ((int) $package->product_limit > (int) $summary['active_subscription']->product_limit ? 'ترقية الباقة' : 'تغيير الباقة') : 'اشترك الآن'))
        <article class="vendor-package-choice {{ $isCurrent ? 'vendor-package-current' : '' }} {{ $isSelected ? 'vendor-package-selected' : '' }}">
            <div class="vendor-package-card-top"><h3>{{ $package->name }}</h3>@if($isCurrent)<span class="vendor-package-badge">الباقة الحالية</span>@elseif($isSelected)<span class="vendor-package-badge">الباقة المختارة</span>@endif</div>
            @if($package->description)<p class="vendor-package-description">{{ $package->description }}</p>@endif
            <div class="vendor-package-price">{{ webCurrencyConverter(amount: $package->package_price) }}</div><p class="vendor-package-price-label">سعر الباقة</p>
            <dl class="vendor-package-facts"><div><dt>عدد الإعلانات</dt><dd>{{ $package->product_limit }} إعلانات</dd></div><div><dt>مدة الاشتراك</dt><dd>{{ $duration['value'] }} {{ translate($duration['unit']) }}</dd></div></dl>
            <div class="vendor-package-payment">
                <button type="button" class="btn btn--primary w-100" data-package-payment-open="package-payment-{{ $package->id }}" @disabled($summary['pending_review'])>{{ $actionLabel }}</button>
                @include('vendor-views.package.partials.payment-dialog')
            </div>
        </article>
    @empty<p class="vendor-package-empty">لا توجد باقات متاحة حاليًا.</p>@endforelse
    </div>
</section>
