<?php

namespace App\Http\Controllers\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerSubscriptionPayment;
use App\Services\MonthlySubscriptionPricingService;
use App\Services\CustomerMonthlySubscriptionService;
use Illuminate\Http\Request;

class MonthlySubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => 'nullable|in:pending_review,approved,rejected,draft']);
        return view('admin-views.customer.subscriptions', [
            'pricing' => app(MonthlySubscriptionPricingService::class)->get(),
            'payments' => CustomerSubscriptionPayment::with('customer')->when($request->filled('status'), fn ($q) => $q->where('status',$request->status))->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function pricing(Request $request)
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('business_settings'), 403);
        $data = $request->validate(['customer_price' => 'nullable|numeric|gt:0|max:999999999|decimal:0,3', 'vendor_price' => 'nullable|numeric|gt:0|max:999999999|decimal:0,3']);
        app(MonthlySubscriptionPricingService::class)->update($data, (int) auth('admin')->id());
        return back()->with('subscription_message','تم حفظ أسعار الاشتراك. الدفعات السابقة تحتفظ بسعرها المقتبس.');
    }

    public function review(Request $request, int $payment)
    {
        $data = $request->validate(['decision' => 'required|in:approve,reject', 'review_note' => 'nullable|string|max:1000']);
        try { app(CustomerMonthlySubscriptionService::class)->review($payment,$data['decision'],(int) auth('admin')->id(),$data['review_note'] ?? null); }
        catch (\DomainException $e) { throw \Illuminate\Validation\ValidationException::withMessages(['payment' => $e->getMessage()]); }
        return back()->with('subscription_message','تمت مراجعة الدفع.');
    }

    public function proof(int $payment)
    {
        $record = CustomerSubscriptionPayment::findOrFail($payment);
        abort_unless($record->payment_proof,404);
        $bytes = \Illuminate\Support\Facades\Crypt::decryptString(\Illuminate\Support\Facades\Storage::disk('local')->get($record->payment_proof));
        return response($bytes,200,['Content-Type' => getimagesizefromstring($bytes)['mime'] ?? 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
