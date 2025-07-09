<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class TourCategory extends Model
{
    use HasFactory;

    protected $table = 'tour_categories';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?? (string) Str::uuid();
        });
    }

    // Fix: Change from 'packages' to 'tourPackages' to match the controller
    public function tourPackages()
    {
        return $this->hasMany(TourPackage::class, 'category_id');
    }

    // Keep old method for backward compatibility
    public function packages()
    {
        return $this->tourPackages();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}