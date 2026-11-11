<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestOrderConversionService
{
    public function convert(int $orderId, string $accountReference): Order
    {
        $parsed = AdministrativeReferenceService::parse($accountReference);
        if (!$parsed || $parsed['type'] !== 'users') {
            throw ValidationException::withMessages(['account' => 'اكتب رقم حساب العميل مثل C000123.']);
        }

        return DB::transaction(function () use ($orderId, $parsed) {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            if ((int) $order->is_guest !== 1) {
                throw ValidationException::withMessages(['order' => 'هذا الطلب ليس طلب زائر.']);
            }
            $customer = User::query()->whereKey($parsed['id'])->whereNull('account_deleted_at')->first();
            if (!$customer || !$customer->is_active) {
                throw ValidationException::withMessages(['account' => 'حساب العميل غير موجود أو غير نشط.']);
            }
            $order->customer_id = $customer->id;
            $order->is_guest = 0;
            $order->save();

            return $order->fresh();
        });
    }
}
