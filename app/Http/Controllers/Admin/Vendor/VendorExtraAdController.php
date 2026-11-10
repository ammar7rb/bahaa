<?php
namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorExtraAdPurchase;
use App\Services\VendorExtraAdService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorExtraAdController extends Controller
{
    public function index()
    {
        return view('admin-views.vendor.package.extra-ads', ['settings' => app(VendorExtraAdService::class)->settings(),
            'purchases' => VendorExtraAdPurchase::with('seller:id,f_name,l_name')->when(request()->filled('record_id'), fn ($q) => $q->whereKey(request()->integer('record_id')))->latest('id')->paginate(30)]);
    }
    public function settings(Request $request)
    {
        $data = $request->validate(['unit_price' => 'required|numeric|min:0.01|max:999999999.99|decimal:0,2', 'max_slots' => 'required|integer|min:1|max:10000', 'enabled' => 'nullable|boolean']);
        $before = DB::table('vendor_extra_ad_settings')->where('id', 1)->first();
        DB::table('vendor_extra_ad_settings')->updateOrInsert(['id' => 1], ['unit_price' => $data['unit_price'],
            'max_slots' => $data['max_slots'], 'enabled' => $request->boolean('enabled'), 'updated_by' => auth('admin')->id(), 'updated_at' => now()]);
        if (\Illuminate\Support\Facades\Schema::hasTable('business_setting_audits')) {
            DB::table('business_setting_audits')->insert(['kind' => 'package', 'subject_type' => 'vendor_extra_ad_settings',
                'subject_id' => 1, 'before' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                'after' => json_encode($data + ['enabled' => $request->boolean('enabled')], JSON_UNESCAPED_UNICODE),
                'admin_id' => auth('admin')->id(), 'created_at' => now()]);
        }
        ToastMagic::success('تم حفظ إعدادات الإضافات للمشتريات الجديدة.');
        return back();
    }
    public function approve(Request $request, int $id)
    {
        $data = $request->validate(['payment_reference' => 'required|string|max:191', 'review_note' => 'nullable|string|max:1000']);
        try {
            $purchase = app(VendorExtraAdService::class)->approve($id, auth('admin')->id(), $data['payment_reference'], $data['review_note'] ?? null);
            ToastMagic::success($purchase->status === 'manual_settlement' ? 'انتهت الدورة؛ يبقى التحويل معلقًا لتسوية يدوية دون اعتماد أو إضافة رصيد.' : 'تم اعتماد الدفع وإضافة المساحات.');
        } catch (DomainException $e) { return back()->withErrors(['payment' => $e->getMessage()]); }
        return back();
    }
    public function reject(Request $request, int $id)
    {
        $data = $request->validate(['review_note' => 'required|string|min:3|max:1000']);
        try { app(VendorExtraAdService::class)->rejectPayment($id, auth('admin')->id(), $data['review_note']); }
        catch (DomainException $e) { return back()->withErrors(['payment' => $e->getMessage()]); }
        ToastMagic::success('سُجل عدم تأكيد وصول التحويل، دون إضافة رصيد أو مساحات.');
        return back();
    }
}
