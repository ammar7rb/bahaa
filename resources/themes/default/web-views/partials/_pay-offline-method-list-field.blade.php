@if($method)
    <link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-transfer.css') }}?v=20261010.3">
    <input type="hidden" name="payment_method" value="offline_payment">
    @include('customer-subscription.transfer-form',['nested'=>true,'transferMode'=>'order','transferAmount'=>(float) webCurrencyConverterOnlyDigit($totalOfflineAmount),'transferCurrency'=>session('currency_code','EGP'),'transferAction'=>''])
@endif
