<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Upazilla extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasSlug;

    protected $fillable = ['zella_id', 'name', 'slug', 'code', 'postal_code', 'status'];

    public function zella(): BelongsTo
    {
        return $this->belongsTo(Zella::class);
    }

    public function carRentalPackageLocations()
    {
        return $this->hasMany(CarRentalPackageLocation::class);
    }

    public function carRentalBookingDetails()
    {
        return $this->hasMany(CarRentalBookingDetail::class, 'pickup_upazilla_id');
    }

    // Get available car rental packages in this upazilla
    public function availableCarRentalPackages()
    {
        return CarRentalPackage::whereHas('locations', function ($query) {
            $query->where('upazilla_id', $this->id);
        })->where('status', 'active');
    }
}
