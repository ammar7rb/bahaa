<?php
namespace App\Services;
use App\Models\OfflinePaymentMethod;
class CustomerOrderPaymentDisplayService
{
    public function label(?string $method, array $information = []): string
    {
        if (in_array($method, ['wallet', 'wallet_payment'], true)) return 'رصيد المحفظة';
        if ($method !== 'offline_payment') return translate($method ?? '');
        $channel = $information['payment_channel'] ?? null;
        if (!in_array($channel, ['wallet', 'instapay'], true) && !empty($information['method_id'])) {
            $channel = OfflinePaymentMethod::find($information['method_id'])?->payment_channel;
        }
        return match ($channel) {
            'wallet' => 'محفظة إلكترونية',
            'instapay' => 'إنستا باي',
            default => is_string($information['method_name'] ?? null) && trim($information['method_name']) !== ''
                ? $information['method_name'] : 'تحويل إلكتروني',
        };
    }
}
