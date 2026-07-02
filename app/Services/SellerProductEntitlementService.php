<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\SellerPackageTransaction;
use App\Models\SellerProductEntitlement;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerProductEntitlementService
{
    public function __construct(
        private readonly SellerInsuranceService $sellerInsuranceService,
    ) {}

    public function getSummary(Seller $seller): array
    {
        $this->expireSubscription($seller);
        $subscription = $this->activeSubscriptionQuery($seller)->first();
        $insuranceSatisfied = $this->sellerInsuranceService->getSummary($seller)['active'];
        $total = $subscription ? $this->productQuotaTotal($subscription) : 0;
        $used = $subscription?->used_product_limit ?? 0;

        return [
            'insurance_satisfied' => $insuranceSatisfied,
            'active_subscription' => $subscription,
            'product_limit' => $total,
            'used_product_limit' => $used,
            'remaining_product_limit' => max(0, $total - $used),
            'can_add_product' => $insuranceSatisfied && $subscription && $used < $total,
        ];
    }

    public function assertCanReserve(Seller $seller): SellerPackageSubscription
    {
        $summary = $this->getSummary($seller);
        if (! $summary['insurance_satisfied']) {
            throw new DomainException('active_seller_insurance_is_required_before_adding_products');
        }
        if (! $summary['active_subscription']) {
            throw new DomainException('active_seller_package_is_required_before_adding_products');
        }
        if ($summary['active_subscription']->product_duration_days <= 0) {
            throw new DomainException('seller_package_product_duration_is_not_configured');
        }
        if (! $summary['can_add_product']) {
            throw new DomainException('seller_package_product_limit_has_been_reached');
        }

        return $summary['active_subscription'];
    }

    public function reserveForProduct(Product $product, Seller $seller): SellerProductEntitlement
    {
        return DB::transaction(function () use ($product, $seller) {
            Seller::query()->whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->withoutGlobalScopes()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $this->assertSellerOwnsProduct($seller, $product);

            $existing = $product->sellerProductEntitlements()
                ->whereIn('status', [
                    SellerProductEntitlement::STATUS_RESERVED,
                    SellerProductEntitlement::STATUS_ACTIVE,
                ])
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $insuranceSatisfied = $this->sellerInsuranceService->getSummary($seller)['active'];
            if (! $insuranceSatisfied) {
                throw new DomainException('active_seller_insurance_is_required_before_adding_products');
            }

            $subscription = $this->activeSubscriptionQuery($seller)->lockForUpdate()->first();
            if (! $subscription) {
                throw new DomainException('active_seller_package_is_required_before_adding_products');
            }
            if ($subscription->product_duration_days <= 0) {
                throw new DomainException('seller_package_product_duration_is_not_configured');
            }

            $total = $this->productQuotaTotal($subscription);
            if ($subscription->used_product_limit >= $total) {
                throw new DomainException('seller_package_product_limit_has_been_reached');
            }

            $subscription->increment('used_product_limit');
            $subscription->refresh();
            $entitlement = SellerProductEntitlement::create([
                'seller_id' => $seller->id,
                'product_id' => $product->id,
                'seller_package_subscription_id' => $subscription->id,
                'seller_package_id' => $subscription->seller_package_id,
                'duration_days' => $subscription->product_duration_days,
                'status' => SellerProductEntitlement::STATUS_RESERVED,
                'metadata' => ['reserved_at' => now()->toDateTimeString()],
            ]);

            $this->recordQuotaTransaction(
                $subscription,
                $product,
                $entitlement,
                SellerPackageTransaction::TYPE_QUOTA_USAGE,
                debit: 1,
                balanceAfter: max(0, $total - $subscription->used_product_limit),
                note: 'Seller product quota reserved.'
            );

            return $entitlement;
        });
    }

    public function activateForPublication(Product $product, Seller $seller): SellerProductEntitlement
    {
        return DB::transaction(function () use ($product, $seller) {
            Seller::query()->whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->withoutGlobalScopes()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $this->assertSellerOwnsProduct($seller, $product);

            if ((int) $product->request_status !== 1) {
                throw new DomainException('product_must_be_approved_before_publication');
            }
            if (! $this->sellerInsuranceService->getSummary($seller)['active']) {
                throw new DomainException('active_seller_insurance_is_required_before_publishing_products');
            }

            $entitlement = $product->sellerProductEntitlements()
                ->whereIn('status', [
                    SellerProductEntitlement::STATUS_RESERVED,
                    SellerProductEntitlement::STATUS_ACTIVE,
                ])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $entitlement) {
                $entitlement = $this->reserveForProduct($product, $seller);
            }
            if ($entitlement->status === SellerProductEntitlement::STATUS_ACTIVE) {
                if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
                    $this->expireEntitlement($entitlement, $product);
                    throw new DomainException('seller_product_publication_duration_has_expired');
                }

                return $entitlement;
            }

            $subscription = SellerPackageSubscription::query()
                ->whereKey($entitlement->seller_package_subscription_id)
                ->where('status', SellerPackageSubscription::STATUS_ACTIVE)
                ->where('payment_status', 'paid')
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->first();
            if (! $subscription) {
                $this->restoreReservedEntitlement($entitlement, 'Package expired before product publication.');
                $entitlement = $this->reserveForProduct($product, $seller);
            }

            $startsAt = now();
            $expiresAt = $startsAt->copy()->addDays($entitlement->duration_days);
            $metadata = $entitlement->metadata ?: [];
            $metadata['activated_at'] = $startsAt->toDateTimeString();
            $entitlement->update([
                'status' => SellerProductEntitlement::STATUS_ACTIVE,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'activated_at' => $startsAt,
                'metadata' => $metadata,
            ]);

            return $entitlement->fresh();
        });
    }

    public function restoreAfterRejection(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product = Product::query()->withoutGlobalScopes()->whereKey($product->id)->lockForUpdate()->first();
            if (! $product || $product->added_by !== 'seller') {
                return;
            }

            $entitlement = $product->sellerProductEntitlements()
                ->where('status', SellerProductEntitlement::STATUS_RESERVED)
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($entitlement) {
                $this->restoreReservedEntitlement($entitlement, 'Product rejected before first publication.');
            }
        });
    }

    public function cancelForDeletion(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product = Product::query()->withoutGlobalScopes()->whereKey($product->id)->lockForUpdate()->first();
            if (! $product || $product->added_by !== 'seller') {
                return;
            }

            $entitlement = $product->sellerProductEntitlements()
                ->whereIn('status', [
                    SellerProductEntitlement::STATUS_RESERVED,
                    SellerProductEntitlement::STATUS_ACTIVE,
                ])
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if (! $entitlement) {
                return;
            }
            if ($entitlement->status === SellerProductEntitlement::STATUS_RESERVED) {
                $this->restoreReservedEntitlement($entitlement, 'Unpublished product deleted.');

                return;
            }

            $entitlement->update([
                'status' => SellerProductEntitlement::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        });
    }

    public function expireDueEntitlements(?int $sellerId = null): int
    {
        $expired = 0;
        SellerProductEntitlement::query()
            ->where('status', SellerProductEntitlement::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->when($sellerId, fn ($query) => $query->where('seller_id', $sellerId))
            ->orderBy('id')
            ->chunkById(100, function ($entitlements) use (&$expired) {
                foreach ($entitlements as $entitlement) {
                    DB::transaction(function () use ($entitlement, &$expired) {
                        $locked = SellerProductEntitlement::query()->whereKey($entitlement->id)->lockForUpdate()->first();
                        if (! $locked || $locked->status !== SellerProductEntitlement::STATUS_ACTIVE
                            || ! $locked->expires_at?->isPast()) {
                            return;
                        }
                        $product = Product::query()->withoutGlobalScopes()->find($locked->product_id);
                        $this->expireEntitlement($locked, $product);
                        $expired++;
                    });
                }
            });

        if ($expired > 0) {
            cacheRemoveByType(type: 'products');
        }

        return $expired;
    }

    private function activeSubscriptionQuery(Seller $seller)
    {
        return $seller->packageSubscriptions()
            ->where('status', SellerPackageSubscription::STATUS_ACTIVE)
            ->where('payment_status', 'paid')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id');
    }

    private function expireSubscription(Seller $seller): void
    {
        $seller->packageSubscriptions()
            ->where('status', SellerPackageSubscription::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => SellerPackageSubscription::STATUS_EXPIRED]);
    }

    private function productQuotaTotal(SellerPackageSubscription $subscription): int
    {
        return max(0, $subscription->product_limit + $subscription->product_adjustment_limit);
    }

    private function restoreReservedEntitlement(SellerProductEntitlement $entitlement, string $note): void
    {
        if ($entitlement->quota_restored || $entitlement->status !== SellerProductEntitlement::STATUS_RESERVED) {
            return;
        }

        $subscription = SellerPackageSubscription::query()
            ->whereKey($entitlement->seller_package_subscription_id)
            ->lockForUpdate()
            ->first();
        if ($subscription && $subscription->used_product_limit > 0) {
            $subscription->decrement('used_product_limit');
            $subscription->refresh();
        }

        $entitlement->update([
            'status' => SellerProductEntitlement::STATUS_RESTORED,
            'quota_restored' => true,
            'quota_restored_at' => now(),
            'cancelled_at' => now(),
        ]);

        if ($subscription) {
            $this->recordQuotaTransaction(
                $subscription,
                Product::query()->withoutGlobalScopes()->findOrFail($entitlement->product_id),
                $entitlement,
                SellerPackageTransaction::TYPE_QUOTA_RESTORE,
                credit: 1,
                balanceAfter: max(0, $this->productQuotaTotal($subscription) - $subscription->used_product_limit),
                note: $note
            );
        }
    }

    private function expireEntitlement(SellerProductEntitlement $entitlement, ?Product $product): void
    {
        $entitlement->update([
            'status' => SellerProductEntitlement::STATUS_EXPIRED,
            'expired_at' => now(),
        ]);
        if ($product) {
            $product->update(['status' => 0]);
        }
    }

    private function recordQuotaTransaction(
        SellerPackageSubscription $subscription,
        Product $product,
        SellerProductEntitlement $entitlement,
        string $transactionType,
        int $credit = 0,
        int $debit = 0,
        int $balanceAfter = 0,
        string $note = ''
    ): void {
        SellerPackageTransaction::create([
            'seller_id' => $subscription->seller_id,
            'seller_package_subscription_id' => $subscription->id,
            'seller_package_id' => $subscription->seller_package_id,
            'product_id' => $product->id,
            'seller_product_entitlement_id' => $entitlement->id,
            'transaction_id' => (string) Str::uuid(),
            'transaction_type' => $transactionType,
            'quota_type' => SellerPackageTransaction::QUOTA_PRODUCT,
            'credit' => $credit,
            'debit' => $debit,
            'balance_after' => $balanceAfter,
            'paid_amount' => 0,
            'note' => $note,
        ]);
    }

    private function assertSellerOwnsProduct(Seller $seller, Product $product): void
    {
        if ($product->added_by !== 'seller' || (int) $product->user_id !== (int) $seller->id) {
            throw new DomainException('seller_product_not_found');
        }
    }
}
