<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CreditRequest;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\SupportTicketConv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductCreditRequestController extends Controller
{
    public function index(): View
    {
        $products = Product::query()->where('added_by', 'admin')->where('status', 1)->where('request_status', 1)
            ->where('is_credit_listing', true)
            ->where(fn ($query) => $query->where('product_type', 'digital')->orWhere('current_stock', '>', 0))
            ->latest('id')->paginate(20);

        return view(theme_root_path() === 'theme_aster' ? 'theme-views.credit-products' : 'web-views.credit-products', compact('products'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:189']]);
        abort_unless($product->added_by === 'admin' && (bool) $product->is_credit_listing
            && (int) $product->request_status === 1 && (int) $product->status === 1, 404);
        if ($product->product_type === 'physical' && (int) $product->current_stock <= 0) {
            return back()->with('credit_request_notice', 'المنتج غير متاح حاليًا.');
        }
        if (checkVendorAbility(type: 'inhouse', status: 'temporary_close')
            || checkVendorAbility(type: 'inhouse', status: 'vacation_status')) {
            return back()->with('credit_request_notice', 'المتجر غير متاح حاليًا.');
        }

        $customerId = (int) auth('customer')->id();
        $activeKey = $customerId.':'.$product->id;
        if (CreditRequest::where('active_key', $activeKey)->exists()) {
            return back()->with('credit_request_notice', 'لديك طلب مفتوح لهذا المنتج بالفعل.');
        }

        try {
            $credit = DB::transaction(function () use ($product, $customerId, $data, $activeKey) {
                $ticket = SupportTicket::create([
                    'customer_id' => $customerId,
                    'subject' => mb_substr('طلب ائتمان: '.$product->name, 0, 180),
                    'type' => 'credit_request',
                    'purpose' => 'credit_request',
                    'priority' => 'medium',
                    'description' => 'طلب ائتمان للمنتج '.$product->public_code,
                    'status' => 'open',
                ]);
                if (! empty($data['message'])) {
                    SupportTicketConv::create([
                        'support_ticket_id' => $ticket->id,
                        'customer_message' => $data['message'],
                    ]);
                }

                $credit = CreditRequest::create([
                    'reference' => 'CR-'.Str::upper(Str::random(12)),
                    'customer_id' => $customerId,
                    'product_id' => $product->id,
                    'support_ticket_id' => $ticket->id,
                    'product_code' => $product->public_code,
                    'requested_product_price' => $product->unit_price,
                    'customer_message' => $data['message'] ?? null,
                    'status' => 'submitted',
                    'active_key' => $activeKey,
                ]);

                return $credit;
            }, 3);
        } catch (\Illuminate\Database\QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23505', '19'], true)) {
                return back()->with('credit_request_notice', 'لديك طلب مفتوح لهذا المنتج بالفعل.');
            }
            throw $exception;
        }

        return back()->with('credit_request_notice', 'تم استلام طلب الائتمان رقم '.$credit->reference.' وسنتواصل معك.');
    }
}
