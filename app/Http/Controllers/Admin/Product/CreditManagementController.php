<?php

namespace App\Http\Controllers\Admin\Product;

use App\Models\CreditRequest;
use App\Models\Product;
use App\Models\SupportTicketConv;
use App\Services\PublicProductCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CreditManagementController extends Controller
{
    public function listings(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $products = Product::query()->withoutGlobalScopes()->where('added_by', 'admin')
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('name', 'like', '%'.$term.'%'))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin-views.product.credit-listings', compact('products'));
    }

    public function toggleListing(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        abort_unless($product->added_by === 'admin', 403);
        if ((bool) $data['enabled']) {
            abort_unless((int) $product->request_status === 1 && (int) $product->status === 1, 422);
            app(PublicProductCodeService::class)->assign($product);
        }
        $product->forceFill(['is_credit_listing' => (bool) $data['enabled']])->save();

        return back()->with('status', 'تم تحديث عرض المنتج في صفحة الائتمان.');
    }

    public function requests(Request $request): View
    {
        $filter = $request->validate(['status' => ['nullable', 'in:submitted,responded']]);
        $requests = CreditRequest::query()->with(['customer', 'product', 'responder'])
            ->when($filter['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin-views.product.credit-requests', compact('requests'));
    }

    public function respond(Request $request, CreditRequest $creditRequest): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:189']]);
        DB::transaction(function () use ($creditRequest, $data) {
            $locked = CreditRequest::query()->lockForUpdate()->findOrFail($creditRequest->id);
            if ($locked->status !== 'submitted') {
                abort(409, 'تمت معالجة الطلب بالفعل.');
            }
            $adminId = (int) auth('admin')->id();
            SupportTicketConv::create([
                'support_ticket_id' => $locked->support_ticket_id,
                'admin_id' => $adminId,
                'admin_message' => $data['response'],
            ]);
            $locked->update([
                'status' => 'responded', 'active_key' => null, 'admin_response' => $data['response'],
                'responded_by_admin_id' => $adminId, 'responded_at' => now(),
            ]);
        });

        return back()->with('status', 'تم إرسال الرد للعميل وتسجيل اسم المسؤول.');
    }
}
