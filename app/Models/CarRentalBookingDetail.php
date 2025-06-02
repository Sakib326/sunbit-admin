<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarRentalBookingDetail extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'booking_id', 'car_rental_package_id', 'pickup_date', 'return_date',
        'rental_days', 'pickup_upazilla_id', 'pickup_address',
        'driver_name', 'driver_phone'
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'return_date' => 'date',
    ];

    // === RELATIONSHIPS ===
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function carRentalPackage()
    {
        return $this->belongsTo(CarRentalPackage::class);
    }

    public function pickupUpazilla()
    {
        return $this->belongsTo(Upazilla::class, 'pickup_upazilla_id');
    }

    // === METHODS ===
    public function getFullPickupLocation()
    {
        if (!$this->pickupUpazilla) {
            return 'Location not specified';
        }

        $location = [];

        $location[] = $this->pickupUpazilla->name;

        if ($this->pickupUpazilla->zella) {
            $location[] = $this->pickupUpazilla->zella->name;
        }

        if ($this->pickupUpazilla->zella?->state) {
            $location[] = $this->pickupUpazilla->zella->state->name;
        }

        return implode(', ', $location);
    }

    public function getRentalSummary()
    {
        return [
            'car' => $this->carRentalPackage->getFullCarName(),
            'pickup_location' => $this->getFullPickupLocation(),
            'pickup_date' => $this->pickup_date->format('d M Y'),
            'return_date' => $this->return_date->format('d M Y'),
            'rental_days' => $this->rental_days,
            'driver' => $this->driver_name,
            'phone' => $this->driver_phone,
        ];
    }

    // === BOOT METHOD ===
    protected static function booted()
    {
        static::saving(function ($detail) {
            // Auto-calculate rental days
            if ($detail->pickup_date && $detail->return_date) {
                $detail->rental_days = $detail->pickup_date->diffInDays($detail->return_date) ?: 1;
            }
        });
    }
}
