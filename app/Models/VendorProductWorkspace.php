<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorProductWorkspace extends Model
{
    protected $guarded = [];

    protected $casts = [
        'seller_content' => 'array', 'editorial_content' => 'array',
        'approved_seller_content' => 'array', 'legacy_exempt' => 'boolean',
        'publication_suspended' => 'boolean',
        'private_reference' => 'integer', 'revision' => 'integer',
        'approved_revision' => 'integer', 'editorial_revision' => 'integer',
        'submitted_at' => 'datetime', 'approved_at' => 'datetime',
    ];
}
