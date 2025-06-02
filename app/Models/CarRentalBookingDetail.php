<?php

// filepath: app/Models/CarRentalBookingDetail.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarRentalBookingDetail extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'booking_id', 'car_rental_package_id', 'pickup_date', 'return_date', 'rental_days'
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

    // === METHODS ===
    public function getRentalSummary()
    {
        return [
            'car' => $this->carRentalPackage ? $this->carRentalPackage->getFullCarName() : 'Car package not found',
            'pickup_date' => $this->pickup_date->format('d M Y'),
            'return_date' => $this->return_date->format('d M Y'),
            'rental_days' => $this->rental_days,
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
