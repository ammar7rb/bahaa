<?php

namespace App\Models;

use App\Traits\CacheManagerTrait;
use App\Traits\StorageTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\TaxModule\app\Models\Taxable;

/**
 * @property int $user_id
 * @property int $shop_id
 * @property string $added_by
 * @property string $name
 * @property string $code
 * @property string $slug
 * @property int $category_id
 * @property int $sub_category_id
 * @property int $sub_sub_category_id
 * @property int $brand_id
 * @property string $unit
 * @property string $digital_product_type
 * @property string $product_type
 * @property string $details
 * @property int $min_qty
 * @property int $published
 * @property float $tax
 * @property string $tax_type
 * @property string $tax_model
 * @property float $unit_price
 * @property int $status
 * @property float $discount
 * @property int $current_stock
 * @property int $minimum_order_qty
 * @property int $free_shipping
 * @property int $request_status
 * @property int $featured_status
 * @property int $refundable
 * @property int $featured
 * @property int $is_homepage_visible
 * @property int $flash_deal
 * @property int $seller_id
 * @property float $purchase_price
 * @property string $denied_note
 * @property float $shipping_cost
 * @property float $same_day_shipping_cost
 * @property float $next_day_shipping_cost
 * @property float $normal_shipping_cost
 * @property float $same_day_shipping_cost
 * @property float $next_day_shipping_cost
 * @property float $normal_shipping_cost
 * @property int $multiply_qty
 * @property float $temp_shipping_cost
 * @property string $thumbnail
 * @property string $thumbnail_storage_type
 * @property string $preview_file
 * @property string $preview_file_storage_type
 * @property string $digital_file_ready
 * @property string $meta_title
 * @property string $meta_description
 * @property string $meta_image
 * @property int $is_shipping_cost_updated
 */
class Product extends Model
{
    public bool $workspacePublicationWrite = false;

    // Product-level discounts are retired; keep stored values for historical audit.
    public function getDiscountAttribute($value): float
    {
        return app(\App\Services\FlashSalePriceService::class)->forProduct($this)['amount'] ?? 0.0;
    }

    public function getDiscountTypeAttribute($value): ?string
    {
        return app(\App\Services\FlashSalePriceService::class)->forProduct($this)['type'] ?? $value;
    }

    public function setDiscountAttribute($value): void
    {
        $this->attributes['discount'] = 0;
    }

    public function save(array $options = [])
    {
        if (config('vendor_product_workspace.enabled') && !$this->exists && $this->added_by === 'seller') {
            return DB::transaction(function () use ($options) {
                \App\Models\Seller::whereKey($this->user_id)->lockForUpdate()->firstOrFail();
                return parent::save($options);
            });
        }
        return parent::save($options);
    }
    public function getAdministrativeReferenceAttribute(): string
    {
        $workspace = app(\App\Services\VendorProductWorkspaceService::class);
        if ($workspace->enabled() && $workspace->sellerId()) return 'AD-'.($workspace->state($this)?->private_reference ?? '');
        return \App\Services\AdministrativeReferenceService::format('product', (int) $this->id);
    }

    use StorageTrait, CacheManagerTrait;

    protected $fillable = [
        'user_id',
        'shop_id',
        'added_by',
        'name',
        'code',
        'slug',
        'category_ids',
        'category_id',
        'sub_category_id',
        'sub_sub_category_id',
        'brand_id',
        'unit',
        'pieces_per_unit',
        'length',
        'width',
        'height',
        'dimension_unit',
        'weight',
        'weight_unit',
        'production_date',
        'no_production_expiry_dates',
        'category_review_required',
        'expiry_date',
        'digital_product_type',
        'product_type',
        'details',
        'colors',
        'choice_options',
        'variation',
        'digital_product_file_types',
        'digital_product_extensions',
        'unit_price',
        'purchase_price',
        'tax',
        'tax_type',
        'tax_model',
        'discount',
        'discount_type',
        'attributes',
        'current_stock',
        'minimum_order_qty',
        'video_provider',
        'video_url',
        'status',
        'featured_status',
        'featured',
        'is_homepage_visible',
        'request_status',
        'denied_note',
        'shipping_cost',
        'same_day_shipping_cost',
        'next_day_shipping_cost',
        'normal_shipping_cost',
        'same_day_shipping_cost',
        'next_day_shipping_cost',
        'normal_shipping_cost',
        'multiply_qty',
        'color_image',
        'images',
        'thumbnail',
        'thumbnail_storage_type',
        'preview_file',
        'preview_file_storage_type',
        'digital_file_ready',
        'meta_title',
        'meta_description',
        'meta_image',
        'digital_file_ready_storage_type',
        'is_shipping_cost_updated',
        'temp_shipping_cost',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'user_id' => 'integer',
        'shop_id' => 'integer',
        'added_by' => 'string',
        'name' => 'string',
        'code' => 'string',
        'public_code' => 'string',
        'is_credit_listing' => 'boolean',
        'slug' => 'string',
        'category_id' => 'integer',
        'sub_category_id' => 'integer',
        'sub_sub_category_id' => 'integer',
        'brand_id' => 'integer',
        'unit' => 'string',
        'pieces_per_unit' => 'integer',
        'length' => 'float',
        'width' => 'float',
        'height' => 'float',
        'weight' => 'float',
        'no_production_expiry_dates' => 'boolean',
        'category_review_required' => 'boolean',
        'production_date' => 'date:Y-m-d',
        'expiry_date' => 'date:Y-m-d',
        'digital_product_type' => 'string',
        'product_type' => 'string',
        'details' => 'string',
        'min_qty' => 'integer',
        'published' => 'integer',
        'tax' => 'float',
        'tax_type' => 'string',
        'tax_model' => 'string',
        'unit_price' => 'float',
        'status' => 'integer',
        'discount' => 'float',
        'current_stock' => 'integer',
        'minimum_order_qty' => 'integer',
        'free_shipping' => 'integer',
        'request_status' => 'integer',
        'seller_review_changes' => 'array',
        'featured_status' => 'integer',
        'refundable' => 'integer',
        'featured' => 'integer',
        'is_homepage_visible' => 'integer',
        'flash_deal' => 'integer',
        'seller_id' => 'integer',
        'purchase_price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'denied_note' => 'string',
        'shipping_cost' => 'float',
        'same_day_shipping_cost' => 'float',
        'next_day_shipping_cost' => 'float',
        'normal_shipping_cost' => 'float',
        'same_day_shipping_cost' => 'float',
        'next_day_shipping_cost' => 'float',
        'normal_shipping_cost' => 'float',
        'multiply_qty' => 'integer',
        'temp_shipping_cost' => 'float',
        'thumbnail' => 'string',
        'preview_file' => 'string',
        'digital_file_ready' => 'string',
        'meta_title' => 'string',
        'meta_description' => 'string',
        'meta_image' => 'string',
        'is_shipping_cost_updated' => 'integer',
        'digital_product_file_types' => 'array',
        'digital_product_extensions' => 'array',
        'thumbnail_storage_type' => 'string',
        'digital_file_ready_storage_type' => 'string',
    ];

    protected $appends = ['is_shop_temporary_close', 'thumbnail_full_url', 'preview_file_full_url', 'color_images_full_url', 'meta_image_full_url', 'images_full_url', 'digital_file_ready_full_url', 'administrative_reference', 'vendor_workspace'];

    public function getVendorWorkspaceAttribute(): ?array
    {
        $workspace = app(\App\Services\VendorProductWorkspaceService::class);
        if (!$workspace->enabled() || !$workspace->sellerId() || (int) $this->user_id !== $workspace->sellerId()) return null;
        $state = $workspace->state($this);
        if (!$state) return null;
        return ['private_reference' => $state->private_reference,
            'legacy_exempt' => $state->legacy_exempt, 'revision' => $state->revision,
            'advertising_visible' => $workspace->vendorActive($state)];
    }

    public function translations(): MorphMany
    {
        return $this->morphMany('App\Models\Translation', 'translationable')
            ->when(config('vendor_product_workspace.enabled') && app(\App\Services\VendorProductWorkspaceService::class)->sellerId() && !request()->attributes->get('vendor_workspace_financial_context'), fn ($query) => $query->whereRaw('1 = 0'));
    }

    public function scopeReviewRequestStatus($query, $status)
    {
        if (!config('vendor_product_workspace.enabled')) return $query->where('request_status', $status);
        $states = match ((int) $status) {
            0 => ['submitted', 'under_review'],
            2 => ['rejected'],
            default => ['approved_published', 'suspended'],
        };
        return $query->where(function ($q) use ($states, $status) {
            $q->whereExists(fn ($w) => $w->selectRaw('1')->from('vendor_product_workspaces as review_workspace')
                ->whereColumn('review_workspace.product_id', 'products.id')->whereIn('review_workspace.review_state', $states))
                ->orWhere(fn ($fallback) => $fallback->where('request_status', $status)->whereNotExists(fn ($w) => $w->selectRaw('1')->from('vendor_product_workspaces as review_workspace')->whereColumn('review_workspace.product_id', 'products.id')));
        });
    }

    public function scopeActive($query)
    {
        app(\App\Services\VendorAdvertisingAccessService::class)->constrain($query);
        $brandSetting = getWebConfig(name: 'product_brand');
        $digitalProductSetting = getWebConfig(name: 'digital_product');
        $businessMode = getWebConfig(name: 'business_mode');
        $productType = $digitalProductSetting ? ['digital', 'physical'] : ['physical'];

        return $query->when($businessMode == 'single', function ($query) {
                $query->where(['added_by' => 'admin']);
            })
            ->when($brandSetting && !in_array('digital', $productType), function ($query) use ($brandSetting, $productType) {
                $query->where(function($query) {
                    $query->whereHas('brand', function ($query) {
                        $query->where('status', 1);
                    })->orWhere(function ($query) {
                        $query->whereNull('brand_id')->where('status', 1);
                    });
                });
            })
            ->when($brandSetting && $digitalProductSetting, function ($query) {
                $query->where(function ($query) {
                    $query->where(function ($query) {
                        $query->where(['product_type' => 'digital', 'status' => 1]);
                    })->orWhere(function ($query) {
                        $query->where(['product_type' => 'physical', 'status' => 1])->where(function ($query2) {
                            $query2->whereNull('brand_id')->orWhere(function ($query3) {
                                $query3->whereNotNull('brand_id')->whereHas('brand', function ($brandQuery) {
                                    $brandQuery->where('status', 1);
                                });
                            });
                        });
                    });
                });
            })
            ->when(!$brandSetting, function ($query) {
                $query->whereNull('brand_id')->where('status', 1);
            })
            ->where(['status' => 1])
            ->where(['request_status' => 1])
            ->where(function ($query) {
                $query->where('added_by', 'admin')
                    ->orWhere(function ($query) {
                        $query->where('added_by', 'seller');
                    });
            })
            ->SellerApproved()
            ->whereIn('product_type', $productType);
    }

    public function scopeSellerApproved($query): void
    {
        $query->whereHas('seller', function ($query) {
            $query->where(['status' => 'approved']);
        })->orWhere(function ($query) {
            $query->where(['added_by' => 'admin', 'status' => 1]);
        });
    }

    /**
     * A product may be approved and searchable without being eligible for a
     * storefront-homepage section.
     */
    public function scopeHomepageVisible(Builder $query): Builder
    {
        return $query->where('is_homepage_visible', 1);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function clearanceSale(): HasOne
    {
        return $this->hasOne(StockClearanceProduct::class, 'product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    //old relation: reviews_by_customer
    public function reviewsByCustomer(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id')->where('customer_id', auth('customer')->id())->whereNotNull('product_id')->whereNull('delivery_man_id');
    }

    public function digitalProductAuthors(): HasMany
    {
        return $this->hasMany(DigitalProductAuthor::class, 'product_id');
    }

    public function digitalProductPublishingHouse(): HasMany
    {
        return $this->hasMany(DigitalProductPublishingHouse::class, 'product_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function refundRequest(): HasMany
    {
        return $this->hasMany(RefundRequest::class, 'product_id', 'id');
    }

    public function scopeStatus($query): Builder
    {
        return $query->where('featured_status', 1);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'seller_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'user_id');
    }

    public function submissionWorkspace(): HasOne
    {
        return $this->hasOne(VendorProductWorkspace::class, 'product_id');
    }

    public function originalSubmission(): HasOne
    {
        return $this->hasOne(VendorProductSubmissionRevision::class, 'product_id')->oldestOfMany();
    }

    public function sellerProductEntitlements(): HasMany
    {
        return $this->hasMany(SellerProductEntitlement::class);
    }

    public function activeSellerProductEntitlement(): HasOne
    {
        return $this->hasOne(SellerProductEntitlement::class)
            ->where('status', SellerProductEntitlement::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latestOfMany();
    }

    public function sellerProductPromotions(): HasMany
    {
        return $this->hasMany(SellerProductPromotion::class);
    }

    public function reviewDecisions(): HasMany
    {
        return $this->hasMany(ProductReviewDecision::class)->latest('id');
    }

    public function activeSearchPromotion(): HasOne
    {
        return $this->hasOne(SellerProductPromotion::class)
            ->where('promotion_type', SellerProductPromotion::TYPE_SEARCH)
            ->where('status', SellerProductPromotion::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latestOfMany();
    }

    public function activeHomepagePromotion(): HasOne
    {
        return $this->hasOne(SellerProductPromotion::class)
            ->where('promotion_type', SellerProductPromotion::TYPE_HOMEPAGE)
            ->where('status', SellerProductPromotion::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latestOfMany();
    }

    public function getIsShopTemporaryCloseAttribute($value): int
    {
        $inHouseTemporaryClose = Cache::get(IN_HOUSE_SHOP_TEMPORARY_CLOSE_STATUS) ?? 0;
        if ($this->added_by == 'admin') {
            return $inHouseTemporaryClose ?? 0;
        } elseif ($this->added_by == 'seller') {
            return Cache::remember('product-shop-close-' . $this->id, 3600, function () {
                return $this?->seller?->shop?->temporary_close ?? 0;
            });
        }
        return 0;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    //old relation: sub_category
    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    //old relation: sub_sub_category
    public function subSubCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_sub_category_id');
    }

    public function rating(): HasMany
    {
        return $this->hasMany(Review::class)
            ->select(DB::raw('avg(rating) average, product_id'))
            ->whereNull('delivery_man_id')
            ->groupBy('product_id');
    }

    //old relation: order_details
    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'product_id');
    }

    public function seoInfo(): BelongsTo
    {
        return $this->belongsTo(ProductSeo::class, 'id', 'product_id')
            ->when(config('vendor_product_workspace.enabled') && app(\App\Services\VendorProductWorkspaceService::class)->sellerId(), fn ($query) => $query->whereRaw('1 = 0'));
    }

    //old relation: order_delivered
    public function orderDelivered(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'product_id')
            ->where('delivery_status', 'delivered');

    }

    //old relation: wish_list
    public function wishList(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'product_id');
    }

    public function digitalVariation(): HasMany
    {
        return $this->hasMany(DigitalProductVariation::class, 'product_id');
    }

    public function tags(): BelongsToMany
    {
        if (strpos(url()->current(), '/api')) {
            return $this->belongsToMany(Tag::class)->limit(5);
        }
        return $this->belongsToMany(Tag::class);
    }

    public function taxVats(): MorphMany
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }

    //old relation: flash_deal_product
    public function flashDealProducts(): HasMany
    {
        return $this->hasMany(FlashDealProduct::class);
    }

    public function scopeFlashDeal($query, $flashDealID)
    {
        return $query->whereHas('flashDealProducts.flashDeal', function ($query) use ($flashDealID) {
            return $query->where('id', $flashDealID);
        });
    }

    //old relation: compare_list
    public function compareList(): HasMany
    {
        return $this->hasMany(ProductCompare::class);
    }

    public function getNameAttribute($name): string|null
    {
        if (app(\App\Services\VendorProductWorkspaceService::class)->sellerId()) return $name;
        $segment = request()->segment(1);
        if ($segment === 'api') {
            return $this->translations[0]->value ?? $name;
        }
        if (in_array($segment, ['admin', 'vendor', 'seller'], true)) {
            return $name;
        }
        return $this->translations[0]->value ?? $name;
    }

    public function getDetailsAttribute($detail): string|null
    {
        if (app(\App\Services\VendorProductWorkspaceService::class)->sellerId()) return $detail;
        $segment = request()->segment(1);
        if ($segment === 'api') {
            return $this->translations[1]->value ?? $detail;
        }
        if (in_array($segment, ['admin', 'vendor', 'seller'], true)) {
            return $detail;
        }
        return $this->translations[1]->value ?? $detail;
    }

    public function getThumbnailFullUrlAttribute(): string|null|array
    {
        $value = $this->thumbnail;
        return $this->storageLink('product/thumbnail', $value, $this->thumbnail_storage_type ?? 'public');
    }

    public function getPreviewFileFullUrlAttribute(): string|null|array
    {
        $value = $this->preview_file;
        return $this->storageLink('product/preview', $value, $this->preview_file_storage_type ?? 'public');
    }

    public function getMetaImageFullUrlAttribute(): array
    {
        $value = $this->meta_image;
        return $this->storageLink('product/meta', $value, 'public');
    }

    public function getDigitalFileReadyFullUrlAttribute(): array
    {
        $value = $this->digital_file_ready;
        return $this->storageLink('product/digital-product', $value, $this->digital_file_ready_storage_type ?? 'public');
    }

    public function getColorImagesFullUrlAttribute(): array
    {
        $images = [];
        $value = is_array($this->color_image) ? $this->color_image : json_decode($this->color_image);
        if ($value) {
            foreach ($value as $item) {
                $item = (array)$item;
                $images[] = [
                    'color' => $item['color'],
                    'image_name' => $this->storageLink('product', $item['image_name'], $item['storage'] ?? 'public')
                ];
            }
        }
        return $images;
    }

    public function getImagesFullUrlAttribute(): array
    {
        $images = [];
        $value = is_array($this->images) ? $this->images : json_decode($this->images);
        if ($value) {
            foreach ($value as $item) {
                $item = isset($item->image_name) ? (array)$item : ['image_name' => $item, 'storage' => 'public'];
                $images[] = $this->storageLink('product', $item['image_name'], $item['storage'] ?? 'public');
            }
        }
        return $images;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('display_group_order', function (Builder $query) {
            $service = app(\App\Services\ProductDisplayOrderService::class);
            $group = $service->context(request());
            if ($group) $service->query($query, $service->ids($group));
        });

        static::retrieved(function ($product) {
            app(\App\Services\VendorProductWorkspaceService::class)->projectForSeller($product);
        });
        static::created(function ($product) {
            if (config('vendor_product_workspace.enabled') && $product->added_by === 'seller') {
                app(\App\Services\VendorProductWorkspaceService::class)->capture($product);
            }
            app(\App\Services\VendorSlotReservationService::class)->attachCreated($product);
        });

        static::creating(function ($model) {
            if ($model->added_by === 'seller') {
                app(\App\Services\VendorAdvertisingAccessService::class)->assertCanCreate((int) $model->user_id);
                app(\App\Services\VendorSlotReservationService::class)->assertCreation((int) $model->user_id);
            }
        });

        static::updating(function ($model) {
            $workspace = app(\App\Services\VendorProductWorkspaceService::class);
            if ($workspace->enabled() && $workspace->sellerId() && !request()->attributes->get('vendor_workspace_financial_context') && $model->added_by === 'seller') {
                $protected = array_intersect(array_keys($model->getDirty()), \App\Services\VendorProductWorkspaceService::CONTENT);
                $protected = array_diff($protected, ['current_stock']);
                if ($protected) throw \Illuminate\Validation\ValidationException::withMessages(['product' => 'تعديلات المحتوى تُرسل للمراجعة من نموذج تعديل المنتج.']);
            }
            if ($workspace->enabled() && !$workspace->sellerId() && request()->segment(1) === 'admin' && $model->added_by === 'seller' && !$model->workspacePublicationWrite) {
                $protected = array_diff(array_intersect(array_keys($model->getDirty()), \App\Services\VendorProductWorkspaceService::CONTENT), ['current_stock']);
                if ($protected) throw \Illuminate\Validation\ValidationException::withMessages(['product' => 'احفظ نسخة التحرير من نموذج المنتج قبل اعتماد النشر.']);
            }

            if ((config('vendor_advertising.enabled') || config('vendor_product_workspace.enabled')) && $model->added_by === 'seller' && $model->isDirty('status') && (int) $model->status === 1) {
                if ($workspace->enabled() && $workspace->sellerId() && ($workspace->state($model)?->publication_suspended || in_array($workspace->state($model)?->review_state, ['suspended', 'rejected', 'deleted'], true))) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['publication' => 'يلزم اعتماد الإدارة قبل إعادة نشر هذا الإعلان.']);
                }
                if ((int) $model->request_status !== 1 || !app(\App\Services\VendorAdvertisingAccessService::class)->visible($model)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['publication' => 'يلزم اعتماد الإدارة ومساحة إعلانية سارية لنشر المنتج.']);
                }
            }
        });

        static::saved(function ($model) {
            if (!$model->public_code && (int) $model->status === 1 && (int) $model->request_status === 1
                && !app(\App\Services\VendorProductWorkspaceService::class)->sellerId()
                && \Illuminate\Support\Facades\Schema::hasTable('public_product_code_sequences')) {
                app(\App\Services\PublicProductCodeService::class)->assign($model);
            }
            cacheRemoveByType(type: 'products');
        });

        static::deleted(function ($model) {
            cacheRemoveByType(type: 'products');
        });

        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                if (strpos(url()->current(), '/api')) {
                    return $query->where('locale', App::getLocale());
                } else {
                    return $query->where('locale', getDefaultLanguage());
                }
            }, 'reviews' => function ($query) {
                $segment = request()->segment(1);
                $query->whereNull('delivery_man_id')->when(!in_array($segment, ['admin', 'vendor', 'seller'], true), function ($query) use ($segment) {
                    return $query->active();
                });
            }]);
        });
    }
}
