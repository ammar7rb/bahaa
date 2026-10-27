<?php

namespace App\Services\Web;

use App\Utils\Helpers;
use App\Utils\SMSModule;
use App\Events\EmailVerificationEvent;
use Exception;


class CustomerAuthService
{
    public function getCustomerVerificationToken(): string
    {
        return (env('APP_MODE') == 'live') ? rand(100000, 999999) : 123456;
    }

    public function getCustomerLoginDataReset(): array
    {
        return [
            'login_hit_count' => 0,
            'is_temp_blocked' => 0,
            'temp_block_time' => null,
            'updated_at' => now()
        ];
    }


    public function sendCustomerPhoneVerificationToken($phone, $token): array
    {
        $response = SMSModule::sendCentralizedSMS($phone, $token);
        return [
            'response' => $response,
            'status' => 'success',
            'message' => translate('please_check_your_SMS_for_OTP'),
        ];
    }

    public function sendCustomerEmailVerificationToken($user, $token): array
    {
        $emailServicesSmtp = getWebConfig(name: 'mail_config');
        if ($emailServicesSmtp['status'] == 0) {
            $emailServicesSmtp = getWebConfig(name: 'mail_config_sendgrid');
        }
        if ($emailServicesSmtp['status'] == 1 && $user['email']) {
            try {
                $data = [
                    'userName' => $user['f_name'],
                    'subject' => translate('registration_Verification_Code'),
                    'title' => translate('registration_Verification_Code'),
                    'verificationCode' => $token,
                    'userType' => 'customer',
                    'templateName' => 'registration-verification',
                ];

                event(new EmailVerificationEvent(email: $user['email'], data: $data));
                return [
                    'status' => 'success',
                    'message' => translate('check_your_email'),
                ];
            } catch (Exception $exception) {
                return [
                    'status' => 'error',
                    'message' => translate('email_is_not_configured') . '. ' . translate('contact_with_the_administrator'),
                ];
            }
        } else {
            return [
                'status' => 'error',
                'message' => translate('email_failed'),
            ];
        }
    }

    public function getCustomerRegisterData(object|array $request, object|array|null $referUser): array
    {
        return [
            'name' => $request['f_name'] . ' ' . $request['l_name'],
            'f_name' => $request['f_name'],
            'l_name' => $request['l_name'],
            'email' => $request['email'],
            'phone' => $request['phone'],
            'is_active' => 1,
            'activation_status' => 'pending_support',
            'onboarding_journey' => 'standard',
            'canonical_phone' => app(\App\Services\EgyptPhoneService::class)->normalizeLocal($request['phone']),
            'password' => bcrypt($request['password']),
            'referral_code' => Helpers::generate_referer_code(),
            'referred_by' => $referUser ? $referUser['id'] : null,
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
        ];
    }

    public function storeCustomerAuthReturnURL(): void
    {
        $historyUrls = array_values(array_filter(
            session('recent_user_routes_history', []),
            fn ($url) => $this->isCustomerAuthReturnURL($url)
        ));
        if (!empty($historyUrls)) {
            $lastUrl = end($historyUrls);
            session()->put('keep_customer_login_redirect_url', $lastUrl);
        } else {
            session()->forget('keep_customer_login_redirect_url');
        }
    }

    public function getCustomerAuthReturnURL(): string
    {
        if (session('checkout_login_return')) return route('checkout-details');
        $keepReturnUrl = route('home');
        if ($this->isCustomerAuthReturnURL(session('keep_customer_login_redirect_url'))) {
            $keepReturnUrl = session('keep_customer_login_redirect_url');
        }
        return $keepReturnUrl;
    }

    private function isCustomerAuthReturnURL(mixed $url): bool
    {
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $home = parse_url(route('home'));
        if (isset($parts['user']) || isset($parts['pass']) ||
            ($parts['scheme'] ?? null) !== ($home['scheme'] ?? null) ||
            ($parts['host'] ?? null) !== ($home['host'] ?? null) ||
            ($parts['port'] ?? null) !== ($home['port'] ?? null)) {
            return false;
        }

        return rtrim($parts['path'] ?? '', '/') !==
            rtrim(parse_url(route('customer.communication.index'), PHP_URL_PATH), '/');
    }

}
