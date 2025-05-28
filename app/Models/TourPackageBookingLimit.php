<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TourPackageBookingLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_package_id',
        'date',
        'max_booking',
    ];

    public function tourPackage()
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }
}
