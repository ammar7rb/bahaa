<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderWorkItemEvent extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];

    public function workItem(): BelongsTo { return $this->belongsTo(OrderWorkItem::class, 'work_item_id'); }
}
