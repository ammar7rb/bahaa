<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Http\Controllers\Controller;
use App\Services\HomepageEditorialService;
use App\Services\HomepageSectionBuilderService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomepageSectionsController extends Controller
{
    private function theme(Request $request): string
    {
        return $request->validate(['theme' => 'nullable|in:default,theme_aster,theme_sigma_care'])['theme'] ?? theme_root_path();
    }

    public function index(Request $request, HomepageSectionBuilderService $builder, HomepageEditorialService $editorial)
    {
        $theme = $this->theme($request);
        $request->validate(['q' => 'nullable|string|max:100', 'vendor' => 'nullable|integer|min:1', 'category' => 'nullable|integer|min:1']);
        $draft = $builder->ensureDraft($theme, auth('admin')->id());
        $definitions = $builder->operationsProductCatalog($theme);
        $sections = $draft->sections->whereIn('section_key', $definitions->keys())->sortBy('sort_order');
        $assigned = \App\Models\Product::with('seller.shop')->whereIn('id', $sections->flatMap(fn ($s) => $s->product_ids ?? [])->unique())->get()->keyBy('id');
        $products = $editorial->eligibleProducts()->with('seller.shop')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('id', $term)->orWhereHas('seller', fn ($q) => $q->where('f_name', 'like', '%'.$term.'%')->orWhere('l_name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))))
            ->when($request->vendor, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->category, fn ($q, $id) => $q->where('category_id', $id))->latest('id')->paginate(20)->withQueryString();
        if ($request->expectsJson()) {
            return response()->json(['html' => view('admin-views.operations._eligible-products', compact('products'))->render()]);
        }
        $categories = \App\Models\Category::where('position', 0)->get();

        return view('admin-views.operations.homepage-sections', compact('theme', 'draft', 'definitions', 'sections', 'assigned', 'products', 'categories', 'builder'));
    }

    public function save(Request $request, HomepageSectionBuilderService $builder, HomepageEditorialService $editorial)
    {
        $theme = $this->theme($request);
        $data = $request->validate(['key' => 'nullable|string|max:80', 'title' => 'required|string|max:180',
            'is_visible' => 'required|boolean', 'sort_order' => 'required|integer|min:1|max:10000',
            'source_mode' => 'required|in:automatic,manual,mixed', 'product_ids' => 'nullable|array|max:50', 'product_ids.*' => 'integer|distinct|min:1']);
        $ids = array_map('intval', $data['product_ids'] ?? []);
        $eligible = $editorial->eligibleProducts()->whereIn('id', $ids)->pluck('id')->all();
        if (array_diff($ids, $eligible)) {
            return back()->withErrors(['product_ids' => __('operations.ineligible')])->withInput();
        }
        DB::transaction(function () use ($builder, $theme, $data, $ids) {
            $draft = $builder->ensureDraft($theme, auth('admin')->id());
            $key = $data['key'] ?? null;
            if (! $key) {
                $key = 'custom_'.Str::lower(Str::random(16));
                $draft->sections()->create(['section_key' => $key, 'is_visible' => false, 'sort_order' => $data['sort_order'],
                    'source_mode' => 'manual', 'automatic_source' => 'existing', 'locale' => 'all', 'settings' => ['title' => $data['title'], 'editorial' => true, 'limit' => 50]]);
            }
            $definition = $builder->operationsProductCatalog($theme)->get($key);
            abort_unless($definition && $definition['type'] === 'product' && $key !== 'paid_ads', 422);
            $section = $draft->sections()->where('section_key', $key)->firstOrFail();
            $settings = array_merge($section->settings ?? [], ['title' => $data['title'], 'editorial' => true]);
            $section->update(['is_visible' => $data['is_visible'], 'sort_order' => $data['sort_order'],
                'source_mode' => str_starts_with($key, 'custom_') ? 'manual' : $data['source_mode'], 'product_ids' => $ids, 'settings' => $settings]);
            \Illuminate\Support\Facades\Cache::forget('homepage-layout-published-'.$theme);
        });

        return redirect()->route('admin.operations.homepage-sections.index', ['theme' => $theme])->with('operations_success', __('operations.draft_saved'));
    }

    public function publish(Request $request, HomepageSectionBuilderService $builder)
    {
        try {
            $builder->publish($this->theme($request), (int) auth('admin')->id());
        } catch (DomainException $e) {
            return back()->withErrors(['publish' => translate($e->getMessage())]);
        }

        return back()->with('operations_success', __('operations.published'));
    }
}
