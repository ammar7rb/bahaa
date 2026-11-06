<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\{Order, OrderShippingProof, Seller, VendorFulfillmentRequest};
use App\Services\{SellerLedgerService, VendorFulfillmentDecisionService, VendorFulfillmentUnlockService, VendorOrderPresentationService};
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class VendorFulfillmentController extends Controller
{
    private function seller(Request $request): Seller { return $request->is('api/*') ? $request->seller : auth('seller')->user(); }

    public function quote(Request $request,int $id)
    {
        try {
            $quote=app(VendorFulfillmentUnlockService::class)->quote($this->seller($request),$id);
            return $request->is('api/*')?response()->json(['quote'=>$quote])->header('Cache-Control','private, no-store')
                :view('vendor-views.order.unlock-confirm',compact('quote','id'));
        }catch(DomainException $e){return $this->failure($request,$e);}
    }

    public function unlock(Request $request,int $id)
    {
        $data=$request->validate(['token'=>'required|string|max:20000']);
        try {$row=app(VendorFulfillmentUnlockService::class)->unlock($this->seller($request),$id,$data['token']);return $this->success($request,$row);}
        catch(DomainException $e){return $this->failure($request,$e);}
    }

    public function decide(Request $request,int $id)
    {
        $data=$request->validate(['action'=>'required|in:accept,reject,preparing,ready,shipping,cancel',
            'reason'=>'required_if:action,reject,cancel|nullable|string|max:1000',
            'penalty_revision'=>'required_if:action,cancel|nullable|integer|min:0']);
        try {
            $row=app(VendorFulfillmentDecisionService::class)->decide($this->seller($request),$id,$data['action'],$data['reason']??null,
                expectedPenaltyRevision:$data['penalty_revision']??null);
            return $this->success($request,$row);
        }catch(DomainException $e){return $this->failure($request,$e);}
    }

    public function deliveryProof(Request $request,int $id)
    {
        $request->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf,mp4|max:10240','note'=>'nullable|string|max:1000']);
        $seller=$this->seller($request); $path=null;
        try {
            $row=DB::transaction(function()use($request,$id,$seller,&$path){
                $initial=VendorFulfillmentRequest::where('seller_id',$seller->id)->findOrFail($id);
                Order::whereKey($initial->order_id)->lockForUpdate()->firstOrFail();
                app(SellerLedgerService::class)->lockSellerFinancialState($seller->id);
                $row=VendorFulfillmentRequest::where('seller_id',$seller->id)->lockForUpdate()->findOrFail($id);
                if($row->status==='delivered')return $row;
                if($row->status!=='shipping')throw new DomainException('إثبات التسليم متاح بعد قبول الجزء وتجهيزه وشحنه.');
                $file=$request->file('proof');$path??=$file->store('shipping-proofs/fulfillment/'.$id,'local');
                $proof=OrderShippingProof::create(['order_id'=>$row->order_id,'seller_id'=>$seller->id,'fulfillment_request_id'=>$id,
                    'shipping_status'=>'delivered','file_path'=>$path,'disk'=>'local','original_name'=>$file->getClientOriginalName(),
                    'mime_type'=>$file->getMimeType(),'sha256'=>hash_file('sha256',$file->getRealPath()),'note'=>$request->input('note'),'review_status'=>'pending']);
                return app(VendorFulfillmentDecisionService::class)->decide($seller,$id,'delivered',$request->input('note'),$proof->id);
            },3);
            return $this->success($request,$row);
        }catch(\Throwable $e){if($path)Storage::disk('local')->delete($path);if($e instanceof DomainException)return $this->failure($request,$e);throw $e;}
    }

    private function success(Request $request,VendorFulfillmentRequest $row)
    {
        if($request->is('api/*'))return response()->json(['fulfillment_request'=>app(VendorOrderPresentationService::class)->order(Order::findOrFail($row->order_id))])->header('Cache-Control','private, no-store');
        return redirect()->route('vendor.orders.details',$row->order_id)->with('success','تم حفظ الإجراء. شراء الباقة أو عرض التفاصيل لا يعد قبولًا.');
    }

    private function failure(Request $request,DomainException $exception)
    {
        return $request->is('api/*')?response()->json(['message'=>$exception->getMessage()],422):back()->withErrors(['fulfillment'=>$exception->getMessage()]);
    }
}
