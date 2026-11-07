<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyMedicalDevice extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['images' => 'array', 'is_published' => 'boolean'];

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): string
    {
        return ! empty($this->images) ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->images[0]) : asset('assets/back-end/img/placeholder/placeholder-1-1.png');
    }
}
