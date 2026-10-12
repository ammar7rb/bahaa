<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorAccountProfile extends Model
{
    protected $guarded = ['id', 'seller_id'];
    protected $casts = ['phone_numbers' => 'array', 'store_addresses' => 'array'];
    protected $hidden = ['commercial_document', 'tax_document'];
}
