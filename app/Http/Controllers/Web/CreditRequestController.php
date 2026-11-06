<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CreditRequestController extends Controller
{
    public function store(): JsonResponse
    {
        abort_unless(auth('customer')->check(), 401);
        $customerId = auth('customer')->id();
        $result = DB::transaction(function () use ($customerId) {
            // Serialize requests for this customer so repeated clicks create one open request.
            DB::table('users')->where('id', $customerId)->lockForUpdate()->firstOrFail();
            $pending = DB::table('support_tickets')->where('customer_id', $customerId)
                ->where('purpose', 'credit_limit_request')->where('status', 'open')->first();
            if ($pending) {
                return ['request_id' => $pending->id, 'created' => false];
            }
            $id = DB::table('support_tickets')->insertGetId([
                'customer_id' => $customerId,
                'subject' => 'طلب التحقق من الحد الائتماني / Credit limit review request',
                'description' => 'يرغب العميل في التحقق من الحد الائتماني للتقسيط المباشر. يُرجى تواصل إدارة الائتمان معه. / The customer requests a credit limit review for direct installment purchases. Please have the credit team contact them.',
                'type' => 'Partner request', 'priority' => 'Medium', 'status' => 'open',
                'purpose' => 'credit_limit_request', 'attachment' => '[]',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return ['request_id' => $id, 'created' => true];
        });
        return response()->json($result, $result['created'] ? 201 : 200);
    }
}
