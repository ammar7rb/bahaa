<?php

namespace App\Http\Controllers\Admin\Product;

use App\Models\Product;
use App\Services\HomepageSectionBuilderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\RedirectResponse;
use DomainException;

class AllProductsController extends Controller
{
    public function place(Request $request, Product $product, HomepageSectionBuilderService $homepage): RedirectResponse
    {
        $data = $request->validate(['sections' => ['nullable', 'array'], 'sections.*' => ['string', 'max:100']]);
        if ((int) $product->request_status !== 1 || (int) $product->status !== 1) {
            return back()->withErrors(['sections' => 'يجب نشر المنتج وإظهاره قبل تحديد موضعه.']);
        }
        $allowed = collect($homepage->productPlacementSections(theme_root_path()))->pluck('key')->all();
        $sections = $data['sections'] ?? [];
        if (array_diff($sections, $allowed)) {
            return back()->withErrors(['sections' => 'القسم المحدد غير متاح.']);
        }
        try {
            $homepage->placeProductInSections(theme_root_path(), (int) $product->id, $sections, (int) auth('admin')->id());
        } catch (DomainException $exception) {
            return back()->withErrors(['sections' => 'تعذر حفظ مواضع المنتج في الصفحة الرئيسية.']);
        }
        $product->forceFill(['is_homepage_visible' => count($sections) > 0])->save();

        return back()->with('status', 'تم تحديث مواضع المنتج.');
    }

    public function order(Request $request, HomepageSectionBuilderService $homepage): View
    {
        $sections = collect($homepage->productPlacementSections(theme_root_path()));
        $selected = $sections->firstWhere('key', $request->query('section')) ?? $sections->first();
        $ids = $selected['product_ids'] ?? [];
        $products = Product::query()->withoutGlobalScopes()->whereIn('id', $ids)->get()->keyBy('id');

        return view('admin-views.product.order', compact('sections', 'selected', 'ids', 'products'));
    }

    public function saveOrder(Request $request, HomepageSectionBuilderService $homepage): RedirectResponse
    {
        $data = $request->validate([
            'section' => ['required', 'string', 'max:100'],
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
        $ranked = collect($data['order'])->map(fn ($rank, $id) => ['id' => (int) $id, 'rank' => (int) $rank])
            ->sortBy([['rank', 'asc'], ['id', 'asc']])->pluck('id')->all();

        try {
            $homepage->reorderSectionProducts(theme_root_path(), $data['section'], $ranked, (int) auth('admin')->id());
        } catch (DomainException $exception) {
            return back()->withErrors(['order' => 'تعذر حفظ الترتيب؛ أعد تحميل الصفحة ثم حاول مجددًا.']);
        }

        return back()->with('status', 'تم تحديث ترتيب المنتجات في القسم.');
    }

    public function all(Request $request, HomepageSectionBuilderService $homepage): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'owner' => ['nullable', 'in:seller,admin'],
            'visibility' => ['nullable', 'in:visible,hidden,pending'],
        ]);

        $products = Product::query()
            ->withoutGlobalScopes()
            ->with(['seller.shop', 'category'])
            ->whereIn('added_by', ['seller', 'admin'])
            ->when($filters['q'] ?? null, function ($query, $term) {
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', '%'.$term.'%')
                        ->orWhere('public_code', 'like', '%'.$term.'%')
                        ->orWhere('code', 'like', '%'.$term.'%');
                });
            })
            ->when($filters['owner'] ?? null, fn ($query, $owner) => $query->where('added_by', $owner))
            ->when($filters['visibility'] ?? null, function ($query, $visibility) {
                if ($visibility === 'visible') {
                    $query->where('request_status', 1)->where('status', 1);
                } elseif ($visibility === 'hidden') {
                    $query->where('request_status', 1)->where('status', 0);
                } else {
                    $query->where('request_status', '!=', 1);
                }
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $placements = collect($homepage->productPlacementSections(theme_root_path()));

        return view('admin-views.product.all-products', compact('products', 'placements'));
    }
}
