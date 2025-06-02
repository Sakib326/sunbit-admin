<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zella extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasSlug;

    protected $fillable = ['state_id', 'name', 'slug', 'code', 'status'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function upazillas(): HasMany
    {
        return $this->hasMany(Upazilla::class);
    }

    public function carRentalPackageLocations()
    {
        return $this->hasMany(CarRentalPackageLocation::class);
    }

    public function carRentalBookingDetails()
    {
        return $this->hasMany(CarRentalBookingDetail::class, 'pickup_upazilla_id');
    }
}
