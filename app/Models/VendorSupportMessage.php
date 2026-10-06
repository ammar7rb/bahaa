<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSupportMessage extends Model
{
    protected $fillable = ['vendor_support_thread_id', 'admin_id', 'body'];
    public function thread(): BelongsTo { return $this->belongsTo(VendorSupportThread::class, 'vendor_support_thread_id'); }
}
