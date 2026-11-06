<?php

namespace App\Traits;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use App\Models\PaymentRequest;

trait Payment
{
    public static function generate_link(object $payer, object $payment_info, object $receiver): Application|bool|string|UrlGenerator|\Illuminate\Contracts\Foundation\Application
    {
        $operationId = $payment_info->getAdditionalData()['customer_checkout_operation_id'] ?? null;
        if (!$operationId) return self::generateCustomerSafePaymentLink($payer, $payment_info, $receiver);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($payer, $payment_info, $receiver, $operationId) {
            \App\Models\User::lockForUpdate()->findOrFail($payment_info->getPayerId());
            $operation = \Illuminate\Support\Facades\DB::table('customer_checkout_operations')->where('id', $operationId)
                ->where('customer_id', $payment_info->getPayerId())->lockForUpdate()->first();
            if (!$operation || $operation->consumed_at) throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'تم تقديم عملية الشراء بالفعل. تابع طلبك من صفحة الطلبات.']);
            return self::generateCustomerSafePaymentLink($payer, $payment_info, $receiver);
        }, 3);
    }

    private static function generateCustomerSafePaymentLink(object $payer, object $payment_info, object $receiver): Application|bool|string|UrlGenerator|\Illuminate\Contracts\Foundation\Application
    {
        if ($payment_info->getPaymentAmount() <= 0) {
            throw new InvalidArgumentException(translate('Payment amount can not be 0'));
        }

        if (!is_array($payment_info->getAdditionalData())) {
            throw new InvalidArgumentException(translate('Additional data should be in a valid array'));
        }

        $operationId = $payment_info->getAdditionalData()['customer_checkout_operation_id'] ?? null;
        $existing = $operationId ? PaymentRequest::where('payer_id', $payment_info->getPayerId())
            ->where('additional_data->customer_checkout_operation_id', $operationId)->first() : null;
        if ($existing && $existing->is_paid) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'تم تأكيد الدفع بالفعل. تابع الطلب أو تواصل مع الدعم؛ لا تدفع مرة أخرى.']);
        }
        if ($existing && $existing->payment_method !== $payment_info->getPaymentMethod()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'تابع عملية الدفع المحفوظة باستخدام نفس طريقة الدفع.']);
        }
        $payment = $existing ?? new PaymentRequest();
        if (!$existing) {
        $payment->payment_amount = $payment_info->getPaymentAmount();
        $payment->success_hook = $payment_info->getSuccessHook();
        $payment->failure_hook = $payment_info->getFailureHook();
        $payment->payer_id = $payment_info->getPayerId();
        $payment->receiver_id = $payment_info->getReceiverId();
        $payment->currency_code = strtoupper($payment_info->getCurrencyCode());
        $payment->payment_method = $payment_info->getPaymentMethod();
        $payment->additional_data = json_encode($payment_info->getAdditionalData());
        $payment->payer_information = json_encode($payer->information());
        $payment->receiver_information = json_encode($receiver->information());
        $payment->external_redirect_link = $payment_info->getExternalRedirectLink();
        $payment->attribute = $payment_info->getAttribute();
        $payment->attribute_id = $payment_info->getAttributeId();
        $payment->payment_platform = $payment_info->getPaymentPlatForm();
        $payment->save();
        }

        $routes = [
            'ssl_commerz' => 'payment/sslcommerz/pay',
            'stripe' => 'payment/stripe/pay',
            'paymob_accept' => 'payment/paymob/pay',
            'flutterwave' => 'payment/flutterwave-v3/pay',
            'paytm' => 'payment/paytm/pay',
            'paypal' => 'payment/paypal/pay',
            'paytabs' => 'payment/paytabs/pay',
            'liqpay' => 'payment/liqpay/pay',
            'razor_pay' => 'payment/razor-pay/pay',
            'senang_pay' => 'payment/senang-pay/pay',
            'mercadopago' => 'payment/mercadopago/pay',
            'bkash' => 'payment/bkash/make-payment',
            'paystack' => 'payment/paystack/pay',
            'fatoorah' => 'payment/fatoorah/pay',
            'xendit' => 'payment/xendit/pay',
            'amazon_pay' => 'payment/amazon/pay',
            'iyzi_pay' => 'payment/iyzipay/pay',
            'hyper_pay' => 'payment/hyperpay/pay',
            'foloosi' => 'payment/foloosi/pay',
            'ccavenue' => 'payment/ccavenue/pay',
            'pvit' => 'payment/pvit/pay',
            'moncash' => 'payment/moncash/pay',
            'thawani' => 'payment/thawani/pay',
            'tap' => 'payment/tap/pay',
            'viva_wallet' => 'payment/viva/pay',
            'hubtel' => 'payment/hubtel/pay',
            'maxicash' => 'payment/maxicash/pay',
            'esewa' => 'payment/esewa/pay',
            'swish' => 'payment/swish/pay',
            'momo' => 'payment/momo/pay',
            'payfast' => 'payment/payfast/pay',
            'worldpay' => 'payment/worldpay/pay',
            'sixcash' => 'payment/sixcash/pay',
            'phonepe' => 'payment/phonepe/pay',
            'cashfree' => 'payment/cashfree/pay',
            'instamojo' => 'payment/instamojo/pay',
            'mercadopago_pix' => 'payment/mercadopago_pix/pay',
        ];
        if (array_key_exists($payment->payment_method, $routes)) {
            return url("{$routes[$payment->payment_method]}/?payment_id={$payment->id}");
        } else {
            return false;
        }
    }
}
