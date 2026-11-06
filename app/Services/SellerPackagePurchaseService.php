<?php

namespace App\Services;

use App\Models\Seller;
use App\Models\SellerPackage;
use App\Models\SellerPackageSubscription;
use App\Models\SellerPackageTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SellerPackagePurchaseService
{
    public function __construct(
        private readonly SellerLedgerService $ledger,
        private readonly SubscriptionDurationService $durationService,
    ) {}

    /** Persisted request key prevents charging again after a lost response. */
    public function purchaseWithPaymentsBalance(Seller $seller, SellerPackage $package, string $requestKey, ?float $expectedPrice = null): array
    {
        return DB::transaction(function () use ($seller, $package, $requestKey, $expectedPrice) {
            $this->ledger->lockSellerFinancialState((int) $seller->id);
            $subscription = SellerPackageSubscription::where('seller_id', $seller->id)
                ->where(fn ($query) => $query->where('metadata->purchase_request_key', $requestKey)
                    ->orWhereJsonContains('metadata->purchase_request_keys', $requestKey))->first();
            if ($subscription && (int) $subscription->seller_package_id !== (int) $package->id) {
                throw new DomainException('package_retry_details_mismatch');
            }
            $package = SellerPackage::whereKey($package->id)->lockForUpdate()->firstOrFail();
            if (!$subscription && $expectedPrice !== null && (int) round($expectedPrice * 100) !== (int) round((float) $package->package_price * 100)) {
                throw new DomainException('تغير سعر الباقة. حدّث الصفحة وراجع السعر قبل تأكيد الشراء.');
            }
            $subscription ??= $this->getOrCreatePendingSubscription($seller, $package);
            if ($subscription->payment_status !== 'paid' && $expectedPrice !== null && (int) round($expectedPrice * 100) !== (int) round((float) $subscription->paid_package_price * 100)) {
                throw new DomainException('راجع السعر المحفوظ لطلب الدفع قبل التأكيد.');
            }
            $metadata = $subscription->metadata ?: [];
            $metadata['purchase_request_keys'] = array_values(array_unique(array_merge($metadata['purchase_request_keys'] ?? [], array_filter([$metadata['purchase_request_key'] ?? null, $requestKey]))));
            $metadata['purchase_request_key'] = $requestKey;
            $subscription->update(['metadata' => $metadata]);
            return $this->payFromOperatingBalance($subscription);
        });
    }

    public function payFromOperatingBalance(SellerPackageSubscription $subscription): array
    {
        return DB::transaction(function () use ($subscription) {
            $this->ledger->lockSellerFinancialState((int) $subscription->seller_id);
            $subscription = SellerPackageSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($subscription->payment_status === 'paid') {
                return ['status' => 1, 'message' => 'already_paid', 'subscription' => $subscription];
            }

            if ($subscription->payment_status !== 'unpaid'
                || $subscription->status !== SellerPackageSubscription::STATUS_PENDING) {
                throw new DomainException('seller_package_is_not_available_for_operating_balance_payment');
            }

            $amount = (float) $subscription->paid_package_price;
            try {
                $this->ledger->assertSufficient((int) $subscription->seller_id, SellerLedgerService::OPERATING, $amount);
            } catch (\InvalidArgumentException $exception) {
                throw new DomainException('seller_operating_balance_insufficient', previous: $exception);
            }

            $this->ledger->post(
                sellerId: (int) $subscription->seller_id,
                eventType: 'seller_package_purchase',
                groupKey: 'seller-package-subscription:'.$subscription->id.':operating-debit',
                movements: [['bucket' => SellerLedgerService::OPERATING, 'direction' => 'debit', 'amount' => $amount]],
                referenceType: SellerPackageSubscription::class,
                referenceId: (int) $subscription->id,
                metadata: ['payment_method' => 'operating_balance'],
            );

            $result = $this->markPaid($subscription, [
                'payment_method' => 'operating_balance',
                'transaction_id' => 'operating-balance-package-'.$subscription->id,
                'payment_amount' => $amount,
                'currency_code' => getCurrencyCode(type: 'default'),
            ]);
            if (($result['status'] ?? 0) !== 1) {
                throw new DomainException($result['message'] ?? 'seller_package_subscription_cannot_be_activated');
            }

            return $result;
        });
    }

    public function getSummary(Seller $seller): array
    {
        if (config('vendor_advertising.enabled')) {
            app(VendorAdvertisingSlotService::class)->synchronize($seller);
        }
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
        return [
            'active_subscription' => $activeSubscription,
            'pending_subscription' => $pendingSubscription,
            'pending_review' => $pendingSubscription?->status === SellerPackageSubscription::STATUS_PENDING_REVIEW,
            'can_purchase' => ! $pendingSubscription,
            'scheduled_subscriptions' => config('vendor_advertising.enabled')
                ? $seller->packageSubscriptions()->where('status', 'scheduled')->where('payment_status', 'paid')->orderBy('starts_at')->get()
                : collect(),
        ];
    }

    public function cancel(
        SellerPackageSubscription $subscription,
        string $reason,
        string $requestedByType,
        int $requestedById
    ): SellerPackageSubscription {
        if (config('vendor_advertising.enabled')) {
            throw new DomainException('vendor_paid_subscription_cancellation_unavailable');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('seller_package_cancellation_reason_is_required');
        }

        return DB::transaction(function () use ($subscription, $reason, $requestedByType, $requestedById) {
            $subscription = SellerPackageSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($subscription->status !== SellerPackageSubscription::STATUS_ACTIVE
                || $subscription->payment_status !== 'paid') {
                throw new DomainException('only_active_paid_package_can_be_cancelled');
            }

            $effect = in_array($subscription->cancellation_effect, ['immediate', 'end_of_period'], true)
                ? $subscription->cancellation_effect
                : 'end_of_period';
            $metadata = $subscription->metadata ?: [];
            $metadata['cancellation'] = [
                'effect' => $effect,
                'reason' => $reason,
                'requested_by_type' => $requestedByType,
                'requested_by_id' => $requestedById,
                'requested_at' => now()->toDateTimeString(),
                'refund_amount' => 0,
                'refund_policy' => 'no_refund',
            ];
            $data = [
                'cancel_at_period_end' => $effect === 'end_of_period',
                'cancellation_requested_at' => now(),
                'cancellation_reason' => $reason,
                'cancellation_requested_by_type' => $requestedByType,
                'cancellation_requested_by_id' => $requestedById,
                'metadata' => $metadata,
            ];
            if ($effect === 'immediate') {
                $data += ['status' => SellerPackageSubscription::STATUS_CANCELLED, 'cancelled_at' => now()];
            }
            $subscription->update($data);

            if ($effect === 'immediate') {
                $subscription->promotions()->where('status', 'active')->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
                cacheRemoveByType(type: 'products');
            }

            return $subscription->fresh();
        });
    }

    public function getOrCreatePendingSubscription(Seller $seller, SellerPackage $package): SellerPackageSubscription
    {
        return DB::transaction(function () use ($seller, $package) {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->id);
            $package = SellerPackage::query()->whereKey($package->id)->first();
            if (! $package) {
                throw new DomainException('seller_package_not_found_or_inactive');
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

            if (! $package->status) {
                throw new DomainException('seller_package_not_found_or_inactive');
            }
            if ((float) $package->package_price <= 0
                || (config('vendor_advertising.enabled') ? (int) $package->product_limit <= 0 : ((int) $package->search_promotion_limit <= 0
                    && (int) $package->homepage_promotion_limit <= 0
                    && (int) $package->coupon_limit <= 0))) {
                throw new DomainException('seller_package_configuration_is_invalid');
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

            if ($subscription->payment_status === 'paid') {
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
                || (config('vendor_advertising.enabled') ? (int) $subscription->product_limit <= 0 : ((int) $subscription->search_promotion_limit <= 0
                    && (int) $subscription->homepage_promotion_limit <= 0
                    && (int) $subscription->coupon_limit <= 0))) {
                return ['status' => 0, 'message' => 'seller_package_subscription_snapshot_is_invalid'];
            }

            $offline = $subscription->metadata['offline_payment'] ?? [];
            $transferReference = $offline['transfer_reference'] ?? null;
            $provided = $paymentData['transaction_id'] ?? null;
            if ($provided && !preg_match('/^(seller-package-offline-|unified-review-|offline-review:)/',$provided) && ($paymentData['payment_method'] ?? $subscription->payment_method) === 'offline_payment') $transferReference = $provided;
            if ($transferReference && ($offline['method_id'] ?? null)) {
                app(VendorTransferReferenceService::class)->reserve((int)$offline['method_id'],$transferReference,'package_subscription',$subscription->id,true);
                $paymentData['transaction_id'] = $transferReference;
            }

            $paidThrough = null;
            if (config('vendor_advertising.enabled')) {
                $paidThrough = SellerPackageSubscription::where('seller_id', $subscription->seller_id)
                    ->where('payment_status', 'paid')->whereIn('status', ['active', 'scheduled'])
                    ->where('expires_at', '>', now())->orderByDesc('expires_at')->lockForUpdate()->first()?->expires_at;
            } else {
            SellerPackageSubscription::query()
                ->where('seller_id', $subscription->seller_id)
                ->where('id', '!=', $subscription->id)
                ->where('status', SellerPackageSubscription::STATUS_ACTIVE)
                ->update([
                    'status' => SellerPackageSubscription::STATUS_REPLACED,
                    'cancelled_at' => now(),
                ]);
            }

            $paymentRequestId = $paymentData['id'] ?? $subscription->payment_request_id;
            $startsAt = now();
            $duration = $this->durationService->normalize(
                $subscription->duration_unit,
                $subscription->duration_value,
                $subscription->package_validity_days,
            );
            $expiresAt = $this->durationService->expiresAt($startsAt, $duration['unit'], $duration['value']);
            if (config('vendor_advertising.enabled')) {
                $cycle = app(VendorAdvertisingCyclePolicy::class)->cycle($startsAt, $paidThrough);
                $startsAt = $cycle['starts_at'];
                $expiresAt = $cycle['expires_at'];
                $duration = ['unit' => 'days', 'value' => 30];
            }
            $metadata = $subscription->metadata ?: [];
            $metadata['payment'] = [
                'payment_request_id' => $paymentRequestId,
                'payment_method' => $paymentData['payment_method'] ?? $subscription->payment_method,
                'transaction_id' => $paymentData['transaction_id'] ?? null,
                'payment_amount' => $paymentData['payment_amount'] ?? $subscription->paid_package_price,
                'currency_code' => $paymentData['currency_code'] ?? null,
                'paid_at' => now()->toDateTimeString(),
            ];
            if ($reviewedByAdminId) {
                app(VendorTransferReferenceService::class)->release('package_subscription',$subscription->id);
            $metadata['offline_payment_review'] = [
                    'action' => 'approved',
                    'reviewed_by_admin_id' => $reviewedByAdminId,
                    'reviewed_at' => $startsAt->toDateTimeString(),
                    'note' => $reviewNote,
                ];
            }

            $subscription->update([
                'status' => $startsAt->gt(now()) ? SellerPackageSubscription::STATUS_SCHEDULED : SellerPackageSubscription::STATUS_ACTIVE,
                'payment_status' => 'paid',
                'payment_method' => $paymentData['payment_method'] ?? $subscription->payment_method,
                'payment_reference' => $paymentData['transaction_id'] ?? $subscription->payment_reference,
                'payment_request_id' => $paymentRequestId,
                'starts_at' => $startsAt,
                'started_at' => $startsAt,
                'expires_at' => $expiresAt,
                'duration_unit' => $duration['unit'],
                'duration_value' => $duration['value'],
                'activated_at' => now(),
                'metadata' => $metadata,
            ]);

            $this->createActivationTransactions($subscription->fresh(), $paymentData, $reviewedByAdminId);
            if (config('vendor_advertising.enabled')) {
                app(VendorAdvertisingSlotService::class)->provision($subscription->fresh());
                app(VendorAdvertisingSlotService::class)->restorePrevious($subscription->fresh());
            }

            // The locked paid transition is executed only once, even when a
            // payment callback or an approval request is retried.
            app(SellerPackagePaymentNotificationService::class)->notifyPaid($subscription);
            app(VendorFulfillmentUnlockService::class)->notifyPackageConfirmed($subscription->fresh());

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

    public function snapshotForFunding(SellerPackage $package): array
    {
        if (!$package->status || $package->package_price <= 0 || $package->product_limit < 1) throw new DomainException('seller_package_not_found_or_inactive');
        return $this->packageSnapshot($package);
    }

    public function purchaseFundingSnapshot(Seller $seller, int $packageId, array $snapshot, string $key): SellerPackageSubscription
    {
        return DB::transaction(function () use ($seller, $packageId, $snapshot, $key) {
            $this->ledger->lockSellerFinancialState($seller->id);
            $existing = SellerPackageSubscription::where('seller_id',$seller->id)->where('metadata->funding_intent_key',$key)->lockForUpdate()->first();
            if ($existing) return $this->payFromOperatingBalance($existing)['subscription'];
            if (SellerPackageSubscription::where('seller_id',$seller->id)->where('payment_status','unpaid')->whereIn('status',['pending','pending_review'])->exists()) throw new DomainException('يوجد طلب باقة آخر معلق؛ راجعه أولًا ثم أعد المحاولة.');
            $subscription = SellerPackageSubscription::create(array_merge($snapshot, ['seller_id'=>$seller->id, 'seller_package_id'=>$packageId,
                'status'=>'pending', 'payment_status'=>'unpaid', 'metadata'=>['funding_intent_key'=>$key, 'package_snapshot'=>$snapshot]]));
            return $this->payFromOperatingBalance($subscription)['subscription'];
        });
    }

    private function packageSnapshot(SellerPackage $package): array
    {
        $snapshot = [
            'package_name' => $package->name,
            'paid_package_price' => (float) $package->package_price,
            'product_limit' => 0,
            'used_product_limit' => 0,
            'product_adjustment_limit' => 0,
            'product_duration_days' => 0,
            'search_promotion_limit' => $package->search_promotion_limit,
            'used_search_promotion_limit' => 0,
            'search_promotion_adjustment_limit' => 0,
            'search_promotion_duration_days' => $package->search_promotion_duration_days,
            'search_priority' => $package->search_priority,
            'homepage_promotion_limit' => $package->homepage_promotion_limit,
            'used_homepage_promotion_limit' => 0,
            'homepage_promotion_adjustment_limit' => 0,
            'homepage_promotion_duration_days' => $package->homepage_promotion_duration_days,
            'coupon_limit' => $package->coupon_limit,
            'used_coupon_limit' => 0,
            'coupon_adjustment_limit' => 0,
            'package_validity_days' => $package->package_validity_days,
            'duration_unit' => ($duration = $this->durationService->fromPackage($package))['unit'],
            'duration_value' => $duration['value'],
            'cancellation_effect' => $package->cancellation_effect ?: 'end_of_period',
        ];
        if (! Schema::hasColumn('seller_package_subscriptions', 'search_priority')) {
            unset($snapshot['search_priority']);
        }
        if (! Schema::hasColumn('seller_package_subscriptions', 'cancellation_effect')) {
            unset($snapshot['cancellation_effect']);
        }
        if (config('vendor_advertising.enabled')) {
            $snapshot = array_replace($snapshot, [
                'product_limit' => (int) $package->product_limit, 'product_duration_days' => 30,
                'search_promotion_limit' => 0, 'search_promotion_duration_days' => 0,
                'homepage_promotion_limit' => 0, 'homepage_promotion_duration_days' => 0,
                'coupon_limit' => 0, 'package_validity_days' => 30, 'duration_unit' => 'days', 'duration_value' => 30,
            ]);
        }

        return $snapshot;
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
            SellerPackageTransaction::QUOTA_COUPON => $subscription->coupon_limit,
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
        $due = $seller->packageSubscriptions()
            ->where('status', SellerPackageSubscription::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
        (clone $due)->where('cancel_at_period_end', true)->update([
            'status' => SellerPackageSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
        $due->where('cancel_at_period_end', false)->update(['status' => SellerPackageSubscription::STATUS_EXPIRED]);
    }
}
