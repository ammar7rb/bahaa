<section class="customer-profile-summary" aria-label="ملخص حساب العميل">
    <div class="customer-profile-metric customer-profile-wallet"><i class="fi fi-rr-wallet" aria-hidden="true"></i><div><span>{{ translate('Wallet Balance') }}</span><strong><bdi>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $customer->wallet_balance)) }}</bdi></strong></div></div>
    <button type="button" class="customer-profile-metric" data-bs-toggle="offcanvas" data-bs-target="#savedAddressOffcanvas" aria-controls="savedAddressOffcanvas"><i class="fi fi-rr-marker" aria-hidden="true"></i><div><span>العناوين المحفوظة</span><strong data-profile-address-count>{{ $customer->addresses->count() }}</strong></div><i class="fi fi-rr-angle-small-left profile-entry-arrow" aria-hidden="true"></i></button>
    @foreach(['total_order'=>['total_orders','shopping-bag'], 'ongoing'=>['ongoing','clock'], 'completed'=>['completed','check-circle'], 'canceled'=>['canceled','cross-circle'], 'returned'=>['returned','undo'], 'failed'=>['failed','exclamation'], 'refunded'=>['refunded','receipt']] as $metricKey=>[$metricLabel,$metricIcon])
        <div class="customer-profile-metric"><i class="fi fi-rr-{{ $metricIcon }}" aria-hidden="true"></i><div><span>{{ translate($metricLabel) }}</span><strong>{{ $orderStatusArray[$metricKey] }}</strong></div></div>
    @endforeach
</section>
