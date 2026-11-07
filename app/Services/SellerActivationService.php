<?php

namespace App\Services;

use App\Models\AccountActivationCase;
use App\Models\BusinessSetting;
use App\Models\Seller;
use App\Models\SellerActivationTicket;
use App\Models\SellerActivationTicketMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SellerActivationService
{
    public const SETTING_AUTOMATIC_MESSAGE = 'seller_activation_automatic_message';
    public const AUTOMATIC_MESSAGE = 'Our support team is reviewing your seller account activation request.';

    public function automaticMessage(): string
    {
        try {
            return (string) (BusinessSetting::query()
                ->where('type', self::SETTING_AUTOMATIC_MESSAGE)
                ->value('value') ?: translate('seller_activation_automatic_message_default'));
        } catch (Throwable) {
            return self::AUTOMATIC_MESSAGE;
        }
    }

    public function openRegistrationTicket(Seller $seller): ?SellerActivationTicket
    {
        return null;
    }

    private function safeTranslate(string $key, string $fallback): string
    {
        try {
            return (string) translate($key);
        } catch (Throwable) {
            return $fallback;
        }
    }
}
