<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\VendorProductWorkspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorProductReviewQueueController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'state' => ['nullable', 'in:pending,submitted,under_review,approved_published,suspended,rejected,deleted'],
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $state = $filters['state'] ?? 'pending';
        $submissions = VendorProductWorkspace::query()->whereIn('review_state', $state === 'pending' ? ['submitted', 'under_review'] : [$state])
            ->when($filters['seller_id'] ?? null, fn ($q, $id) => $q->where('seller_id', $id))
            ->orderByDesc('submitted_at')->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(20)->withQueryString();
        $products = Product::withoutGlobalScopes()->with('seller')
            ->whereIn('id', $submissions->pluck('product_id'))->get()->keyBy('id');

        $legacyProducts = Product::withoutGlobalScopes()->with('seller')
            ->where('added_by', 'seller')->where('request_status', 0)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('vendor_product_workspaces as w')->whereColumn('w.product_id', 'products.id'))
            ->when($filters['seller_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when(!in_array($state, ['pending', 'submitted'], true), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('id')->paginate(20, ['*'], 'legacy_page')->withQueryString();

        return view('admin-views.product.review-queue.workspace', compact('submissions', 'products', 'state', 'legacyProducts'));
    }
}
