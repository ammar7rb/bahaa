<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSupportThread extends Model
{
    protected $fillable = ['seller_id', 'created_by_admin_id', 'subject', 'status'];
    public function messages(): HasMany { return $this->hasMany(VendorSupportMessage::class)->orderBy('id'); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
}
