<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerSubscriptionPayment;
use App\Models\OfflinePaymentMethod;
use App\Services\CustomerMonthlySubscriptionService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MonthlySubscriptionController extends Controller
{
    public function __construct(private readonly CustomerMonthlySubscriptionService $subscriptions) {}

    private function customer(Request $request): int
    {
        return (int) ($request->is('api/*') ? $request->user('api')->id : auth('customer')->id());
    }

    public function index(Request $request)
    {
        $id = $this->customer($request);
        $payload = [
            'subscription' => $this->subscriptions->status($id),
            'offline_methods' => (getWebConfig('offline_payment')['status'] ?? 0)
                ? OfflinePaymentMethod::where('status', 1)->whereIn('payment_channel', ['wallet','instapay'])->get(['id','method_name','payment_channel','method_fields','method_informations']) : collect(),
            'purchase_wallet_enabled' => getWebConfig('wallet_status') == 1,
            'purchase_balance' => (float) \App\Models\User::findOrFail($id)->wallet_balance,
            'payments' => CustomerSubscriptionPayment::where('customer_id', $id)->latest('id')->paginate(10),
        ];
        return $request->is('api/*') ? response()->json($payload) : view('customer-subscription.index', $payload);
    }

    public function checkout(Request $request)
    {
        $data = $request->validate(['request_key' => 'required|uuid']);
        try {
            return $this->result($request, $this->subscriptions->checkout($this->customer($request), $data['request_key']));
        } catch (DomainException $e) { throw ValidationException::withMessages(['subscription' => $e->getMessage()]); }
    }

    public function wallet(Request $request, int $payment)
    {
        try {
            return $this->result($request, $this->subscriptions->payFromWallet($this->customer($request), $payment));
        } catch (DomainException $e) { throw ValidationException::withMessages(['subscription' => $e->getMessage()]); }
    }

    public function offline(Request $request, int $payment)
    {
        $record = CustomerSubscriptionPayment::where('customer_id', $this->customer($request))->findOrFail($payment);
        // An exact retry after a lost response doesn't create another proof or
        // change a submission. A different transfer requires a new reviewed case.
        if ($record->status === 'pending_review' && $record->payment_reference === trim((string) $request->input('payment_reference'))) return $this->result($request, $record);
        $data = $request->validate([
            'method_id' => ['required','integer',Rule::exists('offline_payment_methods','id')->where(fn ($q) => $q->where('status',1)->whereIn('payment_channel',['wallet','instapay']))],
            'payment_reference' => 'required|string|max:191|unique:customer_subscription_payments,payment_reference',
            'payment_note' => 'nullable|string|max:1000',
            'method_information' => 'nullable|array', 'method_information.*' => 'nullable|string|max:255',
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        abort_unless(getWebConfig('offline_payment')['status'] ?? 0, 403);
        $method = OfflinePaymentMethod::findOrFail($data['method_id']);
        $path = 'customer-subscriptions/'.Str::uuid().'.enc';
        if (!Storage::disk('local')->put($path, Crypt::encryptString(file_get_contents($request->file('payment_proof')->getRealPath())))) {
            throw ValidationException::withMessages(['payment_proof' => 'تعذر حفظ إثبات الدفع. حاول مرة أخرى.']);
        }
        try {
            $record = $this->subscriptions->submitOffline($this->customer($request), $payment, $method, $data, $path);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            if ($e instanceof DomainException) throw ValidationException::withMessages(['subscription' => $e->getMessage()]);
            if ($e instanceof \Illuminate\Database\QueryException && in_array((string) $e->getCode(), ['23000','23505'], true)) {
                $retry = $record->fresh();
                if ($retry->status === 'pending_review' && $retry->payment_reference === trim($data['payment_reference'])) return $this->result($request,$retry);
                throw ValidationException::withMessages(['payment_reference' => 'مرجع التحويل مستخدم بالفعل.']);
            }
            throw $e;
        }
        return $this->result($request, $record);
    }

    private function result(Request $request, CustomerSubscriptionPayment $payment)
    {
        return $request->is('api/*') ? response()->json(['payment' => $payment, 'subscription' => $this->subscriptions->status($payment->customer_id)])
            : redirect()->route('customer.subscription.index')->with('subscription_message', $payment->status === 'approved' ? 'تم تفعيل اشتراكك لمدة 30 يومًا.' : ($payment->status === 'pending_review' ? 'الدفع قيد المراجعة' : 'اختر طريقة دفع الاشتراك.'));
    }
}
