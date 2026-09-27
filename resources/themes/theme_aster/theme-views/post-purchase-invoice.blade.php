@extends('theme-views.layouts.app')

@section('title', translate('complete_order_requirements'))

@section('content')
    <main class="main-content py-4 mb-4">
        <div class="container"><div class="row justify-content-center"><div class="col-lg-8">
            <div class="card"><div class="card-body p-3 p-md-4">
                <h4>{{ translate('complete_order_requirements') }}</h4>
                <p class="text-muted">{{ translate('your_merchandise_payment_has_been_received_the_order_is_waiting_for_tax_and_insurance_payment') }}</p>
                @if($summary['insurance_amount'] > 0)<div class="alert alert-warning">{{ translate('insurance_balance_can_only_be_used_for_the_insurance_part_and_never_for_tax') }}</div>@endif
                <div class="d-flex justify-content-between border-bottom py-2"><span>{{ translate('order') }}</span><strong>#{{ $invoice->order_id }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>{{ translate('merchandise_payment') }}</span><strong>{{ webCurrencyConverter($summary['first_payment_amount'] ?? 0) }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>{{ translate('tax') }}</span><strong>{{ webCurrencyConverter($summary['tax_amount']) }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>{{ translate('order_insurance') }}</span><strong>{{ webCurrencyConverter($summary['insurance_amount']) }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>إجمالي الفاتورة الثانية</span><strong>{{ webCurrencyConverter($summary['total_amount']) }}</strong></div>
                <div class="d-flex justify-content-between py-3"><span>{{ translate('total_due') }}</span><strong>{{ webCurrencyConverter($summary['external_amount_due']) }}</strong></div>
                @if($invoice->status === \App\Models\PostPurchaseInvoice::STATUS_PAID)
                    <div class="alert alert-success mb-0">{{ translate('payment_received_order_waiting_for_admin_review') }}</div>
                @else
                    @if(($summary['insurance_amount'] ?? 0) > ($summary['insurance_balance_paid'] ?? 0))
                        @php($insuranceStillDue = max(0, $summary['insurance_amount'] - $summary['insurance_balance_paid']))
                        <p>رصيد التأمين المستحق المتاح: {{ webCurrencyConverter($insuranceBalance['available_balance'] ?? 0) }}</p>
                        @if(($insuranceBalance['available_balance'] ?? 0) < $insuranceStillDue)<div class="alert alert-warning">{{ translate('insurance_balance_is_insufficient_deposit_or_use_transfer') }} <a href="{{ route('wallet') }}">{{ translate('deposit_into_insurance_balance') }}</a></div>@endif
                        <form method="post" action="{{ route('post-purchase-invoices.use-insurance-balance', $invoice->id) }}">@csrf<button class="btn btn-outline-primary mb-2" type="submit" @disabled(($insuranceBalance['available_balance'] ?? 0) < $insuranceStillDue)>{{ translate('use_insurance_balance_for_insurance_only') }}</button></form>
                    @endif
                    @if($summary['external_amount_due'] > 0 && $digitalPaymentAvailable)<form method="post" action="{{ route('post-purchase-invoices.pay-digital', $invoice->id) }}" class="d-flex gap-2 mb-2">@csrf<select class="form-select" name="payment_method" required><option value="">{{ translate('select_payment_method') }}</option>@foreach($paymentGatewayList as $gateway)<option value="{{ $gateway['key_name'] }}">{{ $gateway['key_name'] }}</option>@endforeach</select><button class="btn btn-primary" type="submit">{{ translate('pay_now') }}</button></form>@endif
                    @if($summary['external_amount_due'] > 0 && $offlinePaymentAvailable && $offlinePaymentMethods->isNotEmpty())
                        <details class="border rounded p-3 mb-3"><summary>الدفع بإنستا باي أو محفظة إلكترونية</summary>
                            @foreach($offlinePaymentMethods as $method)<div class="border rounded p-2 mt-2"><strong>{{ $method->payment_channel === 'instapay' ? 'إنستا باي' : 'محفظة إلكترونية' }} — {{ translatePaymentText($method->method_name) }}</strong>@foreach($method->method_fields ?? [] as $field)<div>{{ translatePaymentText($field['input_name'] ?? '') }}: <bdi>{{ $field['input_data'] ?? '' }}</bdi></div>@endforeach</div>@endforeach
                            <form method="post" enctype="multipart/form-data" action="{{ route('post-purchase-invoices.offline-payment', $invoice->id) }}" class="row g-2 mt-2">@csrf<div class="col-md-6"><select class="form-select" name="method_id" required>@foreach($offlinePaymentMethods as $method)<option value="{{ $method->id }}">{{ $method->payment_channel === 'instapay' ? 'إنستا باي' : 'محفظة إلكترونية' }} — {{ $method->method_name }}</option>@endforeach</select></div><div class="col-md-6"><input class="form-control" type="file" name="payment_proof" accept="image/*" required></div><div class="col-12"><textarea class="form-control" name="payment_note" placeholder="{{ translate('note') }}"></textarea></div><div class="col-12"><button class="btn btn-outline-primary" type="submit">{{ translate('submit_for_review') }}</button></div></form>
                        </details>
                    @endif
                    <a href="{{ route('account-tickets') }}">{{ translate('need_help_contact_support') }}</a>
                @endif
            </div></div>
        </div></div></div>
    </main>
@endsection
