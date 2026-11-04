<?php

namespace App\Services;

use App\Models\Seller;

class AccountPhotoAccess
{
    public function canView(string $accountType, int $accountId): bool
    {
        if (!$accountId || app()->runningInConsole() && !app()->runningUnitTests()) {
            return false;
        }
        if (auth('admin')->check()) {
            return true;
        }
        if ($accountType === 'customer') {
            return (int) auth('customer')->id() === $accountId
                || (request()->bearerToken() && (int) auth('api')->id() === $accountId);
        }

        $authenticatedSeller = request()->input('seller');
        return (int) auth('seller')->id() === $accountId
            || ($authenticatedSeller instanceof Seller && (int) $authenticatedSeller->id === $accountId);
    }
}
