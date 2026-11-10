<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/** Read-only access to the existing immutable vendor submission history. */
class VendorProductSubmissionRevision extends Model
{
    public $timestamps = false;
    protected $guarded = ['*'];
    protected $casts = ['seller_content'=>'array','recorded_at'=>'datetime'];
}
