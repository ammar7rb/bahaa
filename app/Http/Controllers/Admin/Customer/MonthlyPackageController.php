<?php

namespace App\Http\Controllers\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerPurchasePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonthlyPackageController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['tab' => 'nullable|in:customers,vendors']);
        return view('admin-views.customer.monthly-packages', ['packages' => app(\App\Services\CustomerPackageCatalogService::class)->query()->orderBy('sort_order')->get(),
            'offers' => DB::table('customer_credit_offers')->orderBy('id')->get(), 'tab' => $request->input('tab', 'customers')]);
    }

    public function save(Request $request, ?int $package = null)
    {
        abort_unless($package,422);
        $data = $request->validate(['description' => 'nullable|string|max:2000',
            'package_price' => 'required|numeric|gt:0|max:999999999|decimal:0,3',
            'renewal_price'=>'nullable|numeric|gt:0|max:999999999|decimal:0,3',
            'purchase_limit' => 'required|numeric|gt:0|max:999999999|decimal:0,3', 'status' => 'required|boolean', 'sort_order' => 'nullable|integer|min:0|max:99999']);
        DB::transaction(function () use ($data, $package) {
            $record = app(\App\Services\CustomerPackageCatalogService::class)->query()->lockForUpdate()->findOrFail($package);
            $record->fill($data + ['duration_unit' => 'day', 'duration_value' => 30]);
            $record->updated_by_admin_id = auth('admin')->id();
            if (!$package) $record->created_by_admin_id = auth('admin')->id();
            $record->save();
        });
        return back()->with('subscription_message', 'تم حفظ الباقة. المدفوعات السابقة تحتفظ بالشروط المشتراة.');
    }

    public function delete(int $package)
    {
        if(app(\App\Services\CustomerPackageCatalogService::class)->query()->whereKey($package)->exists())throw ValidationException::withMessages(['package'=>'الباقات الثلاث الأساسية لا تحذف؛ يمكن تعديل إعداداتها.']);
        DB::transaction(function () use ($package) {
            $record = CustomerPurchasePackage::lockForUpdate()->findOrFail($package);
            if ($record->subscriptions()->exists() || $record->transactions()->exists()
                || DB::table('customer_subscription_payments')->where('package_id', $package)->exists()
                || DB::table('customer_monthly_subscriptions')->where('package_id', $package)->exists()
                || DB::table('customer_activation_invoices')->where('customer_purchase_package_id', $package)->exists()) {
                throw ValidationException::withMessages(['package' => 'الباقة مرتبطة بسجل اشتراك أو دفع. عطّلها بدل حذف التاريخ المالي.']);
            }
            $record->delete();
        });
        return back()->with('subscription_message', 'تم حذف الباقة غير المستخدمة.');
    }

    public function offer(Request $request, ?int $offer = null)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'price' => 'required|numeric|gt:0|max:999999999|decimal:0,3',
            'allowance' => 'required|numeric|gt:0|max:999999999|decimal:0,3', 'active' => 'required|boolean']);
        $data['updated_at'] = now();
        DB::transaction(function () use ($offer, $data) {
            if ($offer) {
                abort_unless(DB::table('customer_credit_offers')->where('id', $offer)->lockForUpdate()->first(), 404);
                DB::table('customer_credit_offers')->where('id', $offer)->update($data);
            } else {
                DB::table('customer_credit_offers')->insert($data + ['created_at' => now()]);
            }
        });
        return back()->with('subscription_message', 'تم حفظ عرض زيادة الحد. الزيادة ليست رصيد محفظة.');
    }

    public function deleteOffer(int $offer)
    {
        DB::transaction(function () use ($offer) {
            abort_unless(DB::table('customer_credit_offers')->where('id', $offer)->lockForUpdate()->first(), 404);
            if (DB::table('customer_subscription_payments')->where('offer_id', $offer)->exists()) {
                throw ValidationException::withMessages(['offer' => 'هذا العرض مرتبط بسجل دفع. غيّر حالته إلى معطّل لإخفائه عن العملاء مع الاحتفاظ بالسجل المالي.']);
            }
            DB::table('customer_credit_offers')->where('id', $offer)->delete();
        });
        return back()->with('subscription_message', 'تم حذف عرض زيادة الحد الائتماني.');
    }
}
