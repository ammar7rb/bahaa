<?php

namespace App\Services;

use App\Models\Seller;
use App\Models\SellerPackage;
use App\Models\SellerPackageSubscription;
use App\Models\SellerPackageTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerPackagePurchaseService
{
    public function __construct(
        private readonly SellerInsuranceService $sellerInsuranceService,
    ) {}

    public function getSummary(Seller $seller): array
    {
        $this->expireCurrentSubscription($seller);
        $activeSubscription = $seller->packageSubscriptions()
            ->where('status', SellerPackageSubscription::STATUS_ACTIVE)
            ->where('payment_status', 'paid')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
        $pendingSubscription = $seller->packageSubscriptions()
            ->where('payment_status', 'unpaid')
            ->whereIn('status', [
                SellerPackageSubscription::STATUS_PENDING,
                SellerPackageSubscription::STATUS_PENDING_REVIEW,
            ])
            ->latest('id')
            ->first();
        $insuranceSummary = $this->sellerInsuranceService->getSummary($seller);

        return [
            'insurance_satisfied' => $insuranceSummary['active'],
            'insurance_summary' => $insuranceSummary,
            'active_subscription' => $activeSubscription,
            'pending_subscription' => $pendingSubscription,
            'pending_review' => $pendingSubscription?->status === SellerPackageSubscription::STATUS_PENDING_REVIEW,
            'can_purchase' => $insuranceSummary['active'] && ! $pendingSubscription,
        ];
    }

    public function getOrCreatePendingSubscription(Seller $seller, SellerPackage $package): SellerPackageSubscription
    {
        return DB::transaction(function () use ($seller, $package) {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->id);
            $package = SellerPackage::query()->whereKey($package->id)->where('status', true)->first();
            if (! $package) {
                throw new DomainException('seller_package_not_found_or_inactive');
            }

            $insuranceSummary = $this->sellerInsuranceService->getSummary($seller);
            if (! $insuranceSummary['active']) {
                throw new DomainException('active_seller_insurance_is_required_before_buying_a_package');
            }

            if ((float) $package->package_price <= 0
                || $package->product_limit <= 0
                || $package->product_duration_days <= 0) {
                throw new DomainException('seller_package_configuration_is_invalid');
            }

            $pendingSubscription = $seller->packageSubscriptions()
                ->where('payment_status', 'unpaid')
                ->whereIn('status', [
                    SellerPackageSubscription::STATUS_PENDING,
                    SellerPackageSubscription::STATUS_PENDING_REVIEW,
                ])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($pendingSubscription) {
                if ($pendingSubscription->status === SellerPackageSubscription::STATUS_PENDING_REVIEW) {
                    throw new DomainException('seller_package_payment_is_pending_admin_review');
                }
                if ((int) $pendingSubscription->seller_package_id !== (int) $package->id) {
                    throw new DomainException('another_seller_package_payment_is_already_pending');
                }

                return $pendingSubscription;
            }

            $snapshot = $this->packageSnapshot($package);

            return SellerPackageSubscription::create(array_merge($snapshot, [
                'seller_id' => $seller->id,
                'seller_package_id' => $package->id,
                'status' => SellerPackageSubscription::STATUS_PENDING,
                'payment_status' => 'unpaid',
                'metadata' => [
                    'created_from' => 'seller_package_purchase',
                    'package_snapshot' => $snapshot,
                ],
            ]));
        });
    }

    public function attachPaymentRequest(SellerPackageSubscription $subscription, string $paymentRequestId): SellerPackageSubscription
    {
        return DB::transaction(function () use ($subscription, $paymentRequestId) {
            $subscription = SellerPackageSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($subscription->payment_status === 'unpaid'
                && $subscription->status === SellerPackageSubscription::STATUS_PENDING) {
                $subscription->update(['payment_request_id' => $paymentRequestId]);
            }

            return $subscription->fresh();
        });
    }

    public function submitOfflinePayment(SellerPackageSubscription $subscription, array $offlinePayment): SellerPackageSubscription
    {
        return DB::transaction(function () use ($subscription, $offlinePayment) {
            $subscription = SellerPackageSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($subscription->payment_status !== 'unpaid'
                || $subscription->status !== SellerPackageSubscription::STATUS_PENDING) {
                throw new DomainException('seller_package_is_not_available_for_offline_payment');
            }

            $metadata = $subscription->metadata ?: [];
            $metadata['offline_payment'] = $offlinePayment;
            $subscription->update([
                'status' => SellerPackageSubscription::STATUS_PENDING_REVIEW,
                'payment_method' => 'offline_payment',
                'payment_reference' => 'offline-review:'.($offlinePayment['method_id'] ?? 'manual'),
                'metadata' => $metadata,
            ]);

            return $subscription->fresh();
        });
    }

    public function markPaid(
        SellerPackageSubscription $subscription,
        array $paymentData,
        ?int $reviewedByAdminId = null,
        ?string $reviewNote = null
    ): array {
        return DB::transaction(function () use ($subscription, $paymentData, $reviewedByAdminId, $reviewNote) {
            Seller::query()->whereKey($subscription->seller_id)->lockForUpdate()->first();
            $subscription = SellerPackageSubscription::query()->lockForUpdate()->find($subscription->id);
            if (! $subscription) {
                return ['status' => 0, 'message' => 'seller_package_subscription_not_found'];
            }

            if ($subscription->payment_status === 'paid'
                && $subscription->status === SellerPackageSubscription::STATUS_ACTIVE) {
                return ['status' => 1, 'message' => 'already_paid', 'subscription' => $subscription];
            }

            if ($subscription->payment_status !== 'unpaid'
                || ! in_array($subscription->status, [
                    SellerPackageSubscription::STATUS_PENDING,
                    SellerPackageSubscription::STATUS_PENDING_REVIEW,
                ], true)) {
                return ['status' => 0, 'message' => 'seller_package_subscription_cannot_be_activated'];
            }

            if ((float) $subscription->paid_package_price <= 0
                || $subscription->product_limit <= 0
                || $subscription->product_duration_days <= 0) {
                return ['status' => 0, 'message' => 'seller_package_subscription_snapshot_is_invalid'];
            }

            SellerPackageSubscription::query()
                ->where('seller_id', $subscription->seller_id)
                ->where('id', '!=', $subscription->id)
                ->where('status', SellerPackageSubscription::STATUS_ACTIVE)
                ->update([
                    'status' => SellerPackageSubscription::STATUS_REPLACED,
                    'cancelled_at' => now(),
                ]);

            $paymentRequestId = $paymentData['id'] ?? $subscription->payment_request_id;
            $startsAt = now();
            $expiresAt = $subscription->package_validity_days
                ? $startsAt->copy()->addDays($subscription->package_validity_days)
                : null;
            $metadata = $subscription->metadata ?: [];
            $metadata['payment'] = [
                'payment_request_id' => $paymentRequestId,
                'payment_method' => $paymentData['payment_method'] ?? $subscription->payment_method,
                'transaction_id' => $paymentData['transaction_id'] ?? null,
                'payment_amount' => $paymentData['payment_amount'] ?? $subscription->paid_package_price,
                'currency_code' => $paymentData['currency_code'] ?? null,
                'paid_at' => $startsAt->toDateTimeString(),
            ];
            if ($reviewedByAdminId) {
                $metadata['offline_payment_review'] = [
                    'action' => 'approved',
                    'reviewed_by_admin_id' => $reviewedByAdminId,
                    'reviewed_at' => $startsAt->toDateTimeString(),
                    'note' => $reviewNote,
                ];
            }

            $subscription->update([
                'status' => SellerPackageSubscription::STATUS_ACTIVE,
                'payment_status' => 'paid',
                'payment_method' => $paymentData['payment_method'] ?? $subscription->payment_method,
                'payment_reference' => $paymentData['transaction_id'] ?? $subscription->payment_reference,
                'payment_request_id' => $paymentRequestId,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'activated_at' => $startsAt,
                'metadata' => $metadata,
            ]);

            $this->createActivationTransactions($subscription->fresh(), $paymentData, $reviewedByAdminId);

            return [
                'status' => 1,
                'message' => 'paid',
                'subscription' => $subscription->fresh(),
            ];
        });
    }

    public function rejectOfflinePayment(
        SellerPackageSubscription $subscription,
        int $reviewedByAdminId,
        ?string $reviewNote = null
    ): SellerPackageSubscription {
        return DB::transaction(function () use ($subscription, $reviewedByAdminId, $reviewNote) {
            $subscription = SellerPackageSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($subscription->payment_status !== 'unpaid'
                || $subscription->status !== SellerPackageSubscription::STATUS_PENDING_REVIEW) {
                throw new DomainException('seller_package_offline_review_not_found');
            }

            $metadata = $subscription->metadata ?: [];
            $metadata['offline_payment_review'] = [
                'action' => 'rejected',
                'reviewed_by_admin_id' => $reviewedByAdminId,
                'reviewed_at' => now()->toDateTimeString(),
                'note' => $reviewNote,
            ];
            $subscription->update([
                'status' => SellerPackageSubscription::STATUS_REJECTED,
                'cancelled_at' => now(),
                'metadata' => $metadata,
            ]);

            return $subscription->fresh();
        });
    }

    public function recordPaymentFailure(SellerPackageSubscription $subscription, array $paymentData): void
    {
        DB::transaction(function () use ($subscription, $paymentData) {
            $subscription = SellerPackageSubscription::query()->lockForUpdate()->find($subscription->id);
            if (! $subscription || $subscription->payment_status === 'paid') {
                return;
            }

            $metadata = $subscription->metadata ?: [];
            $metadata['last_payment_failure'] = [
                'payment_request_id' => $paymentData['id'] ?? null,
                'payment_method' => $paymentData['payment_method'] ?? null,
                'failed_at' => now()->toDateTimeString(),
            ];
            $subscription->update(['metadata' => $metadata]);
        });
    }

    private function packageSnapshot(SellerPackage $package): array
    {
        return [
            'package_name' => $package->name,
            'paid_package_price' => (float) $package->package_price,
            'product_limit' => $package->product_limit,
            'used_product_limit' => 0,
            'product_adjustment_limit' => 0,
            'product_duration_days' => $package->product_duration_days,
            'search_promotion_limit' => $package->search_promotion_limit,
            'used_search_promotion_limit' => 0,
            'search_promotion_adjustment_limit' => 0,
            'search_promotion_duration_days' => $package->search_promotion_duration_days,
            'homepage_promotion_limit' => $package->homepage_promotion_limit,
            'used_homepage_promotion_limit' => 0,
            'homepage_promotion_adjustment_limit' => 0,
            'homepage_promotion_duration_days' => $package->homepage_promotion_duration_days,
            'package_validity_days' => $package->package_validity_days,
        ];
    }

    private function createActivationTransactions(
        SellerPackageSubscription $subscription,
        array $paymentData,
        ?int $createdByAdminId
    ): void {
        $baseData = [
            'seller_id' => $subscription->seller_id,
            'seller_package_subscription_id' => $subscription->id,
            'seller_package_id' => $subscription->seller_package_id,
            'payment_request_id' => $paymentData['id'] ?? $subscription->payment_request_id,
            'reference' => $paymentData['transaction_id'] ?? $subscription->payment_reference,
            'created_by_admin_id' => $createdByAdminId,
            'metadata' => ['package_name' => $subscription->package_name],
        ];

        SellerPackageTransaction::create(array_merge($baseData, [
            'transaction_id' => (string) Str::uuid(),
            'transaction_type' => 'package_purchase',
            'paid_amount' => $subscription->paid_package_price,
            'note' => 'Seller package payment completed.',
        ]));

        $quotas = [
            SellerPackageTransaction::QUOTA_PRODUCT => $subscription->product_limit,
            SellerPackageTransaction::QUOTA_SEARCH_PROMOTION => $subscription->search_promotion_limit,
            SellerPackageTransaction::QUOTA_HOMEPAGE_PROMOTION => $subscription->homepage_promotion_limit,
        ];
        foreach ($quotas as $quotaType => $amount) {
            if ($amount <= 0) {
                continue;
            }
            SellerPackageTransaction::create(array_merge($baseData, [
                'transaction_id' => (string) Str::uuid(),
                'transaction_type' => 'quota_grant',
                'quota_type' => $quotaType,
                'credit' => $amount,
                'balance_after' => $amount,
                'paid_amount' => 0,
                'note' => 'Initial seller package quota granted.',
            ]));
        }
    }

    private function expireCurrentSubscription(Seller $seller): void
    {
        $seller->packageSubscriptions()
            ->where('status', SellerPackageSubscription::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => SellerPackageSubscription::STATUS_EXPIRED]);
    }
}
