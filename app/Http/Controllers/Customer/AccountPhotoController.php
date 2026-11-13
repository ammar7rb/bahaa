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
        $directory = $kind === 'customer' ? 'profile' : 'seller';
        $filename = $this->recoverTruncatedName($filename, $directory);
        abort_unless(preg_match('/\A[a-zA-Z0-9_-]+\.(?:webp|png|jpe?g|gif)\z/i', $filename), 404);

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

    /**
     * Older uploads were saved as "<uuid>.webp" (41 chars) into a varchar(30) column, so the
     * database kept only the first 30 characters. Recover the real file by its unique prefix.
     */
    private function recoverTruncatedName(string $filename, string $directory): string
    {
        if ($filename === '' || $filename === 'def.png' || str_contains($filename, '.')
            || !preg_match('/\A[a-zA-Z0-9_-]{16,40}\z/', $filename)) {
            return $filename;
        }
        foreach (Storage::disk('local')->files('private-account-photos/'.$directory) as $file) {
            $base = basename($file);
            if (str_starts_with($base, $filename) && preg_match('/\.(?:webp|png|jpe?g|gif)\z/i', $base)) {
                return $base;
            }
        }

        return $filename;
    }
}
