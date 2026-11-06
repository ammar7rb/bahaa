@extends('layouts.vendor.app')
@section('title', translate('vendor_subscriptions'))
@section('content')
<div class="content container-fluid vendor-finance-page">
    <div class="vendor-page-intro"><h1>{{ translate('vendor_subscriptions') }}</h1><a class="btn btn-outline-primary" href="{{ route('vendor.balance.payments') }}">رصيد المدفوعات: {{ webCurrencyConverter(amount: $operatingBalance) }}</a></div>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if($summary['active_subscription'])
        <section class="card card-body mb-4"><h2>{{ $summary['active_subscription']->package_name }}</h2><p>الإعلانات المتبقية: {{ max(0, $slots->count() - (int) $summary['active_subscription']->used_product_limit) }} • الإضافات: {{ max(0, $slots->count() - (int) $summary['active_subscription']->product_limit) }} • موعد انتهاء الاشتراك {{ $summary['active_subscription']->expires_at?->format('Y-m-d H:i') }}</p></section>
    @endif
    @if($summary['active_subscription'] && $autoRenewalAvailable && !($autoRenewal->enabled ?? false))
        @php($renewalPlan = $packages->firstWhere('id', $summary['active_subscription']->seller_package_id))
        @if($renewalPlan)
            <form method="post" action="{{ route('vendor.packages.auto-renewal.enable') }}" class="card card-body mb-4">
                @csrf
                <input type="hidden" name="subscription_id" value="{{ $summary['active_subscription']->id }}">
                <input type="hidden" name="expected_price" value="{{ $renewalPlan->package_price }}">
                <label><input type="checkbox" required> التجديد التلقائي عند انتهاء الاشتراك من الرصيد النقدي بقيمة {{ webCurrencyConverter(amount: $renewalPlan->package_price) }}</label>
                <p>يتوقف التجديد ويصلك إشعار إذا لم يكفِ الرصيد أو تغيّر سعر الباقة. لإلغاء التجديد تواصل مع الإدارة.</p>
                <button class="btn btn--primary align-self-start" type="submit">تفعيل التجديد التلقائي</button>
            </form>
        @endif
    @elseif($autoRenewalAvailable && ($autoRenewal->enabled ?? false))
        <p class="alert alert-info">التجديد التلقائي مفعل. لإلغائه تواصل مع الإدارة.</p>
    @endif
    @foreach($summary['scheduled_subscriptions'] as $scheduled)
        <div class="alert alert-info">الدورة القادمة مدفوعة: {{ $scheduled->package_name }} — تبدأ {{ $scheduled->starts_at?->format('Y-m-d H:i') }}. تظل شروط الدورة الحالية كما هي حتى انتهائها.</div>
    @endforeach
    @if($summary['pending_subscription'])<div class="vendor-inline-note">{{ $summary['pending_subscription']->package_name }} — المعاملة قيد المراجعة.</div>@endif
    @include('vendor-views.package.partials.package-catalogue')
    <section class="card card-body mt-4"><h2>سجل الاشتراكات</h2><div class="table-responsive"><table class="table"><thead><tr><th>الباقة</th><th>المدفوع</th><th>الحالة</th><th>البداية</th><th>النهاية</th></tr></thead><tbody>@foreach($subscriptions as $subscription)<tr><td>{{ $subscription->package_name }}</td><td>{{ webCurrencyConverter(amount: $subscription->paid_package_price) }}</td><td>{{ translate($subscription->status) }}</td><td>{{ $subscription->starts_at?->format('Y-m-d H:i') ?? '—' }}</td><td>{{ $subscription->expires_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>@endforeach</tbody></table></div></section>
</div>
@endsection

@push('script')
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-package-payment.js') }}?v={{ filemtime(public_path('assets/back-end/js/vendor-package-payment.js')) }}" defer></script>
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-extra-ads.js') }}" defer></script>
@endpush
