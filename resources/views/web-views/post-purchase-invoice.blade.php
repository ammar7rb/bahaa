@extends('layouts.front-end.app')

@section('title', 'الفاتورة الثانية للطلب')

@section('content')
    <div class="container py-4 py-md-5 second-stage-page text-align-direction">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3 p-md-4">
                        <div class="second-stage-icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg></div>
                        @if(session('checkout_pending_notice'))
                            <div class="alert alert-success">{{ translate('checkout_request_received_pending') }}</div>
                        @endif
                        <h4 class="mb-2">الفاتورة الثانية للطلب #{{ $invoice->order_id }}</h4>
                        <p class="text-muted mb-4">تضم هذه الفاتورة ضريبة المنتجات وتأمين الطلب حسب الإعدادات المفعّلة. اختر طريقة السداد بعد اعتماد الدفعة الأولى.</p>

                        <div class="alert alert-warning" @if($summary['insurance_amount'] <= 0) hidden @endif>
                            رصيد التأمين يُستخدم لسداد التأمين فقط. تُسدد الضريبة بإنستا باي أو محفظة إلكترونية.
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-borderless align-middle mb-0">
                                <tr><th>{{ translate('balance_order_number') }}</th><td>#{{ $invoice->order_id }}</td></tr>
                                <tr><th>مبلغ المشتريات والشحن في الفاتورة الأولى</th><td>{{ webCurrencyConverter($summary['first_payment_amount'] ?? 0) }}</td></tr>
                                <tr><th>{{ translate('tax') }}</th><td>{{ webCurrencyConverter($summary['tax_amount']) }}</td></tr>
                                <tr><th>{{ translate('order_insurance') }}</th><td>{{ webCurrencyConverter($summary['insurance_amount']) }}</td></tr>
                                <tr><th>إجمالي الفاتورة الثانية</th><td class="fw-bold">{{ webCurrencyConverter($summary['total_amount']) }}</td></tr>
                                <tr><th>المتبقي للسداد</th><td class="fw-bold">{{ webCurrencyConverter($summary['external_amount_due']) }}</td></tr>
                                @if($invoice->payment_due_at)<tr><th>آخر موعد للسداد</th><td>{{ $invoice->payment_due_at->format('d M Y, h:i A') }}</td></tr>@endif
                            </table>
                        </div>

                        @if($invoice->status === \App\Models\PostPurchaseInvoice::STATUS_PAID)
                            <div class="alert alert-success mb-0">{{ translate('payment_received_order_waiting_for_admin_review') }}</div>
                        @elseif($invoice->status === \App\Models\PostPurchaseInvoice::STATUS_AWAITING_REVIEW)
                            <div class="alert alert-info mb-0">{{ translate('offline_payment_is_waiting_for_admin_review') }}</div>
                        @elseif($invoice->status === \App\Models\PostPurchaseInvoice::STATUS_EXPIRED)
                            <div class="alert alert-danger mb-0">{{ translate('payment_window_has_expired_contact_support') }}</div>
                        @elseif(!$invoice->isPayable())
                            <div class="alert alert-info mb-0">الفاتورة الثانية جاهزة بالمبالغ أعلاه. بعد اعتماد الدفعة الأولى ستختار سداد التأمين من رصيده المستحق، أو سداد الفاتورة بإنستا باي أو محفظة إلكترونية.</div>
                        @else
                            <div class="d-flex flex-column gap-3">
                                <h5 class="mb-0">اختر طريقة سداد الفاتورة الثانية</h5>
                                @if(($summary['insurance_amount'] ?? 0) > ($summary['insurance_balance_paid'] ?? 0))
                                    @php($insuranceStillDue = max(0, $summary['insurance_amount'] - $summary['insurance_balance_paid']))
                                    <p class="mb-1">رصيد التأمين المستحق المتاح: {{ webCurrencyConverter($insuranceBalance['available_balance'] ?? 0) }}</p>
                                    @if(($insuranceBalance['available_balance'] ?? 0) < $insuranceStillDue)
                                        <div class="alert alert-warning">{{ translate('insurance_balance_is_insufficient_deposit_or_use_transfer') }} <a href="{{ route('wallet') }}">{{ translate('deposit_into_insurance_balance') }}</a></div>
                                    @endif
                                    <form method="post" action="{{ route('post-purchase-invoices.use-insurance-balance', $invoice->id) }}">
                                        @csrf
                                        <button class="btn btn-outline-primary" type="submit" @disabled(($insuranceBalance['available_balance'] ?? 0) < $insuranceStillDue)>{{ translate('use_insurance_balance_for_insurance_only') }}</button>
                                    </form>
                                @endif

                                @if($summary['external_amount_due'] > 0 && $digitalPaymentAvailable)
                                    <form method="post" action="{{ route('post-purchase-invoices.pay-digital', $invoice->id) }}" class="row g-2">
                                        @csrf
                                        <div class="col-md-8">
                                            <select class="form-select" name="payment_method" required>
                                                <option value="">{{ translate('select_payment_method') }}</option>
                                                @foreach($paymentGatewayList as $gateway)
                                                    <option value="{{ $gateway['key_name'] }}">{{ $gateway['key_name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4"><button class="btn btn--primary w-100" type="submit">{{ translate('pay_now') }}</button></div>
                                    </form>
                                @endif

                                @if($summary['external_amount_due'] > 0 && $offlinePaymentAvailable && $offlinePaymentMethods->isNotEmpty())
                                    <details class="border rounded p-3">
                                        <summary class="cursor-pointer fw-semibold">إنستا باي أو محفظة إلكترونية — اختر طريقة التحويل وارفع إثبات الدفع</summary>
                                        @foreach($offlinePaymentMethods as $method)
                                            <details class="border rounded p-3 mt-3">
                                                <summary>{{ $method->payment_channel === 'instapay' ? 'إنستا باي' : 'محفظة إلكترونية' }} — {{ translatePaymentText($method->method_name) }}</summary>
                                                @foreach($method->method_fields ?? [] as $field)
                                                    <p class="small mt-2 mb-0">{{ translatePaymentText($field['input_name'] ?? '') }}: <bdi>{{ $field['input_data'] ?? '' }}</bdi></p>
                                                @endforeach
                                            </details>
                                        @endforeach
                                        <form method="post" enctype="multipart/form-data" action="{{ route('post-purchase-invoices.offline-payment', $invoice->id) }}" class="row g-2 mt-2">
                                            @csrf
                                            <div class="col-md-6"><select class="form-select" name="method_id" required>@foreach($offlinePaymentMethods as $method)<option value="{{ $method->id }}">{{ $method->method_name }}</option>@endforeach</select></div>
                                            <div class="col-md-6"><input class="form-control" type="file" name="payment_proof" accept="image/*" required></div>
                                            <div class="col-12"><textarea class="form-control" name="payment_note" placeholder="{{ translate('note') }}"></textarea></div>
                                            <div class="col-12"><button class="btn btn-outline-primary" type="submit">{{ translate('submit_for_review') }}</button></div>
                                        </form>
                                    </details>
                                @endif

                                <a class="btn btn-link px-0 text-start" href="{{ route('account-tickets') }}">{{ translate('need_help_contact_support') }}</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
