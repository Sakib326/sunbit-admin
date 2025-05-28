<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TourPackageFaq extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_package_id',
        'question',
        'answer',
        'position',
    ];

    public function tourPackage()
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }
}
