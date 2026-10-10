@foreach($offlinePaymentMethods as $method)
    <details class="mt-3"><summary>{{ offlinePaymentMethodDisplayName($method) }}</summary>
        @include('customer-subscription.transfer-form',['transferMode'=>'deposit','transferAmount'=>0,'transferCurrency'=>session('currency_code','EGP'),'transferAction'=>route('customer-balances.offline')])
    </details>
@endforeach
