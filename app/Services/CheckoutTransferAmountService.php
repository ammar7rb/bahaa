<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
class CheckoutTransferAmountService
{
    /** Use the same per-vendor amount snapshotted by OrderManager, including its shipping. */
    public function total(iterable $groups): float
    {
        $total = 0.0;
        foreach ($groups as $group) $total += (float) ($group['first_payment_amount'] ?? 0);
        return round($total, 3);
    }
    public function assertSubmitted(mixed $submitted, float $expected): void
    {
        if (!is_scalar($submitted) || !is_numeric($submitted) || !is_finite((float) $submitted)
            || $expected <= 0 || (int) round((float) $submitted * 1000) !== (int) round($expected * 1000)) {
            throw ValidationException::withMessages(['transferred_amount' => 'تغيّر إجمالي الطلب أو الشحن. أعد فتح وسيلة الدفع وحوّل الإجمالي الظاهر شامل الشحن.']);
        }
    }
    /** Allocation per order avoids repeating the whole basket transfer for every vendor. */
    public function snapshot(array $information, float $orderTotal, float $shippingCost): array
    {
        if (!empty($information['method_id'])) {
            $channel = \App\Models\OfflinePaymentMethod::find($information['method_id'])?->payment_channel;
            if (in_array($channel, ['wallet', 'instapay'], true)) $information['payment_channel'] = $channel;
        }
        $information['transferred_amount'] = number_format($orderTotal, 3, '.', '');
        $information['order_total'] = number_format($orderTotal, 3, '.', '');
        $information['shipping_cost'] = number_format($shippingCost, 3, '.', '');
        return $information;
    }
}
