<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Services\VendorFulfillmentDecisionService;
use DomainException;
use Illuminate\Http\Request;

class VendorFulfillmentController extends Controller
{
    public function prepareRefund(Request $request,int $id)
    {
        $data=$request->validate(['line_id'=>'required|integer|min:1','amount'=>'required|numeric|min:0.01|max:999999999.99|decimal:0,2','reason'=>'required|string|min:3|max:1000']);
        try{$refund=app(\App\Services\VendorFulfillmentRefundService::class)->prepare($id,(int)$data['line_id'],(float)$data['amount'],(int)auth('admin')->id(),$data['reason']);}
        catch(DomainException $e){return back()->withErrors(['refund'=>$e->getMessage()]);}
        return redirect()->route('admin.refund-section.refund.details',$refund->id);
    }

    public function financialReview(Request $request,int $id)
    {
        $data=$request->validate(['financial_reference'=>'required|string|max:191','note'=>'required|string|min:3|max:2000']);
        \Illuminate\Support\Facades\DB::transaction(function()use($id,$data){
            $row=\App\Models\VendorFulfillmentRequest::findOrFail($id);
            $order=\App\Models\Order::whereKey($row->order_id)->lockForUpdate()->firstOrFail();
            app(\App\Services\SellerLedgerService::class)->lockSellerFinancialState((int)$row->seller_id);
            $review=\Illuminate\Support\Facades\DB::table('vendor_fulfillment_financial_reviews')->where('request_id',$id)->lockForUpdate()->first();
            abort_unless($review,404);if($review->status==='reviewed')return;
            // This records an administrator's review of the existing refund/settlement
            // process. It does not issue refunds or release money a second time.
            \Illuminate\Support\Facades\DB::table('vendor_fulfillment_financial_reviews')->where('id',$review->id)->update([
                'status'=>'reviewed','admin_note'=>$data['financial_reference'].' — '.$data['note'],'reviewed_by'=>auth('admin')->id(),'reviewed_at'=>now(),'updated_at'=>now()]);
            \App\Models\OrderOperationalAlert::where('idempotency_key','vendor-portion-review-'.$id)->update(['resolved_at'=>now()]);
            app(\App\Services\OrderFinancialAuditService::class)->record($order,'vendor_portion_financial_review','fulfillment_request',$id,$data,(int)auth('admin')->id());
        },3);
        return back()->with('success','سُجلت المراجعة ومرجعها. لا تنفذ هذه الخطوة استردادًا أو تسوية مالية أخرى.');
    }

    public function update(Request $request,int $id)
    {
        $data=$request->validate(['action'=>'required|in:shipping,delivered,cancel','reason'=>'required|string|max:1000']);
        try{app(VendorFulfillmentDecisionService::class)->adminTransition($id,$data['action'],$data['reason'],(int)auth('admin')->id());}
        catch(DomainException $e){return back()->withErrors(['fulfillment'=>$e->getMessage()]);}
        return back()->with('success','تم حفظ إجراء التنفيذ وإخطار التتبع وفق حالة الأجزاء.');
    }
}
