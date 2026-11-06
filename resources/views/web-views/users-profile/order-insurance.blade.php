@extends('layouts.front-end.app')

@section('title', translate('Customer_Order_Insurance'))

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-4">
                        <div>
                            <h1 class="h3 mb-2">{{ translate('Customer_Order_Insurance') }}</h1>
                            <p class="text-muted mb-0">{{ translate('customer_purchase_paid_insurance_pending_notice') }}</p>
                        </div>
                        <span class="badge bg-warning text-dark fs-14">{{ translate($insurance->status) }}</span>
                    </div>

                    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><div class="rounded bg-light p-3 h-100"><small class="text-muted d-block">{{ translate('Order_ID') }}</small><strong>#{{ $order->id }}</strong></div></div>
                        <div class="col-md-4"><div class="rounded bg-light p-3 h-100"><small class="text-muted d-block">{{ translate('Purchase_Amount_Paid') }}</small><strong>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$order->order_amount)) }}</strong></div></div>
                        <div class="col-md-4"><div class="rounded bg-light p-3 h-100"><small class="text-muted d-block">{{ translate('Required_Insurance_Amount') }}</small><strong class="text-primary">{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$insurance->amount)) }}</strong></div></div>
                        <div class="col-md-6"><div class="rounded bg-light p-3 h-100"><small class="text-muted d-block">{{ translate('Payment_Deadline') }}</small><strong>{{ $insurance->payment_due_at ?: translate('Not_Set') }}</strong></div></div>
                        <div class="col-md-6"><div class="rounded bg-light p-3 h-100"><small class="text-muted d-block">{{ translate('Insurance_Balance') }}</small><strong>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$balance['available_balance'])) }}</strong></div></div>
                    </div>

                    <div class="alert alert-info">
                        {{ translate('customer_insurance_is_separate_and_used_only_for_future_insurance_after_maturity') }}
                    </div>

                    @if($insurance->payment_status === 'paid')
                        <div class="alert alert-success mb-0">{{ translate('customer_order_insurance_paid_waiting_admin_review') }}</div>
                    @elseif($insurance->status === 'pending_review')
                        <div class="alert alert-warning mb-0">{{ translate('offline_payment_submitted_and_waiting_for_admin_review') }}</div>
                    @elseif(in_array($insurance->status,['pending_payment']))
                        <div class="row g-4 mt-1">
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <h2 class="h5">{{ translate('Pay_Insurance') }}</h2>
                                    @if($balance['available_balance'] >= $insurance->amount)
                                        <form method="post" action="{{ route('account-order-insurance.pay',$order->id) }}" class="mb-3">@csrf
                                            <input type="hidden" name="payment_method" value="customer_insurance_balance">
                                            <button class="btn btn-primary w-100">{{ translate('Pay_From_Insurance_Balance') }}</button>
                                        </form>
                                    @else
                                        <div class="alert alert-warning fs-13">
                                            {{ translate('insurance_balance_is_insufficient_deposit_or_use_transfer') }}
                                            <a class="d-block mt-2 font-weight-bold" href="{{ route('wallet') }}">{{ translate('deposit_into_insurance_balance') }}</a>
                                        </div>
                                    @endif
                                    @if($digitalPayment)
                                        @foreach($paymentGateways as $gateway)
                                            @if(!empty($gateway['key_name']))
                                                <form method="post" action="{{ route('account-order-insurance.pay',$order->id) }}" class="mb-2">@csrf
                                                    <input type="hidden" name="payment_method" value="{{ $gateway['key_name'] }}">
                                                    <button class="btn btn-outline-primary w-100">{{ translate('Pay_With') }} {{ translatePaymentText($gateway['key_name']) }}</button>
                                                </form>
                                            @endif
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <h2 class="h5">{{ translate('Offline_Payment') }}</h2>
                                    @if($offlinePayment && $offlineMethods->isNotEmpty())
                                        <form method="post" enctype="multipart/form-data" action="{{ route('account-order-insurance.offline',$order->id) }}">@csrf
                                            <div class="row g-2 mb-3">
                                                @foreach($offlineMethods as $method)
                                                    <div class="col-sm-6"><label class="border rounded p-3 d-flex align-items-center gap-2 cursor-pointer h-100 w-100">
                                                        <input type="radio" name="method_id" value="{{ $method->id }}" required>
                                                        <i class="{{ $method->payment_channel === 'wallet' ? 'tio-wallet' : 'tio-send' }} text-primary fs-20"></i>
                                                        <strong>{{ $method->payment_channel === 'wallet' ? translate('pay_with_electronic_wallet') : translate('pay_with_instapay') }}</strong>
                                                    </label></div>
                                                @endforeach
                                            </div>
                                            <div class="mb-3"><input class="form-control" type="file" name="payment_proof" accept="image/*" required></div>
                                            <div class="mb-3"><textarea class="form-control" name="payment_note" placeholder="{{ translate('Payment_Note') }}"></textarea></div>
                                            <button class="btn btn-outline-primary w-100">{{ translate('Submit_For_Review') }}</button>
                                        </form>
                                    @else
                                        <p class="text-muted mb-0">{{ translate('offline_payment_method_not_found') }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if($insurance->payment_status !== 'paid')
                <div class="row g-3">
                    <div class="col-lg-7"><div class="card h-100"><div class="card-body">
                        <h2 class="h5">{{ translate('Need_Help_With_Insurance') }}</h2>
                        <form method="post" action="{{ route('account-order-insurance.support',$order->id) }}">@csrf
                            <textarea class="form-control mb-3" name="message" rows="3" placeholder="{{ translate('Describe_Insurance_Problem') }}"></textarea>
                            <button class="btn btn-outline-primary">{{ translate('Contact_Technical_Support') }}</button>
                        </form>
                    </div></div></div>
                    <div class="col-lg-5"><div class="card h-100"><div class="card-body">
                        <h2 class="h5">{{ translate('Do_Not_Want_To_Pay_Insurance') }}</h2>
                        <p class="text-muted">{{ translate('declining_insurance_cancels_order_and_schedules_purchase_refund') }}</p>
                        <form method="post" action="{{ route('account-order-insurance.decline',$order->id) }}">@csrf
                            <textarea class="form-control mb-3" name="reason" rows="2" required></textarea>
                            <button class="btn btn-outline-danger" onclick="return confirm('{{ translate('confirm_decline_insurance') }}')">{{ translate('Decline_And_Request_Purchase_Refund') }}</button>
                        </form>
                    </div></div></div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
