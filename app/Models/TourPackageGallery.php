<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TourPackageGallery extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_package_id',
        'image_url',
        'position',
        'is_featured',
    ];

    public function tourPackage()
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }
}
