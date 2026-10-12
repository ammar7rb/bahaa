<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CustomerOrderPaymentRetryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderPaymentRetryController extends Controller
{
    public function submit(Request $request, int $order)
    {
        $customerId = (int) ($request->is('api/*') ? $request->user('api')->id : auth('customer')->id());
        Order::where('customer_id', $customerId)->where('is_guest', 0)->findOrFail($order);
        $sender = $request->input('transfer_schema') === 'sender_v2'
            ? app(\App\Services\CustomerTransferInformationService::class)->sender($request->all()) : [];
        if ($sender) $request->merge(['payment_reference' => 'order-transfer-'.$customerId.':'.$request->input('request_key')]);
        $data = $request->validate(['request_key' => 'required|uuid', 'payment_reference' => 'required|string|max:191',
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $path = 'customer-order-retries/'.Str::uuid().'.enc';
        if (!Storage::disk('local')->put($path, Crypt::encryptString(file_get_contents($request->file('payment_proof')->getRealPath())))) {
            throw ValidationException::withMessages(['payment_proof' => 'تعذر حفظ إثبات الدفع. حاول مرة أخرى.']);
        }
        try {
            $used = app(CustomerOrderPaymentRetryService::class)->submit($customerId, $order, $data['request_key'], trim($data['payment_reference']), $path, $sender);
            if (!$used) Storage::disk('local')->delete($path);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        return $request->is('api/*') ? response()->json(['message' => 'الدفع قيد المراجعة'])
            : back()->with('payment_retry_message', 'الدفع قيد المراجعة');
    }

    public function proof(int $order)
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('orders'), 403);
        $info = Order::findOrFail($order)->offlinePayments?->payment_info ?: [];
        $path = $info['private_retry_proof'] ?? '';
        abort_unless(str_starts_with($path, 'customer-order-retries/') && !str_contains($path, '..') && Storage::disk('local')->exists($path), 404);
        $content = Crypt::decryptString(Storage::disk('local')->get($path));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        abort_unless(in_array($mime, ['image/jpeg','image/png','image/webp'], true), 404);
        return response($content)->header('Content-Type', $mime)->header('Cache-Control','private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
