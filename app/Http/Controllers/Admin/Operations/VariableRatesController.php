<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Http\Controllers\Controller;
use App\Services\{VendorCancellationPenaltyService, VendorExtraAdService};
use Illuminate\Http\Request;

class VariableRatesController extends Controller
{
    public function index()
    {
        return view('admin-views.operations.variable-rates',['penalty'=>app(VendorCancellationPenaltyService::class)->settings(),
            'extra'=>app(VendorExtraAdService::class)->settings()]);
    }

    public function update(Request $request)
    {
        $data=$request->only(['mode','fixed_amount','percentage']);$data['enabled']=$request->boolean('enabled');
        app(VendorCancellationPenaltyService::class)->save($data,(int)auth('admin')->id());
        return back()->with('success','تم حفظ القاعدة للغرامات الجديدة؛ السجلات السابقة لا تتغير.');
    }

    public function extraSlots(Request $request)
    {
        $data=$request->validate(['unit_price'=>'required|numeric|min:0.01|max:999999999.99|decimal:0,2','max_slots'=>'required|integer|min:1|max:10000','enabled'=>'nullable|boolean']);
        \Illuminate\Support\Facades\DB::table('vendor_extra_ad_settings')->updateOrInsert(['id'=>1],[
            'unit_price'=>$data['unit_price'],'max_slots'=>$data['max_slots'],'enabled'=>$request->boolean('enabled'),
            'updated_by'=>auth('admin')->id(),'updated_at'=>now()]);
        return back()->with('success','تم حفظ سعر المساحة للمشتريات الجديدة؛ يجب إعادة مراجعة أي عرض قديم قبل تأكيده.');
    }
}
