<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarRentalPackageLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_rental_package_id', 'country_id', 'state_id', 'zella_id', 'upazilla_id'
    ];

    // === RELATIONSHIPS ===
    public function carRentalPackage()
    {
        return $this->belongsTo(CarRentalPackage::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function zella()
    {
        return $this->belongsTo(Zella::class);
    }

    public function upazilla()
    {
        return $this->belongsTo(Upazilla::class);
    }

    // === METHODS ===
    public function getFullLocationName()
    {
        $parts = [];

        if ($this->upazilla) {
            $parts[] = $this->upazilla->name;
        }
        if ($this->zella && $this->zella->name !== $this->upazilla?->name) {
            $parts[] = $this->zella->name;
        }
        if ($this->state) {
            $parts[] = $this->state->name;
        }
        if ($this->country) {
            $parts[] = $this->country->name;
        }

        return implode(', ', $parts);
    }
}
