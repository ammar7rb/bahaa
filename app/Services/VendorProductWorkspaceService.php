<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use App\Models\VendorProductWorkspace;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Published products stay untouched while private vendor/editorial revisions are reviewed. */
class VendorProductWorkspaceService
{
    public const CONTENT = [
        'name', 'details', 'unit_price', 'purchase_price', 'thumbnail', 'thumbnail_storage_type',
        'images', 'color_image', 'colors', 'variation', 'choice_options', 'attributes',
        'category_ids', 'category_id', 'sub_category_id', 'sub_sub_category_id', 'brand_id',
        'unit', 'sale_unit_type', 'pieces_per_unit', 'product_type', 'digital_product_type',
        'digital_file_ready', 'digital_file_ready_storage_type', 'preview_file', 'preview_file_storage_type', 'video_url', 'video_provider',
        'digital_product_file_types', 'digital_product_extensions',
        'shipping_cost', 'same_day_shipping_cost', 'next_day_shipping_cost', 'normal_shipping_cost', 'multiply_qty',
        'minimum_order_qty', 'current_stock', 'tax', 'tax_type', 'tax_model', 'discount', 'discount_type',
        'length', 'width', 'height', 'dimension_unit', 'weight', 'weight_unit',
        'production_date', 'expiry_date', 'no_production_expiry_dates', 'category_review_required',
        'meta_title', 'meta_description', 'meta_image', 'code',
    ];

    public function enabled(): bool
    {
        return (bool) config('vendor_product_workspace.enabled');
    }

    public function sellerId(): ?int
    {
        return request()->attributes->get('vendor_workspace_seller_id');
    }

    public function state(Product $product): ?VendorProductWorkspace
    {
        if (!$this->enabled() || $product->added_by !== 'seller') return null;
        return VendorProductWorkspace::where('product_id', $product->getRawOriginal('id') ?: $product->id)
            ->where('seller_id', $product->user_id)->first();
    }

    public function capture(Product $product, bool $legacy = false): VendorProductWorkspace
    {
        return DB::transaction(function () use ($product, $legacy) {
            Seller::whereKey($product->user_id)->lockForUpdate()->firstOrFail();
            $existing = VendorProductWorkspace::where('product_id', $product->id)->first();
            if ($existing) return $existing;
            // Random numeric references preserve mobile integer contracts without revealing public IDs.
            do { $reference = random_int(100000000000, 999999999999); }
            while (VendorProductWorkspace::where('private_reference', $reference)->exists()
                || DB::table('products')->where('id', $reference)->exists());
            $content = array_intersect_key($product->getAttributes(), array_flip(self::CONTENT));
            $approved = (int) $product->request_status === 1;
            $state = VendorProductWorkspace::create([
                'product_id' => $product->id, 'seller_id' => $product->user_id,
                'private_reference' => $reference, 'seller_content' => $content,
                'revision' => 1,
                'approved_seller_content' => $approved ? $content : null,
                'approved_revision' => $approved ? 1 : null,
                'review_state' => $product->seller_review_status ?: ($approved ? ((int) $product->status === 1 ? 'approved_published' : 'suspended') : ((int) $product->request_status === 2 ? 'rejected' : 'submitted')),
                'publication_suspended' => $product->seller_review_status === 'suspended',
                'legacy_exempt' => $legacy,
                'baseline_source' => $legacy ? 'current_data_at_rollout'
                    : (request()->filled('slot_id') ? 'seller_submission' : 'default_plan_outside_slot'),
            ]);
            $this->recordSubmission($state);
            return $state;
        });
    }

    public function projectForSeller(Product $product): void
    {
        if (request()->attributes->get('vendor_workspace_financial_context')) return;
        if (!$this->enabled() || !$this->sellerId() || ($product->user_id !== null && (int) $product->user_id !== $this->sellerId())) return;
        $state = VendorProductWorkspace::where('product_id', $product->id)->where('seller_id', $this->sellerId())->first();
        if (!$state) return;
        $private = array_replace(array_fill_keys(self::CONTENT, null), $state->seller_content);
        unset($private['current_stock']); // Live stock is operational, not editorial copy.
        $product->setRawAttributes(array_replace($product->getAttributes(), ['user_id' => $state->seller_id, 'added_by' => 'seller'], $private), true);
        // Prevent a translation/SEO relation from substituting the public editorial content.
        $product->setRelation('translations', collect());
        $product->makeHidden(['seller_review_status', 'seller_review_reason', 'seller_review_changes', 'seller_reviewed_at', 'seller_reviewed_by', 'denied_note']);
        $product->makeHidden(['slug', 'translations', 'seoInfo', 'seo_info', 'digitalVariation', 'digital_variation', 'tags', 'meta_title', 'meta_description', 'meta_image', 'meta_image_full_url']);
    }

    public function saveSellerDraft(Product $product, array $content): VendorProductWorkspace
    {
        return DB::transaction(function () use ($product, $content) {
            Seller::whereKey($product->user_id)->lockForUpdate()->firstOrFail();
            $this->assertCanEdit($product);
            $state = VendorProductWorkspace::where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $this->recordSubmission($state);
            $content = array_intersect_key($content, array_flip(self::CONTENT));
            $next = array_replace($state->seller_content, $content);
            if ($next === $state->seller_content) return $state;
            $state->update([
                'seller_content' => $next, 'revision' => $state->revision + 1,
                'review_state' => 'submitted', 'submitted_at' => now(), 'publication_suspended' => true,
                // A previous editorial draft must not approve a newer seller price silently.
                'editorial_content' => null, 'editorial_revision' => null,
            ]);
            DB::table('products')->where('id', $product->id)->update([
                'seller_review_status' => 'submitted', 'seller_submitted_at' => now(),
                'request_status' => 0, 'status' => 0, 'is_homepage_visible' => 0, 'featured' => 0,
            ]);
            $this->recordSubmission($state->fresh());
            $this->notifyAdminSubmission($state->fresh());
            DB::afterCommit(fn () => cacheRemoveByType(type: 'products'));
            return $state->fresh();
        });
    }

    public function projectForEditorial(Product $product): void
    {
        $state = $this->state($product);
        if (!$state) return;
        $content = array_intersect_key($state->editorial_content ?: $state->seller_content, array_flip(self::CONTENT));
        $content['current_stock'] = $product->current_stock;
        $liveVariants = collect(json_decode($product->getRawOriginal('variation') ?? '[]', true) ?: [])->keyBy('type');
        if (isset($content['variation'])) {
            $variants = json_decode($content['variation'], true) ?: [];
            foreach ($variants as &$variant) if (isset($liveVariants[$variant['type'] ?? ''])) $variant['qty'] = $liveVariants[$variant['type']]['qty'];
            unset($variant);
            $content['variation'] = json_encode($variants, JSON_UNESCAPED_UNICODE);
        }
        // Editorial drafts can contain decoded values from ProductService. Raw model
        // attributes must keep JSON casts encoded so reopening the editor is safe.
        foreach (['digital_product_file_types', 'digital_product_extensions'] as $field) {
            if (isset($content[$field]) && is_array($content[$field])) {
                $content[$field] = json_encode($content[$field], JSON_THROW_ON_ERROR);
            }
        }
        $product->setRawAttributes(array_replace($product->getAttributes(), $content), true);
        $product->setRelation('translations', collect());
        $product->setRelation('seoInfo', null);
    }

    public function saveEditorialDraft(Product $product, array $content, int $sellerRevision, bool $reviewed = true, ?int $stockAtFormLoad = null): VendorProductWorkspace
    {
        return DB::transaction(function () use ($product, $content, $sellerRevision, $reviewed, $stockAtFormLoad) {
            Seller::whereKey($product->user_id)->lockForUpdate()->firstOrFail();
            $state = VendorProductWorkspace::where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            if ($state->revision !== $sellerRevision) throw \Illuminate\Validation\ValidationException::withMessages(['review' => translate('vendor_revision_changed_refresh_review')]);
            $inventory = DB::table('products')->where('id', $product->id)->first();
            if ($stockAtFormLoad !== null && (int) $inventory->current_stock !== $stockAtFormLoad) {
                throw \Illuminate\Validation\ValidationException::withMessages(['review' => translate('vendor_inventory_changed_refresh_review')]);
            }
            $editorial = array_replace($state->editorial_content ?: $state->seller_content, array_intersect_key($content, array_flip(self::CONTENT)));
            $editorial['__stock_at_review'] = $inventory->current_stock;
            $editorial['__variation_at_review'] = $inventory->variation ?? null;
            $state->update([
                'editorial_content' => $editorial,
                'editorial_revision' => $reviewed ? $sellerRevision : null, 'review_state' => 'under_review',
            ]);
            return $state->fresh();
        });
    }

    public function approve(Product $product): void
    {
        if (!config('vendor_order_eligibility.enabled', false)
            && !app(VendorFulfillmentEligibilityService::class)->slotFor((int) $product->user_id, (int) $product->id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'review' => 'يمكن مراجعة المنتج الآن؛ نشر المنتجات خارج المساحات مؤجل حتى اكتمال تفعيل أهلية الطلبات.',
            ]);
        }
        $state = VendorProductWorkspace::where('product_id', $product->id)->lockForUpdate()->firstOrFail();
        if (!$state->editorial_content || $state->editorial_revision !== $state->revision) {
            throw \Illuminate\Validation\ValidationException::withMessages(['review' => translate('save_editorial_copy_for_current_vendor_revision_first')]);
        }
        $editorial = $state->editorial_content;
        $stockAtReview = $editorial['__stock_at_review'] ?? null;
        $baselineVariants = collect(json_decode($editorial['__variation_at_review'] ?? '[]', true) ?: [])->keyBy('type');
        unset($editorial['__stock_at_review'], $editorial['__variation_at_review']);
        if (isset($editorial['current_stock']) && $stockAtReview !== null) {
            if ((int) $editorial['current_stock'] === (int) $stockAtReview) {
                unset($editorial['current_stock']);
            } elseif ((int) $product->current_stock !== (int) $stockAtReview) {
                throw \Illuminate\Validation\ValidationException::withMessages(['review' => translate('vendor_inventory_changed_refresh_review')]);
            }
        }
        if (isset($editorial['variation'])) {
            $liveVariants = collect(json_decode($product->getRawOriginal('variation') ?? '[]', true) ?: [])->keyBy('type');
            $variants = json_decode($editorial['variation'], true) ?: [];
            foreach ($variants as &$variant) {
                $type = $variant['type'] ?? '';
                if (!isset($baselineVariants[$type], $liveVariants[$type])) continue;
                if ((int) ($variant['qty'] ?? 0) === (int) ($baselineVariants[$type]['qty'] ?? 0)) $variant['qty'] = $liveVariants[$type]['qty'];
                elseif ((int) ($liveVariants[$type]['qty'] ?? 0) !== (int) ($baselineVariants[$type]['qty'] ?? 0)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['review' => translate('vendor_inventory_changed_refresh_review')]);
                }
            }
            unset($variant);
            $editorial['variation'] = json_encode($variants, JSON_UNESCAPED_UNICODE);
        }
        $product->forceFill($editorial);
        $product->workspacePublicationWrite = true;
        $state->update([
            'approved_seller_content' => $state->seller_content,
            'approved_revision' => $state->revision, 'approved_at' => now(),
            'review_state' => 'approved_published',
            'publication_suspended' => false,
        ]);
    }

    /** Use the saved private copy in historical order payloads too, never the editorial JSON. */
    public function privatePayload(int $productId, array $payload): array
    {
        if (!$this->enabled() || !$this->sellerId()) return $payload;
        $state = VendorProductWorkspace::where('product_id', $productId)->where('seller_id', $this->sellerId())->first();
        if (!$state) return ['id' => $productId];
        foreach (array_replace(array_fill_keys(self::CONTENT, null), $state->seller_content) as $key => $value) {
            if (is_string($value) && in_array($key, ['images', 'colors', 'color_image', 'variation', 'choice_options', 'attributes', 'category_ids'], true)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($payload[$key] ?? null)) $value = $decoded;
            }
            $payload[$key] = $value;
        }
        $payload['discount'] = 0;
        $payload['discount_type'] = 'flat';
        $payload['status'] = (int) $this->vendorActive($state);
        $payload['request_status'] = 1;
        $private = new Product();
        $private->setRawAttributes($state->seller_content);
        foreach (['thumbnail_full_url', 'images_full_url', 'color_images_full_url', 'preview_file_full_url', 'digital_file_ready_full_url'] as $key) {
            if (array_key_exists($key, $payload)) $payload[$key] = $private->getAttribute($key);
        }
        return $payload;
    }

    public function syncPublishedTranslations(Product $product): void
    {
        // Each existing language falls back to the reviewed editorial content rather than old vendor text.
        foreach (['name' => 'name', 'description' => 'details'] as $key => $field) {
            $product->translations()->where('key', $key)->update(['value' => $product->getRawOriginal($field) ?? $product->getAttributes()[$field] ?? '']);
        }
    }

    public function assertCanEdit(Product $product): void
    {
        if ($this->state($product)?->legacy_exempt) return;
        app(VendorAdvertisingAccessService::class)->assertCanCreate((int) $product->user_id);
    }

    /** Administration-only placement classification; never substitutes for publication review. */
    public function hasAdvertisingSlot(Product $product): bool
    {
        return DB::table('vendor_advertising_slots as s')
            ->join('seller_package_subscriptions as p', 'p.id', '=', 's.seller_package_subscription_id')
            ->where('s.seller_id', $product->user_id)->where('p.seller_id', $product->user_id)
            ->where('s.product_id', $product->getRawOriginal('id'))
            ->where('p.payment_status', 'paid')->whereIn('p.status', ['active', 'scheduled'])
            ->where('p.starts_at', '<=', now())->where('p.expires_at', '>', now())
            ->where('s.starts_at', '<=', now())->where('s.expires_at', '>', now())->exists();
    }

    /** Activity belongs to the vendor's paid slot, never to the editorial decision. */
    public function vendorActive(VendorProductWorkspace $state): bool
    {
        if ($state->legacy_exempt) return true;
        return DB::table('vendor_advertising_slots as s')
            ->join('seller_package_subscriptions as p', 'p.id', '=', 's.seller_package_subscription_id')
            ->where('s.seller_id', $state->seller_id)->where('p.seller_id', $state->seller_id)
            ->where('s.product_id', $state->product_id)->where('p.payment_status', 'paid')
            ->whereIn('p.status', ['active', 'scheduled'])
            ->where('s.starts_at', '<=', now())->where('s.expires_at', '>', now())
            ->where('p.starts_at', '<=', now())->where('p.expires_at', '>', now())->exists();
    }

    /** Immutable content revision; links the private submission, slot and public row. */
    public function recordSubmission(VendorProductWorkspace $state, ?int $slotId = null): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('vendor_product_submission_revisions')) return;
        $slotId ??= DB::table('vendor_advertising_slots')->where('seller_id', $state->seller_id)
            ->where('product_id', $state->product_id)->orderByDesc('id')->value('id');
        DB::table('vendor_product_submission_revisions')->insertOrIgnore([
            'workspace_id' => $state->id, 'product_id' => $state->product_id,
            'seller_id' => $state->seller_id, 'slot_id' => $slotId, 'revision' => $state->revision,
            'seller_content' => json_encode($state->seller_content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'recorded_at' => now(),
        ]);
        if ($slotId !== null) DB::table('vendor_product_submission_revisions')
            ->where('workspace_id', $state->id)->where('revision', $state->revision)
            ->whereNull('slot_id')->update(['slot_id' => $slotId]);
    }

    public function notifyAdminSubmission(VendorProductWorkspace $state): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('notifications')) return;
        $seller = Seller::find($state->seller_id);
        $description = ($state->revision > 1 ? 'تعديل منتج من البائع ' : 'منتج جديد من البائع ')
            .trim(($seller?->f_name ?? '').' '.($seller?->l_name ?? ''))
            .' #'.$state->seller_id.' · المنتج #'.$state->product_id.' · الإصدار '.$state->revision;
        \App\Models\Notification::firstOrCreate([
            'sent_by' => 'seller', 'sent_to' => 'admin', 'seller_id' => $state->seller_id,
            'title' => 'منتج بانتظار مراجعة الإدارة', 'description' => $description,
        ], ['notification_count' => 1, 'status' => 1]);
    }

    public function approvedPrice(Product $product): ?array
    {
        $state = $this->state($product);
        if (!$state?->approved_seller_content) return null;
        return ['unit_price' => $state->approved_seller_content['unit_price'] ?? null, 'revision' => $state->approved_revision];
    }
    public function snapshotOrderLine(array $line): array
    {
        if (!$this->enabled() || empty($line['product_id'])) return $line;
        $state = VendorProductWorkspace::where('product_id', $line['product_id'])->first();
        if (!$state?->approved_seller_content || !isset($state->approved_seller_content['unit_price'])) return $line;
        $line['vendor_unit_price_snapshot'] = $state->approved_seller_content['unit_price'];
        $variants = $state->approved_seller_content['variation'] ?? [];
        if (is_string($variants)) $variants = json_decode($variants, true) ?: [];
        foreach ($variants as $variant) {
            if (!empty($line['variant']) && ($variant['type'] ?? null) === $line['variant'] && isset($variant['price'])) {
                $line['vendor_unit_price_snapshot'] = $variant['price'];
                break;
            }
        }
        $line['vendor_content_revision'] = $state->approved_revision;
        return $line;
    }

}
