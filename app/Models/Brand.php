<?php

namespace App\Models;

use App\Traits\CacheManagerTrait;
use App\Traits\StorageTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\App;

/**
 * @property int $id
 * @property string $name
 * @property string $image
 * @property int $status
 */
class Brand extends Model
{
    use StorageTrait, CacheManagerTrait;

    protected $fillable = [
        'name',
        'slug',
        'image',
        'image_storage_type',
        'image_alt_text',
        'status'
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
        'image' => 'string',
        'image_storage_type' => 'string',
        'image_alt_text' => 'string',
        'status' => 'integer',
        'brand_products_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive(): mixed
    {
        return $this->where('status', 1);
    }

    public function brandProducts(): HasMany
    {
        return $this->hasMany(Product::class)->active();
    }

    public function brandAllProducts(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function translations(): MorphMany
    {
        return $this->morphMany('App\Models\Translation', 'translationable');
    }

    public function seo(): MorphOne
    {
        return $this->morphOne('App\Models\SeoMetaInfo', 'seoable');
    }

    public function getNameAttribute($name): string|null
    {
        $segment = request()->segment(1);
        if (in_array($segment, ['admin', 'vendor', 'seller'], true)) {
            return $name;
        }
        return $this->translatedValue() ?? $name;
    }

    public function getDefaultNameAttribute(): string|null
    {
        return $this->translatedValue() ?? $this->name;
    }

    private function translatedValue(): ?string
    {
        $translation = $this->translations->get(0);
        $value = $translation?->value;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function storage(): MorphMany
    {
        return $this->morphMany(Storage::class, 'data');
    }

    public function getImageFullUrlAttribute(): array
    {
        $value = $this->image;
        return $this->storageLink('brand', $value, $this->image_storage_type ?? 'public');
    }

    protected $appends = ['image_full_url'];

    protected static function boot(): void
    {
        parent::boot();

        static::saved(function ($model) {
            cacheRemoveByType(type: 'brands');
        });

        static::deleted(function ($model) {
            cacheRemoveByType(type: 'brands');
        });

        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                if (strpos(url()->current(), '/api')) {
                    return $query
                        ->where('locale', App::getLocale());
                } else {
                    return $query->where('locale', getDefaultLanguage());
                }
            }]);
        });
    }
}
