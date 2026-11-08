<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Models\OrderWorkItem;
use App\Services\OrderItemReassignmentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderItemReassignmentController extends Controller
{
    public function show(Request $request, int $item, OrderItemReassignmentService $service): View
    {
        $request->validate(['q' => ['nullable','string','max:100']]);
        $workItem = OrderWorkItem::query()->with(['order.customer','line','seller.shop'])->findOrFail($item);
        abort_unless(in_array($workItem->status, ['sent_to_vendor','accepted','overdue','rejected','canceled'], true), 404);
        $candidates = $service->candidates($workItem, trim((string) $request->query('q')));
        return view('admin-views.order.center.reassign', compact('workItem', 'candidates'));
    }

    public function store(Request $request, int $item, OrderItemReassignmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'target_product_id' => ['required','integer','exists:products,id'],
            'reason' => ['required','string','min:5','max:2000'],
        ]);
        try {
            $replacement = $service->transfer($item, (int) $data['target_product_id'],
                (int) auth('admin')->id(), trim($data['reason']));
            return redirect()->route('admin.orders.admin-gate.show', $replacement->id)
                ->with('success', 'تم نقل المنتج إلى طلب التاجر البديل. راجع السعر والشحن ثم أرسله.');
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['reassignment' => $exception->getMessage()]);
        }
    }
}
