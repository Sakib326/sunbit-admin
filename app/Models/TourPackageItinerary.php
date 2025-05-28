<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TourPackageItinerary extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_package_id',
        'name',
        'title',
        'description',
        'image',
        'position',
    ];

    public function tourPackage()
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }
}
