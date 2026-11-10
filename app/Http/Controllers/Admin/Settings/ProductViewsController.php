<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Seller;
use App\Services\ProductViewCountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductViewsController extends Controller
{
    public function index(Request $request, ProductViewCountService $counts)
    {
        $data = $request->validate(['vendor_id' => 'nullable|integer|exists:sellers,id',
            'vendor_q' => 'nullable|string|max:100', 'product_q' => 'nullable|string|max:100']);
        $vendorQuery = trim($data['vendor_q'] ?? '');
        $vendors = $vendorQuery === '' ? collect() : Seller::query()->where(function ($q) use ($vendorQuery) {
            $q->where('f_name', 'like', "%{$vendorQuery}%")->orWhere('l_name', 'like', "%{$vendorQuery}%")
                ->orWhere('phone', 'like', "%{$vendorQuery}%")->orWhere('email', 'like', "%{$vendorQuery}%");
        })->limit(20)->get(['id', 'f_name', 'l_name', 'phone', 'email']);
        $vendor = isset($data['vendor_id']) ? Seller::find($data['vendor_id']) : null;
        $products = $this->products($data)->select(['id', 'name', 'user_id'])->orderByDesc('id')
            ->paginate(25)->withQueryString();
        return view('admin-views.business-settings.product-views', [
            'vendors' => $vendors, 'vendor' => $vendor, 'products' => $products,
            'counts' => $counts->totalsForProducts($products->pluck('id')->all()),
            'preview' => session('product_view_preview'),
        ]);
    }

    public function preview(Request $request, ProductViewCountService $counts)
    {
        $data = $request->validate(['vendor_id' => 'required|integer|exists:sellers,id',
            'product_q' => 'nullable|string|max:100', 'selection' => 'required|in:selected,page,matching',
            'page' => 'nullable|integer|min:1',
            'product_ids' => 'required_if:selection,selected|array|max:500',
            'product_ids.*' => 'integer|distinct', 'amount' => 'required|integer|min:1|max:1000000',
            'reason' => 'required|string|min:5|max:500']);
        $query = $this->products($data);
        if ($data['selection'] === 'selected') $query->whereIn('id', $data['product_ids'] ?? []);
        if ($data['selection'] === 'page') {
            $pageIds = $this->products($data)->orderByDesc('id')
                ->paginate(25, ['id'], 'page', (int) ($data['page'] ?? 1))->pluck('id')->all();
            $query->whereIn('id', $pageIds);
        }
        $products = $query->orderBy('id')->get(['id', 'name', 'user_id']);
        if ($products->isEmpty() || ($data['selection'] === 'selected' && $products->count() !== count($data['product_ids'] ?? []))) {
            throw ValidationException::withMessages(['selection' => 'اختر منتجات تابعة للتاجر المحدد.']);
        }
        $totals = $counts->totalsForProducts($products->pluck('id')->all());
        $preview = ['key' => (string) Str::uuid(), 'vendor_id' => (int) $data['vendor_id'],
            'selection' => $data['selection'], 'amount' => (int) $data['amount'], 'reason' => trim($data['reason']),
            'products' => $products->map(fn ($item) => ['id' => $item->id, 'name' => $item->name,
                'actual' => $totals[$item->id]['actual'], 'added' => $totals[$item->id]['added'],
                'total' => $totals[$item->id]['total'], 'result' => $totals[$item->id]['total'] + (int) $data['amount']])->all(),
            'created_at' => now()->timestamp];
        $request->session()->put('product_view_preview', $preview);
        return redirect()->route('admin.settings.product-views.index', ['vendor_id' => $data['vendor_id'],
            'product_q' => $data['product_q'] ?? null, 'page' => $data['page'] ?? 1]);
    }

    public function apply(Request $request, ProductViewCountService $counts)
    {
        $data = $request->validate(['key' => 'required|uuid']);
        $preview = $request->session()->get('product_view_preview');
        abort_unless($preview && hash_equals($preview['key'], $data['key'])
            && now()->timestamp - $preview['created_at'] <= 900, 422);
        $ids = array_column($preview['products'], 'id');
        DB::transaction(function () use ($preview, $ids, $counts) {
            $inserted = DB::table('product_view_adjustment_batches')->insertOrIgnore([
                'request_key' => $preview['key'], 'admin_id' => auth('admin')->id(),
                'vendor_id' => $preview['vendor_id'], 'amount' => $preview['amount'],
                'product_count' => count($ids), 'reason' => $preview['reason'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if (!$inserted) return;
            $batch = DB::table('product_view_adjustment_batches')->where('request_key', $preview['key'])->first();
            $validIds = Product::withoutGlobalScopes()->where('added_by', 'seller')
                ->where('user_id', $preview['vendor_id'])->whereIn('id', $ids)->lockForUpdate()->pluck('id');
            if ($validIds->count() !== count($ids)) throw ValidationException::withMessages(['selection' => 'تغيرت ملكية المنتجات؛ أعد المعاينة.']);
            $current = $counts->totalsForProducts($ids);
            foreach ($preview['products'] as $product) {
                if ($current[$product['id']]['total'] !== $product['total']) {
                    throw ValidationException::withMessages(['selection' => 'تغير عدد المشاهدات؛ أعد المعاينة قبل الحفظ.']);
                }
            }
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('product_view_adjustments')->insert(array_map(fn ($id) => [
                    'product_id' => $id, 'admin_id' => auth('admin')->id(), 'amount' => $preview['amount'],
                    'reason' => $preview['reason'], 'batch_id' => $batch->id,
                    'created_at' => now(), 'updated_at' => now(),
                ], $chunk));
            }
        }, 3);
        $request->session()->forget('product_view_preview');
        return redirect()->route('admin.settings.product-views.index', ['vendor_id' => $preview['vendor_id']])
            ->with('success', 'أُضيفت الزيادة المستقلة لكل منتج مع تسجيل المسؤول والسبب.');
    }

    private function products(array $filters)
    {
        $query = Product::withoutGlobalScopes()->where('added_by', 'seller')->toBase();
        if (!empty($filters['vendor_id'])) $query->where('user_id', $filters['vendor_id']);
        if (!empty($filters['product_q'])) {
            $q = trim($filters['product_q']);
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$q}%")
                ->orWhere('id', ctype_digit($q) ? (int) $q : -1));
        }
        return $query;
    }
}
