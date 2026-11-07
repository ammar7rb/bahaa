<?php

namespace App\Services;

use App\Utils\Helpers;

class CustomerAccountAccess
{
    public static function allows(string $operation): bool
    {
        if (!auth('admin')->check() || !Helpers::module_permission_check('people')) return false;
        if ((int) auth('admin')->user()->admin_role_id === 1) return true;
        return Helpers::module_permission_check('customer_'.$operation);
    }

    public static function authorize(string $operation): void
    {
        abort_unless(self::allows($operation), 403);
    }
}
