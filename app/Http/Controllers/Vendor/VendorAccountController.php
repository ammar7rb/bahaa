<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\VendorAccountProfile;
use App\Utils\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class VendorAccountController extends Controller
{
    private function seller(Request $request): Seller
    {
        $seller = $request->attributes->get('seller') ?? $request->seller ?? auth('seller')->user();
        abort_unless($seller instanceof Seller, 401);
        return $seller;
    }

    public function show(Request $request)
    {
        $seller = $this->seller($request);
        $profile = VendorAccountProfile::where('seller_id', $seller->id)->first();
        $data = [
            'account_id' => $seller->administrative_reference,
            'f_name' => $seller->f_name, 'l_name' => $seller->l_name,
            'email' => $seller->email, 'image' => $seller->image_full_url,
            'store_name' => $seller->shop?->name,
            'phone_numbers' => $profile?->phone_numbers ?: [$seller->phone],
            'store_addresses' => $profile?->store_addresses ?: array_filter([$seller->shop?->address]),
            'commercial_registration' => $profile?->commercial_registration,
            'tax_card' => $profile?->tax_card ?? $seller->shop?->tax_identification_number,
            'commercial_document_uploaded' => filled($profile?->commercial_document),
            'tax_document_uploaded' => filled($profile?->tax_document),
        ];
        return $request->is('api/*') ? response()->json($data)->header('Cache-Control', 'private, no-store')
            : view('vendor-views.account.index', compact('data'));
    }

    public function update(Request $request)
    {
        $seller = $this->seller($request);
        // Multipart clients send arrays as JSON; normal forms send arrays directly.
        foreach (['phone_numbers', 'store_addresses'] as $key) {
            if (is_string($request->input($key))) $request->merge([$key => json_decode($request->input($key), true)]);
        }
        $data = $request->validate([
            'f_name' => 'required|string|max:100', 'l_name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:191', Rule::unique('sellers')->ignore($seller->id)],
            'store_name' => 'required|string|max:191',
            'phone_numbers' => 'required|array|list|min:1|max:10',
            'phone_numbers.*' => ['required', 'string', 'min:4', 'max:20', 'distinct', 'regex:/^\+?[0-9 ()-]+$/'],
            'store_addresses' => 'required|array|list|min:1|max:10', 'store_addresses.*' => 'required|string|max:1000|distinct',
            'commercial_registration' => 'nullable|string|max:5000', 'tax_card' => 'nullable|string|max:5000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'commercial_document' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'tax_document' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        $stored = [];
        $oldFiles = [];
        $avatar = null;
        $previousAvatar = $seller->image;
        try {
            DB::transaction(function () use ($seller, $request, $data, &$stored, &$oldFiles, &$avatar) {
                $profile = VendorAccountProfile::firstOrNew(['seller_id' => $seller->id]);
                $profile->seller_id = $seller->id;
                $profile->fill(collect($data)->only(['phone_numbers', 'store_addresses', 'commercial_registration', 'tax_card'])->all());
                foreach (['commercial_document', 'tax_document'] as $key) {
                    if ($request->hasFile($key)) {
                        if ($profile->$key) $oldFiles[] = $profile->$key;
                        $profile->$key = $request->file($key)->store('vendor-documents/'.$seller->id, 'local');
                        $stored[] = $profile->$key;
                    }
                }
                $profile->save();
                $seller->fill(collect($data)->only(['f_name', 'l_name', 'email'])->all());
                // Store contacts are independent of the verified login number.
                // Changing authentication identity must use the OTP workflow.
                if ($request->hasFile('image')) {
                    $avatar = ImageManager::upload('seller/', 'webp', $request->file('image'));
                    $seller->image = $avatar;
                }
                $seller->save();
                $shop = $seller->shop;
                abort_unless($shop, 422, translate('store_not_found'));
                $shop->name = $data['store_name'];
                $shop->address = $data['store_addresses'][0];
                $shop->contact = $data['phone_numbers'][0];
                $shop->save();
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);
            if ($avatar) ImageManager::delete('seller/'.$avatar);
            throw $e;
        }
        Storage::disk('local')->delete($oldFiles);
        if ($avatar && $previousAvatar) ImageManager::delete('seller/'.$previousAvatar);
        return $request->is('api/*') ? response()->json(['message' => translate('profile_updated_successfully')])
            : back()->with('account_saved', true);
    }

    public function password(Request $request)
    {
        $seller = $this->seller($request);
        $data = $request->validate(['current_password' => 'required|string', 'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()]]);
        if (!Hash::check($data['current_password'], $seller->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => translate('current_password_is_incorrect')]);
        }
        $seller->password = Hash::make($data['password']);
        $seller->save();
        return $request->is('api/*') ? response()->json(['message' => translate('password_updated_successfully')]) : back()->with('account_saved', true);
    }

    public function document(Request $request, string $kind)
    {
        abort_unless(in_array($kind, ['commercial', 'tax'], true), 404);
        $profile = VendorAccountProfile::where('seller_id', $this->seller($request)->id)->firstOrFail();
        $path = $profile->{$kind.'_document'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
