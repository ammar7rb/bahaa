<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\CreditRequest;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\SupportTicketConv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditProductApiController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::query()->where('added_by', 'admin')->where('status', 1)->where('request_status', 1)
            ->where('is_credit_listing', true)
            ->latest('id')->paginate(20);

        return response()->json($products);
    }

    public function store(Request $request, int $product): JsonResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:189']]);
        $product = Product::query()->where('added_by', 'admin')->where('is_credit_listing', true)
            ->where('request_status', 1)->where('status', 1)->findOrFail($product);
        $customerId = (int) $request->user()->id;
        $activeKey = $customerId.':'.$product->id;
        if (CreditRequest::where('active_key', $activeKey)->exists()) {
            return response()->json(['message' => 'لديك طلب مفتوح لهذا المنتج بالفعل.'], 409);
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
                if (!empty($data['message'])) {
                    SupportTicketConv::create([
                        'support_ticket_id' => $ticket->id,
                        'customer_message' => $data['message'],
                    ]);
                }

                return CreditRequest::create([
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
            }, 3);
        } catch (\Illuminate\Database\QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23505', '19'], true)) {
                return response()->json(['message' => 'لديك طلب مفتوح لهذا المنتج بالفعل.'], 409);
            }
            throw $exception;
        }

        return response()->json([
            'message' => 'تم استلام طلب الائتمان رقم '.$credit->reference.' وسنتواصل معك.',
            'request' => $credit,
        ], 201);
    }
}
