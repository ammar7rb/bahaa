<?php

namespace App\Services;

use App\Models\PhoneOrEmailVerification;
use App\Models\Seller;
use App\Utils\SMSModule;
use Illuminate\Support\Facades\DB;
use Throwable;

class SellerRegistrationVerificationService
{
    private const OTP_TTL_MINUTES = 10;
    private const DEFAULT_MAX_ATTEMPTS = 5;
    private const DEFAULT_RESEND_SECONDS = 60;
    private const DEFAULT_BLOCK_SECONDS = 600;

    public function __construct(
        private readonly FirebaseService $firebaseService,
    ) {
    }

    public function findByReference(?string $reference): ?Seller
    {
        if (!$reference) {
            return null;
        }

        return Seller::where('registration_reference', $reference)->first();
    }

    public function isPhoneVerified(Seller $seller): bool
    {
        return $seller->phone_verified_at !== null || $seller->registration_reference === null;
    }

    public function requiresPhoneVerification(Seller $seller): bool
    {
        return !$this->isPhoneVerified($seller);
    }

    public function getEligibility(Seller $seller): array
    {
        $phoneVerified = $this->isPhoneVerified($seller);
        $accountStatus = $seller->status;

        $nextStep = match (true) {
            !$phoneVerified => 'verify_phone',
            $accountStatus === 'pending' => 'await_admin_approval',
            $accountStatus === 'approved' => 'ready_for_insurance',
            default => 'account_' . $accountStatus,
        };

        return [
            'phone_verified' => $phoneVerified,
            'phone_verified_at' => $seller->phone_verified_at?->toISOString(),
            'account_status' => $accountStatus,
            'can_login' => $phoneVerified && $accountStatus === 'approved',
            'next_step' => $nextStep,
        ];
    }

    public function sendOtp(Seller $seller, ?string $firebaseSessionInfo = null): array
    {
        if ($this->isPhoneVerified($seller)) {
            return [
                'status' => true,
                'code' => 'seller_phone_already_verified',
                'message' => translate('verification_done_successfully'),
                'resend_after' => 0,
            ];
        }

        $identity = $this->verificationIdentity($seller);
        $verification = PhoneOrEmailVerification::where('phone_or_email', $identity)->first();
        $resendAfter = $this->getResendAfter($verification);

        if ($resendAfter > 0) {
            return [
                'status' => false,
                'code' => 'otp_resend_wait',
                'message' => translate('please_try_again_after_') . $resendAfter . ' ' . translate('seconds'),
                'resend_after' => $resendAfter,
            ];
        }

        $deliveryMethod = 'sms';
        $token = env('APP_MODE') === 'live' ? (string) random_int(100000, 999999) : '123456';

        if ($this->isFirebaseEnabled()) {
            $deliveryMethod = $firebaseSessionInfo ? 'firebase_client' : 'firebase';

            if ($firebaseSessionInfo) {
                $token = $firebaseSessionInfo;
            } else {
                try {
                    $firebaseResponse = $this->firebaseService->sendOtp($seller->phone);
                } catch (Throwable) {
                    $firebaseResponse = ['status' => 'error', 'errors' => 'OTP_send_failed'];
                }

                if (($firebaseResponse['status'] ?? 'error') !== 'success') {
                    return [
                        'status' => false,
                        'code' => 'otp_send_failed',
                        'message' => translate(strtolower($firebaseResponse['errors'] ?? 'OTP_send_failed')),
                        'resend_after' => 0,
                    ];
                }

                $token = $firebaseResponse['sessionInfo'];
            }
        } else {
            $smsResponse = SMSModule::sendCentralizedSMS($seller->phone, $token);
            if (env('APP_MODE') !== 'live') {
                $smsResponse = 'success';
            }

            if ($smsResponse !== 'success') {
                return [
                    'status' => false,
                    'code' => 'otp_send_failed',
                    'message' => translate('something_went_wrong.') . ' ' . translate('please_try_again_after_sometime'),
                    'resend_after' => 0,
                ];
            }
        }

        PhoneOrEmailVerification::updateOrCreate(
            ['phone_or_email' => $identity],
            [
                'token' => $token,
                'otp_hit_count' => 0,
                'is_temp_blocked' => 0,
                'temp_block_time' => null,
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return [
            'status' => true,
            'code' => 'seller_registration_otp_sent',
            'message' => translate('OTP_sent_successfully'),
            'delivery_method' => $deliveryMethod,
            'resend_after' => $this->resendSeconds(),
            'expires_in' => self::OTP_TTL_MINUTES * 60,
        ];
    }

    public function verifyOtp(Seller $seller, string $otp): array
    {
        if ($this->isPhoneVerified($seller)) {
            return [
                'status' => true,
                'code' => 'seller_phone_already_verified',
                'message' => translate('verification_done_successfully'),
                'eligibility' => $this->getEligibility($seller),
            ];
        }

        $identity = $this->verificationIdentity($seller);
        $verification = PhoneOrEmailVerification::where('phone_or_email', $identity)->first();

        if (!$verification) {
            return $this->failedVerification('otp_not_requested', translate('OTP_is_not_matched'));
        }

        if ($verification->expires_at && $verification->expires_at->isPast()) {
            $verification->delete();
            return $this->failedVerification('otp_expired', translate('OTP_is_not_matched'));
        }

        $blockSeconds = $this->blockSeconds();
        if ($verification->is_temp_blocked && $verification->temp_block_time) {
            $blockedUntil = $verification->temp_block_time->copy()->addSeconds($blockSeconds);
            if ($blockedUntil->isFuture()) {
                return $this->failedVerification(
                    'otp_temp_blocked',
                    translate('please_try_again_after_') . now()->diffInSeconds($blockedUntil) . ' ' . translate('seconds')
                );
            }

            $verification->update([
                'otp_hit_count' => 0,
                'is_temp_blocked' => 0,
                'temp_block_time' => null,
            ]);
        }

        $verified = false;
        if (strlen((string) $verification->token) > 6) {
            try {
                $firebaseResponse = $this->firebaseService->verifyOtp($verification->token, $seller->phone, $otp);
                $verified = ($firebaseResponse['status'] ?? 'error') === 'success';
            } catch (Throwable) {
                $verified = false;
            }
        } else {
            $verified = hash_equals((string) $verification->token, $otp);
        }

        if (!$verified) {
            $attempts = $verification->otp_hit_count + 1;
            $blocked = $attempts >= $this->maxAttempts();
            $verification->update([
                'otp_hit_count' => $attempts,
                'is_temp_blocked' => $blocked,
                'temp_block_time' => $blocked ? now() : null,
            ]);

            return $this->failedVerification(
                $blocked ? 'otp_temp_blocked' : 'invalid_otp',
                $blocked ? translate('Too_many_attempts.') : translate('OTP_is_not_matched')
            );
        }

        DB::transaction(function () use ($seller, $verification) {
            $seller->update(['phone_verified_at' => now()]);
            $verification->delete();
        });

        $seller->refresh();

        return [
            'status' => true,
            'code' => 'seller_phone_verified',
            'message' => translate('verification_done_successfully'),
            'eligibility' => $this->getEligibility($seller),
        ];
    }

    public function isFirebaseEnabled(): bool
    {
        $setting = getWebConfig(name: 'firebase_otp_verification') ?? [];
        return is_array($setting) && (int) ($setting['status'] ?? 0) === 1;
    }

    public function getMaskedPhone(Seller $seller): string
    {
        $phone = (string) $seller->phone;
        if (strlen($phone) <= 4) {
            return $phone;
        }

        return str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
    }

    private function verificationIdentity(Seller $seller): string
    {
        return 'seller_registration:' . $seller->id . ':' . $seller->phone;
    }

    private function getResendAfter(?PhoneOrEmailVerification $verification): int
    {
        if (!$verification?->created_at) {
            return 0;
        }

        $availableAt = $verification->created_at->copy()->addSeconds($this->resendSeconds());
        return $availableAt->isFuture() ? (int) now()->diffInSeconds($availableAt) : 0;
    }

    private function maxAttempts(): int
    {
        $configured = (int) (getWebConfig(name: 'maximum_otp_hit') ?? 0);
        return $configured > 0 ? $configured : self::DEFAULT_MAX_ATTEMPTS;
    }

    private function resendSeconds(): int
    {
        $configured = (int) (getWebConfig(name: 'otp_resend_time') ?? 0);
        return $configured > 0 ? $configured : self::DEFAULT_RESEND_SECONDS;
    }

    private function blockSeconds(): int
    {
        $configured = (int) (getWebConfig(name: 'temporary_block_time') ?? 0);
        return $configured > 0 ? $configured : self::DEFAULT_BLOCK_SECONDS;
    }

    private function failedVerification(string $code, string $message): array
    {
        return [
            'status' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}
