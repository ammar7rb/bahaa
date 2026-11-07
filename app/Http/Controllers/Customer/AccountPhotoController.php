<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use App\Services\AccountPhotoAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AccountPhotoController extends Controller
{
    public function __invoke(Request $request, string $kind, int $id, AccountPhotoAccess $access)
    {
        if ($kind === 'seller' && $request->bearerToken() && !$access->canView($kind, $id)) {
            return app(\App\Http\Middleware\SellerApiAuthMiddleware::class)->handle(
                $request, fn () => $this->photo($kind, $id, $access),
            );
        }

        return $this->photo($kind, $id, $access);
    }

    private function photo(string $kind, int $id, AccountPhotoAccess $access)
    {
        abort_unless($access->canView($kind, $id), 404);
        $account = ($kind === 'customer' ? User::query() : Seller::query())->findOrFail($id);
        $filename = (string) $account->image;
        abort_unless(preg_match('/\A[a-zA-Z0-9_-]+\.(?:webp|png|jpe?g|gif)\z/i', $filename), 404);

        $directory = $kind === 'customer' ? 'profile' : 'seller';
        $path = $directory.'/'.$filename;
        $disk = Storage::disk('local');
        $privatePath = 'private-account-photos/'.$path;
        if ($disk->exists($privatePath)) {
            $path = $privatePath;
        } else {
            $diskName = $account->storage()->where('key', 'image')->value('value');
            $disk = Storage::disk($diskName === 's3' ? 's3' : 'public');
        }
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $filename, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
