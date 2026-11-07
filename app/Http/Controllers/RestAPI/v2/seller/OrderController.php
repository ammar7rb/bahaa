<?php

namespace App\Http\Controllers\RestAPI\v2\seller;

use App\Events\OrderStatusEvent;
use App\Http\Controllers\Controller;
use App\Models\DeliveryManTransaction;
use App\Models\DeliverymanWallet;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ReferralCustomer;
use App\Traits\CommonTrait;
use App\Utils\BackEndHelper;
use App\Utils\Convert;
use App\Utils\CustomerManager;
use App\Utils\Helpers;
use App\Utils\ImageManager;
use App\Utils\OrderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;


class OrderController extends Controller
{
    use CommonTrait;

    public function list(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $orders = Order::where('seller_id',$seller->id)->where('seller_is','seller')->sellerAccessible()->get()
            ->filter(fn($order)=>app(\App\Services\SellerOrderVisibilityService::class)->canSellerView($order))
            ->map(fn($order)=>app(\App\Services\VendorOrderPresentationService::class)->order($order))->values();

        return response()->json($orders, 200);
    }

    public function details(Request $request, $id):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $order=Order::where('seller_id',$seller->id)->where('seller_is','seller')->findOrFail($id);
        abort_unless(app(\App\Services\SellerOrderVisibilityService::class)->canSellerView($order),404);
        $eligibility=app(\App\Services\VendorFulfillmentEligibilityService::class);
        abort_if($eligibility->summary($order)['details_locked']??false,423,$eligibility::MESSAGE);
        $details=$order->details->filter(fn($line)=>$eligibility->lineAllowed($line))
            ->map(fn($line)=>app(\App\Services\VendorOrderPresentationService::class)->line($line))->values();

        return response()->json($details, 200);
    }

    public function assign_delivery_man(Request $request):JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'delivery_man_id' => 'required',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)]);
        }

        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $order = Order::where(['seller_id' => $seller['id'], 'id' => $request['order_id']])->first();

        $order->delivery_man_id = $request['delivery_man_id'];
        $order->delivery_type = 'self_delivery';
        $order->delivery_service_name = null;
        $order->third_party_delivery_tracking_id = null;
        $order->save();

        OrderStatusEvent::dispatch('new_order_assigned_message', 'delivery_man', $order);

        return response()->json(['success' => 1, 'message' => translate('order_deliveryman_assigned_successfully')], 200);
    }

    public function amount_date_update(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);
        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $deliveryman_charge = $request->deliveryman_charge;

        $order = Order::find($request->order_id);
        $db_expected_date = $order->expected_delivery_date;

        $order->deliveryman_charge = $deliveryman_charge;
        $order->expected_delivery_date = $request->expected_delivery_date;

        try {
            DB::beginTransaction();

            if (!empty($request->expected_delivery_date) && $db_expected_date != $request->expected_delivery_date) {
                CommonTrait::add_expected_delivery_date_history($request->order_id, $seller['id'], $request->expected_delivery_date, 'seller');
            }
            $order->save();

            DB::commit();
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['success' => 0, 'message' => translate('Update fail!')], 403);
        }

        if (!empty($request->expected_delivery_date) && $db_expected_date != $request->expected_delivery_date) {
            OrderStatusEvent::dispatch('expected_delivery_date', 'delivery_man', $order);
        }

        return response()->json(['success' => 0, 'message' => translate('Updated successfully!')], 200);
    }

    /**
     *  Digital file upload after sell
     */
    public function digital_file_upload_after_sell(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'digital_file_after_sell' => 'required|mimes:jpg,jpeg,png,gif,zip,pdf',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)]);
        }

        $order_details = OrderDetail::find($request->order_id);
        if ($order_details) {
            $order_details->digital_file_after_sell = ImageManager::update('product/digital-product/', $order_details->digital_file_after_sell, $request->digital_file_after_sell->getClientOriginalExtension(), $request->file('digital_file_after_sell'), 'file');
            $order_details->save();
            return response()->json(['success' => 1, 'message' => translate('File_upload_successfully')], 200);
        } else {
            return response()->json(['success' => 0, 'message' => translate("File_upload_fail!")], 202);
        }
    }

    public function order_detail_status(Request $request):JsonResponse
    {
        $auth=Helpers::get_seller_by_token($request);
        if(($auth['success']??0)!==1)return response()->json(['auth-001'=>'Unauthenticated'],401);
        $request->merge(['id'=>$request->route('id')??$request->input('id')]);
        $request->validate(['id'=>'required|integer|min:1','order_status'=>'required|in:pending,confirmed,processing,out_for_delivery,delivered,returned,failed,canceled']);
        try {
            return DB::transaction(function()use($request,$auth){
                $order=Order::where('seller_id',$auth['data']->id)->where('seller_is','seller')->lockForUpdate()->findOrFail($request->id);
                app(\App\Services\VendorFulfillmentDecisionService::class)->assertLegacyMutationAllowed($order);
                abort_unless(app(\App\Services\SellerOrderVisibilityService::class)->canSellerView($order),404);
                app(\App\Services\OrderWorkflowService::class)->assertTransition($order->order_status,$request->order_status,'seller');
                return $this->applyLegacyOrderStatus($request);
            },3);
        }catch(\DomainException $error){return response()->json(['success'=>0,'message'=>$error->getMessage()],422);}
    }

    private function applyLegacyOrderStatus(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $order = Order::find($request->id);
        if (empty($order->customer)) {
            return response()->json(['success' => 0, 'message' => translate("Customer account has been deleted. you can't update status!")], 202);
        }

        $wallet_status = getWebConfig(name: 'wallet_status');
        $loyalty_point_status = getWebConfig(name: 'loyalty_point_status');

        if ($request->order_status == 'delivered' && $order->payment_status != 'paid') {
            return response()->json(['success' => 0, 'message' => translate('Before delivered you need to make payment status paid!')], 200);
        }

        if ($order->order_status == 'delivered') {
            return response()->json(['success' => 0, 'message' => translate('order is already delivered')], 200);
        }

        DB::afterCommit(fn()=>OrderStatusEvent::dispatch($request['order_status'], 'customer', $order));
        if ($request->order_status == 'canceled') {
            DB::afterCommit(fn()=>OrderStatusEvent::dispatch('canceled', 'delivery_man', $order));
        }

        $order->order_status = $request->order_status;
        OrderManager::getStockUpdateOnOrderStatusChange($order, $request->order_status);

        if ($request->order_status == 'delivered' && $order['seller_id'] != null) {
            OrderManager::getWalletManageOnOrderStatusChange($order, 'seller');
            OrderDetail::where('order_id', $order->id)->update(
                ['delivery_status' => 'delivered']
            );
        }

        $order->save();

        if ($order->delivery_man_id && $request->order_status == 'delivered') {
            $dm_wallet = DeliverymanWallet::where('delivery_man_id', $order->delivery_man_id)->first();
            $cash_in_hand = $order->payment_method == 'cash_on_delivery' ? $order->order_amount : 0;

            if (empty($dm_wallet)) {
                DeliverymanWallet::create([
                    'delivery_man_id' => $order->delivery_man_id,
                    'current_balance' => BackEndHelper::currency_to_usd($order->deliveryman_charge) ?? 0,
                    'cash_in_hand' => BackEndHelper::currency_to_usd($cash_in_hand),
                    'pending_withdraw' => 0,
                    'total_withdraw' => 0,
                ]);
            } else {
                $dm_wallet->current_balance += BackEndHelper::currency_to_usd($order->deliveryman_charge) ?? 0;
                $dm_wallet->cash_in_hand += BackEndHelper::currency_to_usd($cash_in_hand);
                $dm_wallet->save();
            }

            if ($order->deliveryman_charge && $request->order_status == 'delivered') {
                DeliveryManTransaction::create([
                    'delivery_man_id' => $order->delivery_man_id,
                    'user_id' => $seller->id,
                    'user_type' => 'seller',
                    'credit' => BackEndHelper::currency_to_usd($order->deliveryman_charge) ?? 0,
                    'transaction_id' => Uuid::uuid4(),
                    'transaction_type' => 'deliveryman_charge'
                ]);
            }
        }

        if ($wallet_status == 1 && $loyalty_point_status == 1) {
            if ($request->order_status == 'delivered' && $order->payment_status == 'paid') {
                CustomerManager::create_loyalty_point_transaction($order->customer_id, $order->id, Convert::default($order->order_amount - $order->shipping_cost), 'order_place');
            }
        }

        OrderManager::generateReferBonusForFirstOrder(orderId: $order['id']);
        if ($request['order_status'] == 'delivered') {
            $referredUser = ReferralCustomer::where('user_id', $order?->customer?->id)->first();
            if ($referredUser?->delivered_notify != 1) {
                event(new OrderStatusEvent(key: 'your_referred_customer_order_has_been_delivered', type: 'promoter', order: $order));
                ReferralCustomer::where('user_id', $order?->customer?->id)->update(['delivered_notify' => 1]);
            }
        }
        self::add_order_status_history($order->id, $seller->id, $request->order_status, 'seller');

        return response()->json(['success' => 1, 'message' => translate('order_status_updated_successfully')], 200);
    }

    public function assign_third_party_delivery(Request $request):JsonResponse
    {

        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'delivery_service_name' => 'required',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)]);
        }

        $order = Order::find($request->order_id);
        $order->delivery_type = 'third_party_delivery';
        $order->delivery_service_name = $request->delivery_service_name;
        $order->third_party_delivery_tracking_id = $request->third_party_delivery_tracking_id;
        $order->delivery_man_id = null;
        $order->deliveryman_charge = 0;
        $order->expected_delivery_date = null;
        $order->save();

        return response()->json(['success' => 1, 'message' => translate('third_party_delivery_assigned_successfully')], 200);
    }

    public function update_payment_status(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'payment_status' => 'required|in:paid,unpaid'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $order = Order::find($request['order_id']);
        if (isset($order)) {
            if ($order->is_guest == '0' && empty($order->customer)) {
                return response()->json(['success' => 0, 'message' => translate("Customer account has been deleted. you can't update status!")], 202);
            }

            $order->payment_status = $request['payment_status'];
            $order->save();
            return response()->json(['message' => translate('Payment status updated')], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('not found!')]
            ]
        ], 404);
    }
}
