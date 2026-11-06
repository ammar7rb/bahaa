<?php

namespace App\Services;

use App\Models\AccountActivationCase;
use App\Models\AccountActivationDocument;
use App\Models\AccountActivationProfileField;
use Illuminate\Support\Facades\Schema;

/** Read-only admin projection; private files stay behind existing authorized routes. */
class CustomerAdminProfileRecordsService
{
    public function forCustomer(int $customerId): array
    {
        return ['documents_ready'=>false, 'fields_ready'=>false, 'documents'=>[], 'fields'=>[]];
    }
}
