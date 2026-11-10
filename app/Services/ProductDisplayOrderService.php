<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductDisplayOrderService
{
    private array $loaded = [];
    private ?bool $ready = null;

    public function ids(string $group): array
    {
        if (!($this->ready ??= Schema::hasTable('product_display_orders'))) return [];
        return $this->loaded[$group] ??= array_values(array_unique(array_filter(array_map('intval',
            json_decode(DB::table('product_display_orders')->where('group_key', $group)->value('product_ids') ?? '[]', true) ?: []
        ), fn ($id) => $id > 0)));
    }

    public function context(Request $request): ?string
    {
        if (!in_array($request->method(), ['GET', 'POST'], true)) return null;
        if ($request->filled('sort_by') && !in_array($request->input('sort_by'), ['default', 'recommended', 'latest'], true)) return null;
        $path = $request->path();
        $web = $request->is('products', 'searched-products', 'ajax-filter-products', 'category/*',
            'latest-products','featured-products','top-rated-products','best-selling-products','most-favorite-products','discounted-products');
        $api = $request->is('api/v1/products/latest', 'api/v1/products/new-arrival', 'api/v1/products/featured',
            'api/v1/products/top-rated', 'api/v1/products/best-sellings', 'api/v1/products/search',
            'api/v1/products/filter', 'api/v1/categories/products/*');
        if (!$web && !$api) return null;
        $category = $request->input('sub_sub_category_id') ?: $request->input('sub_category_id') ?: $request->input('category_id')
            ?: ($request->route('category_id') ?? $request->route('id'));
        if (preg_match('~api/v1/categories/products/(\d+)$~', $path, $match)) $category = $match[1];
        if (!$category) {
            $categories = $request->input('category_ids', $request->input('category', []));
            if (is_string($categories)) $categories = json_decode($categories, true) ?: [];
            if (is_array($categories) && count($categories) === 1) $category = reset($categories);
        }
        if ($category && ctype_digit((string)$category)) return 'category:'.(int)$category;
        return $request->filled('search') || $request->filled('name') || $request->is('*/search', 'searched-products') ? 'search' : 'catalog';
    }

    /** Use the category preference for related products, falling back to the catalog. */
    public function relatedQuery(Builder $query, ?int $categoryId): Builder
    {
        $ids = $categoryId ? $this->ids('category:'.$categoryId) : [];
        return $this->query($query, $ids ?: $this->ids('catalog'))->orderBy('products.id');
    }

    /** Prepend preference before pagination, retaining the existing sort as its tie-breaker. */
    public function query(Builder $query, array $ids): Builder
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if (!$ids) return $query;
        $sql = 'CASE products.id';
        foreach ($ids as $rank => $id) $sql .= ' WHEN '.$id.' THEN '.$rank;
        $sql .= ' ELSE 2147483647 END asc';
        $base = $query->getQuery();
        $base->orders = array_merge([['type' => 'Raw', 'sql' => $sql]], $base->orders ?? []);
        return $query;
    }

    public function sort(Collection $products, ?string $group): Collection
    {
        $ids = $group ? $this->ids($group) : [];
        if (!$ids) return $products;
        $ranks = array_flip($ids); $positions = $products->values()->mapWithKeys(fn ($product, $index) => [$product->id => $index]);
        return $products->sort(function ($a, $b) use ($ranks, $positions) {
            // Paid placements retain their already-computed relative order.
            if ($a->is_search_promoted || $b->is_search_promoted) {
                if ($a->is_search_promoted && $b->is_search_promoted) return $positions[$a->id] <=> $positions[$b->id];
                return (int)$b->is_search_promoted <=> (int)$a->is_search_promoted;
            }
            return ($ranks[$a->id] ?? PHP_INT_MAX) <=> ($ranks[$b->id] ?? PHP_INT_MAX)
                ?: $positions[$a->id] <=> $positions[$b->id];
        })->values();
    }

    public function save(string $group, array $ids, int $adminId): void
    {
        abort_unless(in_array($group, ['catalog', 'search'], true) || preg_match('/^category:[1-9][0-9]*$/D', $group), 422);
        DB::transaction(function () use ($group, $ids, $adminId) {
            DB::table('product_display_orders')->insertOrIgnore(['group_key'=>$group, 'product_ids'=>'[]', 'created_at'=>now(), 'updated_at'=>now()]);
            DB::table('product_display_orders')->where('group_key',$group)->lockForUpdate()->first();
            DB::table('product_display_orders')->where('group_key',$group)->update([
                'product_ids'=>json_encode(array_values(array_unique(array_map('intval',$ids)))), 'updated_by_admin_id'=>$adminId, 'updated_at'=>now(),
            ]);
        });
        unset($this->loaded[$group]);
        cacheRemoveByType(type:'products');
    }
}
