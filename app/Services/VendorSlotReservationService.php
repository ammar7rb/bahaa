<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\VendorAdvertisingSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorSlotReservationService
{
    public function enabled(): bool { return (bool) config('vendor_advertising.creation_slots_enabled'); }

    public function reserve(Seller $seller, int $slotId, string $token, ?string $previousToken = null): VendorAdvertisingSlot
    {
        return DB::transaction(function () use ($seller, $slotId, $token, $previousToken) {
            app(VendorAdvertisingAccessService::class)->assertCanCreate((int) $seller->id);
            $slot = $this->lockedSlot($seller->id, $slotId);
            $this->assertCurrent($slot);
            $hash = hash('sha256', $token);
            if ($slot->product_id !== null || ($this->isReserved($slot) && !hash_equals($slot->reservation_token_hash, $hash)
                && ($previousToken === null || !hash_equals($slot->reservation_token_hash, hash('sha256', $previousToken))))) {
                $this->fail('المساحة مستخدمة أو محجوزة في نموذج آخر. اختر مساحة متاحة.');
            }
            $slot->forceFill(['reservation_token_hash' => $hash, 'reserved_at' => $slot->reserved_at ?? now(),
                'reservation_expires_at' => $this->expiresAt($slot)])->save();
            return $slot;
        });
    }

    public function isReserved(VendorAdvertisingSlot $slot): bool
    {
        return $slot->reservation_token_hash !== null && (bool) $slot->reservation_expires_at?->gt(now());
    }

    public function cancel(Seller $seller, int $slotId, ?string $token = null): void
    {
        DB::transaction(function () use ($seller, $slotId, $token) {
            $slot = $this->lockedSlot($seller->id, $slotId);
            if ($token !== null && $slot->reservation_token_hash !== hash('sha256', $token)) return;
            $slot->forceFill($this->emptyReservation())->save();
        });
    }

    public function assertCreation(int $sellerId): void
    {
        if (!$this->enabled() || !request()->hasAny(['slot_id', 'slot_reservation_token'])) return;
        if (DB::transactionLevel() < 1) $this->fail('يلزم إنشاء المنتج من نموذج مساحة محجوزة.');
        $slot = $this->lockedSlot($sellerId, (int) request('slot_id'));
        $this->assertCurrent($slot);
        $token = (string) request('slot_reservation_token');
        if (!$this->isReserved($slot) || $slot->product_id !== null || $token === ''
            || !hash_equals($slot->reservation_token_hash, hash('sha256', $token))) {
            $this->fail('حجز المساحة غير صالح. ارجع إلى المساحات وافتح النموذج مجددًا.');
        }
    }

    public function attachCreated(Product $product): void
    {
        if (!$this->enabled() || $product->added_by !== 'seller' || !request()->hasAny(['slot_id', 'slot_reservation_token'])) return;
        $this->assertCreation((int) $product->user_id);
        $slot = $this->lockedSlot((int) $product->user_id, (int) request('slot_id'));
        app(VendorAdvertisingSlotService::class)->assign(Seller::findOrFail($product->user_id), $slot->id, $product->id, true);
        DB::table('vendor_slot_creation_receipts')->insert(['seller_id' => $product->user_id, 'slot_id' => $slot->id,
            'token_hash' => hash('sha256', (string) request('slot_reservation_token')), 'product_id' => $product->id, 'created_at' => now()]);
    }

    public function receipt(int $sellerId, int $slotId, string $token): ?object
    {
        if ($token === '') return null;
        return DB::table('vendor_slot_creation_receipts')->where('seller_id', $sellerId)->where('slot_id', $slotId)
            ->where('token_hash', hash('sha256', $token))->first();
    }

    public function emptyReservation(): array
    {
        return ['reservation_token_hash' => null, 'reserved_at' => null, 'reservation_expires_at' => null];
    }

    public function lockedSlot(int $sellerId, int $slotId): VendorAdvertisingSlot
    {
        Seller::whereKey($sellerId)->lockForUpdate()->firstOrFail();
        return VendorAdvertisingSlot::where('seller_id', $sellerId)->whereKey($slotId)->lockForUpdate()->firstOrFail();
    }

    public function assertCurrent(VendorAdvertisingSlot $slot): void
    {
        $subscription = SellerPackageSubscription::where('seller_id', $slot->seller_id)
            ->whereKey($slot->seller_package_subscription_id)->lockForUpdate()->firstOrFail();
        if ($subscription->payment_status !== 'paid' || !in_array($subscription->status, ['active', 'scheduled'], true)
            || !$subscription->starts_at?->lte(now()) || !$subscription->expires_at?->gt(now()) || !$slot->editable) {
            $this->fail('يلزم اشتراك ومساحة ساريان. انتهت صلاحية هذه المساحة أو لم تبدأ بعد.');
        }
    }

    private function expiresAt(VendorAdvertisingSlot $slot): mixed
    {
        $minutes = (int) config('vendor_advertising.reservation_idle_minutes', 0);
        return $minutes > 0 ? now()->addMinutes($minutes)->min($slot->expires_at) : $slot->expires_at;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['advertising_slot' => $message]);
    }
}
