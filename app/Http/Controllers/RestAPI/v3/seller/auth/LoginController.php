<?php

namespace App\Http\Controllers\RestAPI\v3\seller\auth;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerWallet;
use App\Models\Shop;
use App\Services\SellerRegistrationVerificationService;
use App\Utils\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function login(Request $request, SellerRegistrationVerificationService $verificationService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:191',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $seller = app(\App\Services\SellerLoginIdentityService::class)->resolve((string) $request['email']);
        $data = ['id' => $seller?->id, 'password' => $request['password']];

        $passwordMatches = $seller && Hash::check($request['password'], $seller['password']);

        if ($passwordMatches && in_array($seller['status'], ['pending', 'approved'], true)
            && $verificationService->requiresPhoneVerification($seller)) {
            return response()->json([
                'errors' => [[
                    'code' => 'seller_phone_verification_required',
                    'message' => translate('Please_verify_your_phone'),
                ]],
                'loginStatus' => 'phone_verification_required',
                'registration_reference' => $seller->registration_reference,
                'eligibility' => $verificationService->getEligibility($seller),
            ], 401);
        }

        if ($passwordMatches && in_array($seller['status'], ['approved', 'pending'], true) && auth('seller')->attempt($data)) {
            if (isset($seller->shop) && empty($seller->shop->setup_guide_app)) {
                Shop::where(['seller_id' => $seller['id']])->update([
                    'setup_guide_app' => json_encode([
                        'shop_setup' => 0,
                        'add_new_product' => Product::where(['added_by' => 'seller', 'user_id' => $seller['id']])->exists() ? 1 : 0,
                        'order_setup' => 0,
                        'withdraw_setup' => 0,
                        'payment_information' => 0,
                    ]),
                ]);
            }
            $token = Str::random(50);
            Seller::where(['id' => auth('seller')->id()])->update(['auth_token' => $token]);
            if (SellerWallet::where('seller_id', $seller['id'])->first() == false) {
                DB::table('seller_wallets')->insert([
                    'seller_id' => $seller['id'],
                    'withdrawn' => 0,
                    'commission_given' => 0,
                    'total_earning' => 0,
                    'pending_withdraw' => 0,
                    'delivery_charge_earned' => 0,
                    'collected_cash' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if (!$seller->registration_reference) {
                $seller->registration_reference = (string) Str::uuid();
                $seller->save();
            }
            return response()->json(['token' => $token, 'registration_reference' => $seller->registration_reference], 200);
        } elseif ($passwordMatches && $seller['status'] == 'pending') {
            $errors = [];
            $errors[] = ['code' => 'auth-001', 'message' => translate('your_account_is_in_review_process').'. '.translate('please_wait_for_admin_approval')];
            return response()->json([
                'errors' => $errors,
                'loginStatus' => 'pending'
            ], 401);
        } else {
            $errors = [];
            $errors[] = ['code' => 'auth-001', 'message' => translate('invalid_credential')];
            return response()->json([
                'errors' => $errors,
                'loginStatus' => 'unauthorized'
            ], 401);
        }
    }
}
