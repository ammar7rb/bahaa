<?php

namespace App\Http\Controllers\RestAPI\v3\seller\auth;

use App\Events\VendorRegistrationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\v3\SellerRegistrationRequest;
use App\Models\Admin;
use App\Models\Seller;
use App\Models\Shop;
use App\Services\SellerRegistrationVerificationService;
use App\Utils\Helpers;
use App\Utils\ImageManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RegisterController extends Controller
{

    public function store(
        SellerRegistrationRequest $request,
        SellerRegistrationVerificationService $verificationService
    ): JsonResponse
    {
        $adminEmail = Admin::where('admin_role_id', 1)->select('email')->first();
        if ($adminEmail && $request['email'] === $adminEmail) {
            return response()->json([
                'message' => translate('Email_already_exist_please_try_another_email'),
                'error' => translate('Email_already_exist_please_try_another_email'),
            ], 403);
        }
        $storage = config('filesystems.disks.default') ?? 'public';
        DB::beginTransaction();
        try {
            $seller = new Seller();
            $seller->f_name = $request->f_name;
            $seller->l_name = $request->l_name;
            $seller->phone = $request->phone;
            $seller->email = $request->email;
            $seller->image = $request->file('image') ? ImageManager::upload('seller/', 'webp', $request->file('image')) : null;
            $seller->password = bcrypt($request->password);
            $seller->status = 'pending';
            $seller->phone_verified_at = null;
            $seller->registration_reference = (string) Str::uuid();
            $seller->save();

            $shop = new Shop();
            $shop->seller_id = $seller->id;
            $shop->name = $request->shop_name;
            $shop->address = $request->shop_address;
            $shop->contact = $request->phone;
            $shop->image = $request->file('logo') ? ImageManager::upload('shop/', 'webp', $request->file('logo')) : null;
            $shop->image_storage_type = $request->has('logo') ? $storage : null;
            $shop->banner =  $request->file('banner') ? ImageManager::upload('shop/banner/', 'webp', $request->file('banner')) : null;
            $shop->banner_storage_type = $request->has('banner') ? $storage : null;
            $shop->bottom_banner = ImageManager::upload('shop/banner/', 'webp', $request->file('bottom_banner'));
            $shop->bottom_banner_storage_type = $request->has('bottom_banner') ? $storage : null;
            $shop->tax_identification_number = $request['tax_identification_number'] ?? '';
            $shop->tin_expire_date = $request['tin_expire_date'] ? Carbon::parse($request['tin_expire_date']) : null;
            $shop->tin_certificate = $request->file('tin_certificate') ? ImageManager::file_upload(
                dir: 'shop/documents/',
                format: $request->file('tin_certificate')->getClientOriginalExtension(),
                file: $request->file('tin_certificate')) : null;
            $shop->tin_certificate_storage_type = $request->has('tin_certificate') ? $storage : null;
            $shop->save();

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
            DB::commit();
            $data = [
                'vendorName' => $request['f_name'],
                'status' => 'pending',
                'subject' => translate('Vendor_Registration_Successfully_Completed'),
                'title' => translate('Vendor_Registration_Successfully_Completed'),
                'userType' => 'vendor',
                'templateName' => 'registration',
            ];
            try {
                event(new VendorRegistrationEvent(email: $request['email'], data: $data));
            } catch (\Throwable) {
                // Registration and phone verification must not fail when email delivery is unavailable.
            }

            if ($verificationService->isFirebaseEnabled() && !$request->filled('firebase_session_info')) {
                $otpResult = [
                    'status' => false,
                    'code' => 'firebase_session_info_required',
                    'message' => 'Start Firebase phone verification on the device, then submit its session info.',
                    'delivery_method' => 'firebase_client',
                    'resend_after' => 0,
                ];
            } else {
                $otpResult = $verificationService->sendOtp($seller, $request->input('firebase_session_info'));
            }

            return response()->json([
                'message' => 'Shop apply successfully!',
                'code' => 'seller_phone_verification_required',
                'registration_reference' => $seller->registration_reference,
                'masked_phone' => $verificationService->getMaskedPhone($seller),
                'otp' => $otpResult,
                'eligibility' => $verificationService->getEligibility($seller),
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Shop apply fail!',
                'error' => $e->getMessage(),
            ], 403);
        }

    }

    public function sendOtp(Request $request, SellerRegistrationVerificationService $verificationService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'registration_reference' => 'required|uuid',
            'firebase_session_info' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 422);
        }

        $seller = $verificationService->findByReference($request->input('registration_reference'));
        if (!$seller) {
            return $this->registrationNotFoundResponse();
        }

        if ($verificationService->requiresPhoneVerification($seller)
            && $verificationService->isFirebaseEnabled()
            && !$request->filled('firebase_session_info')) {
            return response()->json([
                'code' => 'firebase_session_info_required',
                'message' => 'Start Firebase phone verification on the device, then submit its session info.',
                'delivery_method' => 'firebase_client',
            ], 422);
        }

        $result = $verificationService->sendOtp($seller, $request->input('firebase_session_info'));
        return response()->json($result, $result['status'] ? 200 : ($result['code'] === 'otp_resend_wait' ? 429 : 422));
    }

    public function verifyOtp(Request $request, SellerRegistrationVerificationService $verificationService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'registration_reference' => 'required|uuid',
            'otp' => 'required|digits:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 422);
        }

        $seller = $verificationService->findByReference($request->input('registration_reference'));
        if (!$seller) {
            return $this->registrationNotFoundResponse();
        }

        $result = $verificationService->verifyOtp($seller, (string) $request->input('otp'));
        return response()->json($result, $result['status'] ? 200 : 422);
    }

    public function status(Request $request, SellerRegistrationVerificationService $verificationService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'registration_reference' => 'required|uuid',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 422);
        }

        $seller = $verificationService->findByReference($request->input('registration_reference'));
        if (!$seller) {
            return $this->registrationNotFoundResponse();
        }

        return response()->json([
            'registration_reference' => $seller->registration_reference,
            'masked_phone' => $verificationService->getMaskedPhone($seller),
            'eligibility' => $verificationService->getEligibility($seller),
        ]);
    }

    private function registrationNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'errors' => [
                ['code' => 'seller_registration_not_found', 'message' => translate('no_such_user_found')],
            ],
        ], 404);
    }
}
