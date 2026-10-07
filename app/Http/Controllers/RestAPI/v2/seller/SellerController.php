<?php

namespace App\Http\Controllers\RestAPI\v2\seller;

use App\Models\Shop;
use App\Models\Review;
use App\Models\Seller;
use App\Utils\Convert;
use App\Utils\Helpers;
use App\Models\Product;
use App\Models\DeliveryMan;
use App\Utils\ImageManager;
use App\Services\EgyptPhoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\SellerWallet;
use App\Utils\BackEndHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\WithdrawRequest;
use App\Models\WithdrawalMethod;
use App\Services\SellerLedgerService;
use App\Services\SellerWithdrawalMethodService;
use App\Models\OrderTransaction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class SellerController extends Controller
{
    public function shop_info(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
            $product_ids = Product::where(['user_id' => $seller['id'], 'added_by' => 'seller'])->pluck('id')->toArray();
            $shop = Shop::where(['seller_id' => $seller['id']])->first();
            $shop['rating'] = round(Review::whereIn('product_id', $product_ids)->avg('rating'), 3);
            $shop['rating_count'] = Review::whereIn('product_id', $product_ids)->count();
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        return response()->json($shop, 200);
    }

    public function seller_delivery_man(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);
        if ($data['success'] == 1) {
            $seller = $data['data'];
            $delivery_men = DeliveryMan::where(['seller_id' => $seller['id']])->get();
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        return response()->json($delivery_men, 200);
    }

    public function shop_product_reviews(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
            $product_ids = Product::where(['user_id' => $seller['id'], 'added_by' => 'seller'])->pluck('id')->toArray();
            $reviews = Review::whereIn('product_id', $product_ids)->with(['product', 'customer'])->get();
            $reviews->map(function ($data) {
                $data['attachment'] = json_decode($data['attachment'], true);
                return $data;
            });
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        return response()->json($reviews, 200);
    }

    public function shop_product_reviews_status(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $reviews = Review::find($request->id);
            $reviews->status = $request->status;
            $reviews->save();
            return response()->json(['message'=>translate('status updated successfully!!')],200);
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }
    }

    public function seller_info(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        return response()->json(Seller::with(['wallet'])->withCount(['product', 'orders'])->where(['id' => $seller['id']])->first(), 200);
    }

    public function shop_info_update(Request $request):JsonResponse
    {
        $request->merge([
            'contact' => app(EgyptPhoneService::class)->normalize($request->input('contact')),
        ]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'contact' => ['required', 'regex:/^\+201[0125]\d{8}$/'],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $old_image = Shop::where(['seller_id' => $seller['id']])->first()->image;
        $image = $request->file('image');
        if ($image != null) {
            $imageName = ImageManager::update('shop/', $old_image, 'webp', $request->file('image'));
        } else {
            $imageName = $old_image;
        }

        Shop::where(['seller_id' => $seller['id']])->update([
            'name' => $request['name'],
            'address' => $request['address'],
            'contact' => $request['contact'],
            'image' => $imageName,
            'updated_at' => now()
        ]);

        return response()->json(translate('Shop info updated successfully!'), 200);
    }

    public function seller_info_update(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $old_image = Seller::where(['id' => $seller['id']])->first()->image;
        $image = $request->file('image');
        if ($image != null) {
            $imageName = ImageManager::update('seller/', $old_image, 'webp', $request->file('image'));
        } else {
            $imageName = $old_image;
        }

        Seller::where(['id' => $seller['id']])->update([
            'f_name' => $request['f_name'],
            'l_name' => $request['l_name'],
            'bank_name' => $request['bank_name'],
            'branch' => $request['branch'],
            'account_no' => $request['account_no'],
            'holder_name' => $request['holder_name'],
            'phone'=> $request['phone'],
            'password' => $request['password'] != null ? bcrypt($request['password']) : Seller::where(['id' => $seller['id']])->first()->password,
            'image' => $imageName,
            'updated_at' => now()
        ]);

        if ($request['password'] != null) {
            Seller::where(['id' => $seller['id']])->update([
                'auth_token' => Str::random('50')
            ]);
        }

        return response()->json(translate('Info updated successfully!'), 200);
    }

    public function withdraw_request(Request $request): JsonResponse
    {
        $result = Helpers::get_seller_by_token($request); abort_unless($result['success'] == 1, 401); $seller = $result['data'];
        $input = $request->validate(['amount' => 'required|numeric|min:0.01', 'saved_method_id' => 'required_without:withdraw_method_id|integer', 'withdraw_method_id' => 'required_without:saved_method_id|integer', 'request_key' => 'nullable|uuid']);
        try {
            $withdrawal = app(\App\Services\SellerWithdrawalService::class)->submit((int) $seller['id'], (int) ($input['saved_method_id'] ?? $input['withdraw_method_id']), (float) Convert::usd($input['amount']), $input['request_key'] ?? null);
            return response()->json(['message' => translate('withdraw_request_has_been_sent'), 'request' => $withdrawal]);
        } catch (\DomainException | \InvalidArgumentException $exception) { return response()->json(['message' => translate($exception->getMessage())], 422); }
    }


    public function close_withdraw_request(Request $request): JsonResponse
    {
        $result = Helpers::get_seller_by_token($request); abort_unless($result['success'] == 1, 401); $seller = $result['data'];
        $request->validate(['id' => 'required|integer']);
        try { app(\App\Services\SellerWithdrawalService::class)->finish((int) $request->id, 3, (int) $seller['id'], 'Cancelled by vendor'); return response()->json(['message' => translate('withdraw_request_closed')]); }
        catch (\DomainException $exception) { return response()->json(['message' => translate($exception->getMessage())], 422); }
    }


    public function transaction(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);
        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $transaction = WithdrawRequest::where('seller_id', $seller['id'])->latest()->get();

        return response()->json($transaction, 200);
    }

    public function monthly_earning(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);
        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $from = \Carbon\Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');
        $seller_data = '';
        $seller_earnings = OrderTransaction::where([
            'seller_is' => 'seller',
            'seller_id' => $seller['id'],
            'status' => 'disburse'
        ])->select(
            DB::raw('IFNULL(sum(seller_amount),0) as sums'),
            DB::raw('YEAR(created_at) year, MONTH(created_at) month')
        )->whereBetween('created_at', [$from, $to])->groupby('year', 'month')->get()->toArray();
        for ($inc = 1; $inc <= 12; $inc++) {
            $default = 0;
            foreach ($seller_earnings as $match) {
                if ($match['month'] == $inc) {
                    $default = $match['sums'];
                }
            }
            $seller_data .= $default . ',';
        }

        return response()->json($seller_data, 200);
    }

    public function monthly_commission_given(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);
        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        $from = \Carbon\Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $commission_data = '';
        $commission_earnings = OrderTransaction::where([
            'seller_is' => 'seller',
            'seller_id' => $seller['id'],
            'status' => 'disburse'
        ])->select(
            DB::raw('IFNULL(sum(admin_commission),0) as sums'),
            DB::raw('YEAR(created_at) year, MONTH(created_at) month')
        )->whereBetween('created_at', [$from, $to])->groupby('year', 'month')->get()->toArray();
        for ($inc = 1; $inc <= 12; $inc++) {
            $default = 0;
            foreach ($commission_earnings as $match) {
                if ($match['month'] == $inc) {
                    $default = $match['sums'];
                }
            }
            $commission_data .= $default . ',';
        }

        return response()->json($commission_data, 200);
    }

    public function update_cm_firebase_token(Request $request):JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cm_firebase_token' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }
        $data = Helpers::get_seller_by_token($request);

        if ($data['success'] == 1) {
            $seller = $data['data'];
        } else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        DB::table('sellers')->where('id', $seller->id)->update([
            'cm_firebase_token' => $request['cm_firebase_token'],
        ]);

        return response()->json(['message' => translate('successfully updated!')], 200);
    }

    public function account_delete(Request $request):JsonResponse
    {
        $data = Helpers::get_seller_by_token($request);
        if ($data['success'] == 1) {
            $seller = $data['data'];
        }else {
            return response()->json([
                'auth-001' => translate('Your existing session token does not authorize you any more')
            ], 401);
        }

        if($seller->id){
            ImageManager::delete('/seller/' . $seller['image']);

            $seller->delete();
            return response()->json(['message' => translate('Your_account_deleted_successfully!!')],200);

        }else{
            return response()->json(['message' =>'access_denied!!'],403);
        }
    }
}
